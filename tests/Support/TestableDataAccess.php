<?php

class TestableDataAccess extends DataAccess
{
    public function register()
    {

        $columns = [
            GTKColumnMapping::stdStyle($this, "id",             null, "ID", [
                "isPrimaryKey"    => true,
                "isAutoIncrement" => true,
            ]),
            GTKColumnMapping::stdStyle($this, "a",              null, "A"),
            GTKColumnMapping::stdStyle($this, "b",              null, "B"),
            GTKColumnMapping::stdStyle($this, "date_created",   null, "Date Created"),
            GTKColumnMapping::stdStyle($this, "date_modified",  null, "Date Modified"),
        ]; 


        $this->dataMapping = new GTKDataSetMapping($this, $columns);
    }
 

    private function generateMicroTimeUUID() 
    {
        $microTime = microtime(true);
        $microSeconds = sprintf("%06d", ($microTime - floor($microTime)) * 1e6);
        $time = new DateTime(date('Y-m-d H:i:s.' . $microSeconds, $microTime));
        $time = $time->format("YmdHisu"); // Format time to a string with microseconds
        return md5($time);                // You can also use sha1 or any other algorithm
    }
}

