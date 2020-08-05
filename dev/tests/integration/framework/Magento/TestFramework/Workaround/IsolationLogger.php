<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\TestFramework\Workaround;



use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;

/**
 * Trigger queue to process storefront consumers
 */
class IsolationLogger
{
    private $previousTest = 'N/A';
    private $currenTest;

    /**
     * Handler for 'startTest' event.
     *
     * Sync Magento monolith App data with Catalog Storefront Storage.
     *
     * @param \PHPUnit\Framework\TestCase $test
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function startTest(\PHPUnit\Framework\TestCase $test)
    {
        $this->currenTest = \get_class($test) . '::' .  $test->getName(true);
        foreach ($this->dbStateTables as $dbStateTable => $method) {
            $data = $this->pullDbState($dbStateTable);
            $this->{$method}($data);
        }

        // set current test as "previous"
        $this->previousTest = \get_class($test) . '::' .  $test->getName(true);

    }

    /**
     * @var string[]
     */
    private $dbStateTables = [
        'catalog_product_entity' => 'assertIsEmpty',
        'eav_attribute' => 'eavAttributeAssert',
        'catalog_category_entity' => 'assertTwoRecords',
        'eav_attribute_set' => 'attributeSetAssert',
        'store' => 'assertTwoRecords',
        //'catalog_data_exporter_products' => 'assertIsEmpty',
        //'catalog_data_exporter_product_attributes' => 'assertIsEmpty',
        //'catalog_data_exporter_categories' => 'assertIsEmpty'
    ];

    /**
     * @param array $data
     * @throws \Exception
     */
    private function attributeSetAssert(array $data)
    {
        if (count($data) > 9) {
            $this->log('Extra attributes', $data);
        }
    }

    /**
     * @param array $data
     * @throws \Exception
     */
    private function assertTwoRecords(array $data)
    {
        //2 default records
        if (count($data) > 2) {
            $this->log('More then 2 records', $data);
        }
    }

    /**
     * @param array $data
     * @throws \Exception
     */
    private function eavAttributeAssert(array $data)
    {
        //178 - default number of attributes
        if (count($data) > 178) {
            $last = array_slice($data, 178, count($data));
            $this->log('extra Eav attributes', $last);
        }
    }

    /**
     * @param $data
     */
    private function assertIsEmpty(array $data)
    {
        if (!empty($data)) {
            $this->log('Data is not empty', $data);
        }
    }

    private function log($message, array $data)
    {
        echo '>>>isolation>>>' . "\n" .
            'previous test: ' .$this->previousTest . "\n" .
            $message . var_export($data, true) .
            'current test: ' . $this->currenTest . "\n" .
            '<<<isolation<<<' . "\n" .
            "\n\n";
    }

    /**
     * @param string $table
     * @return array
     */
    private function pullDbState(string $table)
    {
        $resource = ObjectManager::getInstance()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $select = $connection->select()
            ->from($table);
        return $connection->fetchAll($select);
    }
}