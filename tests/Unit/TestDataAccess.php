<?php

class TestDataAccess extends \PHPUnit\Framework\TestCase
{
    private DataAccess $db;

    protected function setUp(): void
    {
        $this->db = DAM::get("testable_items");
    }

    public function testInsertUpdate()
    {
        $testableDataAccess = DAM::get("testable_items");

        // $obj["id"]             =           
        $obj["a"]              = "Test Update Object";        
        $obj["b"]              = "My first write";
        $obj["date_created"]   = date('Y-m-d H:i:s');
        $obj["date_modified"]  = null;

        $testableDataAccess->insert($obj);

        $this->assertTrue(
            isset($obj["id"]),
            "No se ha insertado un ID en este objeto."); 
    }
    public function testGetOneReturns()
    {
        // TODO: Implement testSaveImageSavesToClassPath() method.

    }
    public function testSaveImageSavesToClassPath()
    {
        // TODO: Implement testSaveImageSavesToClassPath() method.
    }
    public function testSaveImageSavesToOptionPath()
    {
        // TODO: Implement testSaveImageSavesToOptionPath() method.
    }

    public function testUpdateStringWorksWhenPrimaryKeyIsFirstInMapping()
    {
        // TODO: Implement testUpdateStringWorksWhenPrimaryKeyIsFirstInMapping() method.
    }
}
