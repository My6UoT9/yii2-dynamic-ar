<?php
/**
 * @link https://github.com/tom--/dynamic-ar
 * @copyright Copyright (c) 2015 Spinitron LLC
 * @license http://opensource.org/licenses/ISC
 */

namespace tests\unit;

use my6uot9\dynamicAr\DynamicActiveQuery;
use tests\unit\data\ar\UsualModel;
use tests\unit\data\BaseRecord;
use tests\unit\data\dar\MissingDynColumn;
use yiiunit\framework\db\DatabaseTestCase;
use tests\unit\data\dar\Product;

/**
 * @author Danil Zakablukovskii danil.kabluk@gmail.com
 */
class DynamicActiveQueryTest extends DatabaseTestCase
{

    protected function setUp()
    {
        static::$params = require(__DIR__ . '/data/config.php');
        $this->driverName = array_keys(static::$params['databases'])[0];
        parent::setUp();
        BaseRecord::$db = $this->getConnection();
    }

    public function testDynamicSelect()
    {
        // default
        $query = new DynamicActiveQuery(Product::class);
        $query->select('*');
        $command = $query->createCommand();
        $this->assertEquals('SELECT *, COLUMN_JSON(`product`.`dynamic_columns`) AS `dynamic_columns` FROM `product`', $command->getRawSql());

        // one dynamic attribute
        $query = new DynamicActiveQuery(Product::class);
        $query->select(['(!cost|decimal(6,2)!)']);
        $command = $query->createCommand();
        $this->assertEquals("SELECT COLUMN_GET(`product`.`dynamic_columns`, 'cost' AS decimal(6,2)) FROM `product`", $command->getRawSql());

        // few dynamic attributes
        $query = new DynamicActiveQuery(Product::class);
        $query->select(['(!cost|decimal(6,2)!), (!price.wholesale.12|decimal(6,2)!)']);
        $command = $query->createCommand();
        $this->assertEquals("SELECT COLUMN_GET(`product`.`dynamic_columns`, 'cost' AS decimal(6,2)), COLUMN_GET(COLUMN_GET(COLUMN_GET(`product`.`dynamic_columns`, 'price' AS BINARY), 'wholesale' AS BINARY), '12' AS decimal(6,2)) FROM `product`", $command->getRawSql());

        // few dynamic, one static
        $query = new DynamicActiveQuery(Product::class);
        $query->select(['(!cost|decimal(6,2)!), (!price.wholesale.12|decimal(6,2)!), id']);
        $command = $query->createCommand();
        $this->assertEquals("SELECT COLUMN_GET(`product`.`dynamic_columns`, 'cost' AS decimal(6,2)), COLUMN_GET(COLUMN_GET(COLUMN_GET(`product`.`dynamic_columns`, 'price' AS BINARY), 'wholesale' AS BINARY), '12' AS decimal(6,2)), AS `id` FROM `product`", $command->getRawSql());
    }

    public function testWhere()
    {
        $query = new DynamicActiveQuery(Product::class);
        $query->select('*')
            ->where('(!one.two|char!) = t');
        $command = $query->createCommand();
        $this->assertEquals("SELECT *, COLUMN_JSON(`dynamic_columns`) AS `dynamic_columns` FROM `product` WHERE COLUMN_GET(COLUMN_GET(dynamic_columns, 'one' AS BINARY), 'two' AS char) = t",
            $command->getRawSql());

        $query->andWhere('(!one.three|int!) = 5');
        $command = $query->createCommand();
        $this->assertEquals("SELECT *, COLUMN_JSON(`dynamic_columns`) AS `dynamic_columns` FROM `product` WHERE (COLUMN_GET(COLUMN_GET(dynamic_columns, 'one' AS BINARY), 'two' AS char) = t) AND (COLUMN_GET(COLUMN_GET(dynamic_columns, 'one' AS BINARY), 'three' AS int) = 5)",
            $command->getRawSql());
    }

    /**
     * Every type listed in $this->types() should be recognised as dynamic field type
     */
    public function testTypesProcessing()
    {
        // it's enough to just check select - logic is similar for the whole sql query
        $query = new DynamicActiveQuery(Product::class);

        foreach ($this->types() as $k => $possibleTypes) {
            foreach ($possibleTypes as $type) {
                $query->select(["(!test|$type!)"]);
                $command = $query->createCommand();

                $sql = $command->getRawSql();
                $this->assertNotContains("(!test|$type!)", $sql,
                    "Type $type should be processed, there shouldn't be any user's dynamic queries");
                $this->assertContains("as $type", $sql, "Type $type should be processed", true);
            }
        }
    }

    public function testNestedTypesProcessing()
    {
        // it's enough to just check select - logic is similar for the whole sql query
        $query = new DynamicActiveQuery(Product::class);

        foreach ($this->types() as $k => $possibleTypes) {
            foreach ($possibleTypes as $type) {
                $query->select(["(!test.child|$type!)"]);
                $command = $query->createCommand();

                $sql = $command->getRawSql();
                $this->assertNotContains("(!test|$type!)", $sql,
                    "Type $type should be processed, there shouldn't be any user's dynamic queries");
                $this->assertContains("as $type", $sql, "Type $type should be processed", true);
            }
        }
    }

    public function testAttributeWithoutTypeProcessing()
    {
        $query = new DynamicActiveQuery(Product::class);
        $query->select('(!one.two!)');
        $command = $query->createCommand();
        $this->assertEquals("SELECT COLUMN_GET(COLUMN_GET(dynamic_columns, 'one' AS BINARY), 'two' AS CHAR) FROM `product`",
            $command->getRawSql());
    }

    private function types()
    {
        return [
            'binary' => [
                'binary',
                'binary(5)',
            ],
            'char' => [
                'char',
                'char(5)',
            ],
            'time' => [
                'time',
                'time(5)',
            ],
            'datetime' => [
                'datetime',
                'datetime(5)',
            ],
            'date' => ['date'],
            'decimal' => [
                'decimal',
                'decimal(5)',
                'decimal(5,6)',
            ],
            'double' => [
                'double',
                'double(5,6)',
            ],
            'int' => [
                'int',
                'integer',
            ],
            'signed' => [
                'signed',
                'signed int',
                'signed integer',
            ],
            'unsigned' => [
                'unsigned',
                'unsigned int',
                'unsigned integer',
            ],
        ];
    }

    public function testExceptionForMissingDynColumn()
    {
        $this->setExpectedException('yii\base\Exception');

        $query = new DynamicActiveQuery(MissingDynColumn::class);
        $query->one();
    }

    public function testIndexByExceptionForMissingDynColumn()
    {
        $this->setExpectedException('yii\base\UnknownPropertyException');

        $query = new DynamicActiveQuery(Product::class);
        $query->select('name')->asArray()->indexBy('str');
        $query->all();
    }
}