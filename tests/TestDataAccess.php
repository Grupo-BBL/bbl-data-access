<?php

use PHPUnit\Framework\TestCase;

require_once(dirname(__FILE__, 2)."/vendor/autoload.php");



function getTestableSqliteConnection()
{
    static $sqliteDBConnection = null;

    if (!$sqliteDBConnection)
    {
        $sqliteDBConnection = new PDO("C:\AppStonewood\Test\dbForTestRunner.sqlite", SQLITE3_OPEN_READWRITE|SQLITE3_OPEN_CREATE);
    } 

    return $sqliteDBConnection;
}

function getTestableSqliteDataAccess()
{
    $sqliteDBConnection = getTestableSqliteConnection();

    static $testableDataAccess = null;

    if (!$testableDataAccess)
    {
        $testableDataAccess = new TestableDataAccess($sqliteDBConnection);
    }

    return $testableDataAccess;
}

final class TestDataAccess extends TestCase
{
    public function testInsertUpdate()
    {
        $testableDataAccess = getTestableSqliteDataAccess();

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
        $testableDataAccess = getTestableSqliteDataAccess();

    }
    public function testSaveImageSavesToClassPath()
    {
        $testableDataAccess = getTestableSqliteDataAccess();
    }
    public function testSaveImageSavesToOptionPath()
    {
        $testableDataAccess = getTestableSqliteDataAccess();
    }

    public function testUpdateStringWorksWhenPrimaryKeyIsFirstInMapping()
    {
        $testableDataAccess = getTestableSqliteDataAccess();
    }
}
