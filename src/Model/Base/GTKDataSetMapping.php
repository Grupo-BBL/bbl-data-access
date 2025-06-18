<?php

class GTKDataSetMapping {
    public $ordered           = array();
    public $sqlServerMapping  = array();
    public $phpMapping        = array();
    public $primaryMapping    = array();
    public $primaryKeyMapping = null;
    public $nonPrimaryLookup  = null;
    private $dataAccessor;
    public $tableName;


    public function getTableName()
    {
        if ($this->dataAccessor)
        {
            return $this->dataAccessor->tableName();
        }

        return $this->tableName;
    }

    public function __construct($dataAccessor, $columns)
    {
        // $debug = false;
        // $this->sqlServerMapping = array();
        // $this->phpMapping       = array();

        $this->dataAccessor = $dataAccessor;

        foreach($columns as $key => $item) 
        {
            // if (!$this->primaryMapping)
            // {
            //     $this->primaryMapping = $columnMapping;
            // }
            /*
            if ($debug)
            {
                if (is_array($columnMapping))
                {
                    gtk_log("Will add column: ".serialize($columnMapping))
                }
            }
            */
            $toAdd = $item;
            
            if (is_string($item))
            {
                $toAdd = new GTKColumnMapping($this->dataAccessor, $item);
            }
            else if (is_string($key) && is_array($item))
            {
                $toAdd = new GTKColumnMapping($this->dataAccessor, $item);
            }

            $this->addColumn($toAdd);

            if ($this->dataAccessor)
            {
                $toAdd->dataAccessor = $this->dataAccessor;
            }
        }
    }



    public function setDataAccessor($dataAccessor)
    {
        $debug = false;

        $this->dataAccessor = $dataAccessor;

        foreach ($this->ordered as $columnMapping)
        {
            if ($debug)
            {
                error_log("GTKColumnMapping / Setting data accessor.");
            }
            $columnMapping->dataAccessor = $dataAccessor;
        }
    }

    public function valueForIdentifier($array, $options = null)
    {
        return $this->valueForKey('identifier', $array, $options);
    }

