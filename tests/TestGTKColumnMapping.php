<?php


class TestGTKColumnMapping extends \PHPUnit\Framework\TestCase
{
    public function testNonAutoIncrementColumn()
    {
        $columnMapping = new GTKColumnMapping($this, "id", [
                    "isAutoIncrement" => false,
                    "isPrimaryKey"    => true,
                    "forceInsertion"  => true,
                    "isNullable"      => false, 
        ]);

        $sqliteCreateSQL = $columnMapping->getCreateSQLForDriverName("sqlite");

        $this->assertEquals("id PRIMARY KEY NOT NULL", $sqliteCreateSQL);
    }
}
