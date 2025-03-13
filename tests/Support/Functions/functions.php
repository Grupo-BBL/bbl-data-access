<?php
function TestableDataAccess_generateMicroTimeUUID() 
{
    $microTime    = microtime(true);
    $microSeconds = sprintf("%06d", ($microTime - floor($microTime)) * 1e6);
    $time         = new DateTime(date('Y-m-d H:i:s.' . $microSeconds, $microTime));
    $time         = $time->format("YmdHisu"); // Format time to a string with microseconds
    return md5($time); // You can also use sha1 or any other algorithm
}   