    public function valueForKey($key, $array, $options = null)
    {
        $debug = false;

        if ($debug)
        {
            gtk_log("GTKDataSetMapping - `valueForKey` - ".$key);
           
        }

        if ($key instanceof GTKColumnMapping)
        {
            return $key->getValueFromArray($array);
        }

        if (!is_array($array) && (count($array) === 0))
        {
            return null;
        }

        if (isset($array[$key]))
        {
            return $array[$key];
        }

        if ($debug)
        {
            gtk_log("Not found cleanly in array. Trying advanced options...".serialize($array));
        }

        $argKey = $key;

        if (!isTruthy($key) || $key === "")
        {
            return null;
        }

        if ($options)
        {
            if (array_key_exists('debug', $options))
            {
                $debug = $options['debug'];
            }
        }

        if ($debug) 
        { 
            if (!isDictionary($array))
            {
                if (DataAccessManager::get('persona')->isDeveloper())
                {
                    gtk_log("`DataAccess` - valueForKey — Are you sure you're passing in an object?? Is it an array of arrays?");
                    gtk_log("DataAccess` - valueForKey — Array: ".serialize($array));
                    gtk_log("DataAccess` - valueForKey — Data Mapping: ".serialize($this->ordered));
                    throw new Exception("`DataAccess` - valueForKey — Are you sure you're passing in an object?? Is it an array of arrays?");
                }
            }
        }

        $idKeys = [
            "id",
            "identifier",
            "identificador",
        ];

        if (in_array($key, $idKeys))
        {
            $idAsPHPKey = array_key_exists($key, $this->phpMapping);

            if ($debug)
            {
                gtk_log("ID as PHP Key?: ".$idAsPHPKey);
            }
            
            if (!$idAsPHPKey)
            {
                if ($debug)
                {
                    gtk_log("Searching for primary key mapping...");
                }

                $primaryMapping = $this->primaryKeyMapping;

                if (is_null($primaryMapping))
                {
                    throw new Exception("Este objeto no tienen identificador");
                }
                
                return $primaryMapping->getValueFromArray($array);
            }

            foreach ($idKeys as $idKey)
            {
                if (array_key_exists($idKey, $array))
                {
                    return $array[$idKey];
                }
            }   
        }

        $keysToTry = [
            $key,
            $this->tableName."_".$key,
        ];

        if ($debug)
        {

        }

        $keyToUse      = null;
        $columnMapping = null;

        foreach ($keysToTry as $key)
        {
            if ($keyToUse)
            {
                continue;
            }

            $keyExists = array_key_exists($key, $array);

            if ($keyExists)
            {
                if ($debug) { gtk_log("Found PHP key: $key Returing."); }
                $keyToUse = $key;
                $value = $array[$key];
                if ($value === '')
                {
                    return null;
                }
                else
                {
                    return $value;
                }
            }

            $columnMapping = $this->columnMappingForKey($key);

            if ($columnMapping)
            {
                if ($columnMapping->sqlServerKey)
                {
                    $columnName = ($columnMapping->sqlServerKey);
    
                    if ($debug)
                    {
                        // gtk_log("Got column mapping: ".serialize($columnMapping));
                        gtk_log("Seaching by column name: $columnName");
                    }
    
                    if (array_key_exists($columnName, $array))
                    // if (isset($array[$columnName]))
                    {
                        $value = $array[$columnName];
                        if ($value === '')
                        {
                            return null;
                        }
                        else
                        {
                            return $value;
                        }
                    }
                }
            }
        }

        if ($debug || DataAccessManager::get('persona')->isDeveloper())
        {
            $className = get_class($this);
            gtk_log("$className:: `valueForKey` - No column mapping for key: {$argKey} - Item: ".serialize($array));
        }


        if ($debug)
        {
            gtk_log("Returning null — Column name ($columnName) not found:".serialize($array));
            gtk_log("Column Mapping PHP Key: ".$this->phpKey);
            gtk_log("Column Mapping SQL Key: ".$this->sqlServerKey);
            gtk_log("Key: ".$key);
            gtk_log("Array: ".serialize($array));
        }


        return null;
    }
    /*
    function dbColumnNameForPHPKey($key)
    {
        return $this->dataMapping->dbColumnNameForPHPKey($key);
    }
    */
    public function print() 
    {
        foreach ($this->ordered as $columnMapping) {
            printf("%-20s %-20s %-30s\n", $columnMapping->getPhpKey(), $columnMapping->getSqlServerKey(), $columnMapping->getFormLabel());
        }
    }

    public function generateInsertQuery($tableName) 
    {
        $sql = "INSERT INTO $tableName (";

        $first = true;
        foreach($this->ordered as $columnMapping) 
        {
            if ($columnMapping->isVirtual() || $columnMapping->isAutoIncrement() || $columnMapping->hideOnInsert())
            {
                continue;
            }
            if (!$first) {
                $sql .= ", ";
            }
            $sql .= $this->getColumnKeyForDB($columnMapping->phpKey);
            $first = false;
        }
        $sql .= ") VALUES (";

        $first = true;
        foreach($this->ordered as $columnMapping) {
            if ($columnMapping->isVirtual() || $columnMapping->isAutoIncrement() || $columnMapping->hideOnInsert())
            {
                continue;
            }
            if (!$first) {
                $sql .= ", ";
            }
            $sql .= ":" . $columnMapping->phpKey;
            $first = false;
        }
        $sql .= ")";

        return $sql;
    }

    public function generateUpdateQuery($tableName) 
    {
        $sql = "UPDATE $tableName SET ";

        $first = true;
        foreach($this->ordered as $columnMapping) {
            if (!$first) {
                $sql .= ", ";
            }
            $sql .= $this->getColumnKeyForDB($columnMapping->phpKey) . " = :" . $columnMapping->phpKey;
            $first = false;
        }

        $sql .= " WHERE " . $this->getColumnKeyForDB($this->primaryMapping->phpKey) . " = :" . $this->primaryMapping->phpKey;

        return $sql;
    }

    public function foreach($closure) {
        foreach($this->ordered as $dataMapping) {
            // gtk_log("dataMapping: {serialize($dataMapping)}");
            $closure($dataMapping);
        }
    }


