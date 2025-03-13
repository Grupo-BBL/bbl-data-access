<?php
require_once(dirname(__FILE__, 2)."/vendor/autoload.php");

$envpath = dirname(__FILE__,2)."/.secret/env.php";
echo $envpath."\n";
$_GLOBALS["SECRET_ENV_PATH"] = $envpath;

class StonewoodPersona {

    



}


DataAccessManager::get("persona");