    public function columnMappingAtIndex($index) {
        return $this->ordered[$index];
    }

    public function columnMappingForKey($key)
    {
        if (isset($this->phpMapping[$key]))
        {
            return $this->phpMapping[$key];
        }

        if (isset($this->sqlServerMapping[$key]))
        {
            return $this->sqlServerMapping[$key];
        }

        if (($key === "id") || ($key === "identificador") || ($key === "identifier"))
        {
            return $this->primaryKeyMapping;
        }

        return null;
    }

    public function columnMappingForPHPKey($phpKey, $crash = false)
    {
        if ($crash)
        {
            if (!isset($this->phpMapping[$phpKey]))
            {
                $className = get_class($this);
                $methodName = __FUNCTION__;
                throw new ColumnMappingException(
                            $phpKey, 
                            $className, 
                            $methodName);
            }
        }
    
        return $this->phpMapping[$phpKey] ?? null;
    }

    public function columnMappingForSqlServerKey($key) {
        return $this->sqlServerMapping[$key];
    }

    public function addColumn($columnMapping) 
    {
        if (!$columnMapping->isVirtual())
        {
            if ($columnMapping->isUpdateKey())
            {
                array_push($this->primaryMapping, $columnMapping);
            }
            if ($columnMapping->isPrimaryKey())
            {
                $this->primaryKeyMapping = $columnMapping;
            }
            if ($columnMapping->nonPrimaryLookup)
            {
                $this->nonPrimaryLookup = $columnMapping;
            }
            $columnMapping->sqlServerKey = $columnMapping->phpKey;
            $this->sqlServerMapping[$columnMapping->phpKey] = $columnMapping;
            //$this->sqlServerMapping[$columnMapping->sqlServerKey] = $columnMapping;
        }

        array_push($this->ordered, $columnMapping);
        $this->phpMapping[$columnMapping->phpKey] = $columnMapping;
    }

    public function dbColumnsPrefixedWith($prefix)
    {
        $sql     = "";
        $isFirst = true;

        foreach ($this->ordered as $columnMapping)
        {
            if (!$isFirst)
            {
                $sql .= ",";
            }
        
            $sql .= $columnMapping->getSqlColumnName();

            $isFirst = false;
        }

        return $sql;
    }

    public function dbColumnNameForKey($key)
    {
        $columnMapping = $this->columnMappingForKey($key);

        // If the column mapping is not found...return the key
        if (!$columnMapping)
        {
            return $key;
        }

        // If the column mapping is not found, throw an exception
        if (!$columnMapping)
        {
            throw new Exception("`dbColumnNameForKey` - $this->tableName - :::No column mapping for key: " . $key);
            return null;
        }

        return $columnMapping->getSqlColumnName();
    }

    public function dbColumnNameForPHPKey($phpKey) {
        $columnMapping = $this->columnMappingForPHPKey($phpKey);

        if (!$columnMapping)
        {
            throw new Exception("`dbColumnNameForPHPKey` - $this->__constructtableName - :::No column mapping for PHP key: " . $phpKey);
            return null;
        }

        if ($columnMapping->sqlServerKey)
        {
            return $columnMapping->sqlServerKey;
        }
        else
        {
            return $columnMapping->phpKey;
        }
    }

    public function getColumnKeyForDB($phpKey)
{
    
    $className = get_class($this);

    // Obtén la config de la base de datos actual
    $dbConfig = $_GLOBALS["DataAccessManager_DB_CONFIG"][$this->dbName] ?? [];

    // Si la tabla está en la lista de IGNORAR_DB_KEY..., usa phpKey
    if (isset($dbConfig["IGNORAR_DB_KEY_PARA_LOOKUP_Y_ESCRITURA"]) &&
        in_array($className, $dbConfig["IGNORAR_DB_KEY_PARA_LOOKUP_Y_ESCRITURA"])) {
        return $phpKey;
    }

    // Si no, usa el sqlServerKey (o el phpKey si no existe)
    return $this->dataMapping->phpMapping[$phpKey]->sqlServerKey ?? $phpKey;
}


}
