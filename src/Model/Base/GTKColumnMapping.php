<?php

/*
Why is this not selecting in SQLite SELECT * FROM role_permission_relationships WHERE 
role_id = 10  and this is: SELECT * FROM role_permission_relationships WHERE 
role_id = "10"? 

//-------------------------------------------------------------

In SQLite, the behavior you're observing is due to the dynamic typing system SQLite uses, which is quite different from the static typing system in many other database management systems. SQLite uses a dynamic type system where the type of a value is associated with the value itself, not with its container. This is part of what makes SQLite flexible, but it can also lead to some confusion when dealing with numeric and text data types.

When you perform a query like `SELECT * FROM role_permission_relationships WHERE role_id = 10`, SQLite looks for a numeric match for `role_id`. If `role_id` has been stored in the database as an integer, this query will work as expected.

However, if `role_id` was stored as text (even if it's a number like "10"), you need to query it using a string to match the type, like so: `SELECT * FROM role_permission_relationships WHERE role_id = "10"`. This tells SQLite to treat the comparison as a string comparison, not a numeric comparison.

This discrepancy typically arises because of how the data was inserted or defined in the database schema. If the `role_id` column was defined without a strict type, or if data was inserted into the table using different types (SQLite's flexibility allows this), you might end up with a mixture of types in the same column, or with the column being treated differently than you expect.

To avoid such issues, it's a good practice to ensure that data is consistently typed both when you create the table and when you insert or update its data. For example, if `role_id` is intended to be an integer, you might want to make sure it's always handled as an integer in your database operations. Additionally, using SQLite's type affinity system wisely can help in ensuring data types are handled consistently. SQLite does try to convert types where it makes sense, but explicitly managing your data types can prevent these kinds of surprises.


*/

use function PHPUnit\Framework\throwException;

class CustomInputFunctionArgument
{
    public $dataAccessor;
    public $objectID;
    public $columnMapping;
    public $options;
    public $item;
    public $user;

    public function __construct()
    {
    }

    public function getColumnName()
    {
        return $this->columnMapping->phpKey;
    }
    public function getColumnValue()
    {
        return $this->columnMapping->valueFromDatabase($this->item);
    }
    public function getOptions()
    {
        return $this->options;
    }
}

class GTKColumnMapping extends GTKColumnBase
{
    
    public $isUpdateKey;
    public $sqlServerKey;
    public $type;
    public $databaseType;
    public $nonPrimaryLookup;

    public $columnType;
    public $columnSize;
    public $allowNulls;
    public $defaultValue;
    public $isAutoIncrement;

    public function isUpdatable()
    {
        if ($this->isAutoIncrement())
        {
            return false;
        }

        if ($this->isPrimaryKey())
        {
            return false;
        }

        return true;
    }

    public function isInsertable()
    {
        if ($this->isAutoIncrement())
        {
            return false;
        }

        return true;
    }

    public function listDisplayForDataSourceUserItem($dataSource, $user, $item, $itemIdentifier, $options = null)
    {
        $debug = false; // $this->debug ?? false; 

        if ($debug)
        {
            error_log("GTKColumnMapping->listDispaly --- ".$this->phpKey);
        }

        $toReturn     = "";
        
        $value        = $this->valueFromDatabase($item);

        $transformValueOnLists = $this->transformValueOnLists;

        if ($transformValueOnLists)
        {
            $value = $transformValueOnLists($item, $value);
        }
        
        $wrapStart    = "<td ";
        $wrapStart   .= ' id="cell-'.$dataSource->dataAccessorName.'-'.$itemIdentifier.'-'.$this->phpKey.'"';
        $wrapStart   .= " class='text-center align-middle'";
        $wrapStart   .=  ">";
        $wrapEnd      = "</td>";

        if ($this->isPrimaryKey())
        {
            $updatePermission = $dataSource->userHasPermissionTo("update", $user, $item);

            if ($debug)
            {
                error_log("Is Primary Key - ".$this->phpKey." - Has update permission? ".$updatePermission);
            }

            if ($updatePermission)
            {
                return '<td>'.$dataSource->editLinkForItem($item, [
                    'label' => $value,
                ]).'</td>';
            }


            $readPermission = $dataSource->userHasPermissionTo("show", $user, $item);

            if ($readPermission)
            {
                return '<td>'.$dataSource->showLinkForItem($item, [
                    'label' => $value,
                ]).'</td>';
            }
            
            return '<td>'.$value.'</td>';
        }      
        
        $htmlForValue = "";

        $href = null;

        if ($this->linkTo)
        {

            $linkToModel = null;
            $lookupOnKey = null;

            if (is_string($this->linkTo))
            {
                $linkToModel = $this->linkTo;
                $lookupOnKey = "id";
            }
            else if (is_array($this->linkTo))
            {
                $linkToModel = $this->linkTo["model"];
                $lookupOnKey = $this->linkTo["lookupOnKey"] ?? "id";
            }

            if (!$linkToModel || !$lookupOnKey)
            {
                throw new Exception("Invalid `linkTo` protocol for ".get_class($this)." `linkTo`: ".print_r($this->linkTo, true));
            }

            $baseURL = $linkToModel."/edit";

            $queryParameters = [
                "data_source" => $linkToModel,
                $lookupOnKey  => $value,
            ];

            $href = "/".$baseURL."?".http_build_query($queryParameters);


            if ($debug)
            {
                error_log("linkTo - ".$href);
            }
        }

        if ($href)
        {
            $htmlForValue .= '<a ';
            $htmlForValue .= ' href="'.$href.'" ';
            $htmlForValue .= ">";
        }



        $htmlForValue .= $value;
      
        if ($href)
        {
            $htmlForValue .= '</a>';
        }
        
        $toReturn .= $wrapStart;
        $toReturn .= $htmlForValue;
        $toReturn .= $wrapEnd;

        if ($debug)
        {
            error_log("GTKColumnMapping->listDisplay: --- \n  ".$toReturn);
        }


        return $toReturn;
    }

    public function doesItemContainOurKey($item)
    {
        if (parent::doesItemContainOurKey($item))
        {
            return true;
        }
        
        return isset($item[$this->sqlServerKey]);
    }

    public static function stdStyle($dataSource, $phpKey, $sqlServerKey, $formLabel, $options = [])
    {
        $options["dbKey"]     = $sqlServerKey;
        $options["formLabel"] = $formLabel;

        $toReturn = new GTKColumnMapping($dataSource, $phpKey, $options);
        
        return $toReturn;
    }


    public function __construct(
        $dataSource, 
        $phpKey, 
        $options = null
    ){
        parent::__construct($dataSource, $phpKey, $options);
        
        $this->sqlServerKey        = $options['dbKey']            ?? null;
        $this->type                = $options['type']             ?? null;
        $this->formInputType       = $options['formInputType']    ?? null;
        $this->linkTo              = $options['linkTo']           ?? null;
        $this->nonPrimaryLookup    = $options['nonPrimaryLookup'] ?? null;
        
        $this->columnType          = $options['columnType']       ?? null;   
        $this->columnSize          = $options['columnSize']       ?? null;   
        $this->allowNulls          = $options['allowNulls']       ?? null;   
        $this->defaultValue        = $options['defaultValue']     ?? null; 
    }

    ////////////////////////////////////////////////////////////////////
    // DB-Particulars
    // --------
    ////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////

    public function dbColumnName()
    {
        return $this->getSqlColumnName();
    }
    
    public function getSqlColumnName()
    {
        $sqlKey = null;

        if ($this->sqlServerKey)
        {
            $sqlKey = $this->sqlServerKey;
        }
        else
        {
            $sqlKey = $this->phpKey;
        }

        return $sqlKey;
    }

    public function isUpdateKey()
    {
        return $this->isUpdateKey;
    }


    ////////////////////////////////////////////////////////////////////
    // GetValue
    // --------
    ////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////

    public function getValueFromArray($array, $tableName = null)
    {
        $debug = $this->debug ?? false;

        $toReturn = null;

        if ($this->sqlServerKey)
        {
            if (array_key_exists($this->sqlServerKey, $array))
            {
                $toReturn = $this->valueFromSqlServerData($array);
            }
        }
        
        if ($toReturn)
        {
            return $toReturn;
        }
        
        if (array_key_exists($this->phpKey, $array))
        {
            $toReturn = $this->valueFromPHPData($array);
        }
    
        if ($toReturn)
        {
            return $toReturn;
        }



        if (!$tableName)
        {
            $tableName = $this->dataAccessor->tableName();
        }



        if ($tableName)
        {
            $key = $tableName."_".$this->phpKey;
            $keyExists = array_key_exists($key, $array);
            
            if ($debug)
            {
                error_log("Getting value from array for ".$this->phpKey." - ".$tableName);
            }
            
            if ($keyExists)
            {
                $toReturn = $array[$key];
            }
        }

        if ($toReturn)
        {
            return $toReturn;
        }

        return null;
    }

    public function valueForItem($item)
    {
        return $this->getValueFromArray($item);
    }


    public function valueFromDatabase($data) 
    {
        return $this->getValueFromArray($data);
    }

    public function valueFromSqlServerData($data) 
    {
        $debug = $this->debug ?? false;

        $value = null;
        $key   = $this->sqlServerKey;
        
        if (array_key_exists($key, $data))
        {
            $value = $data[$key];
        }
        else
        {
            if ($debug)
            {
                error_log("Key not found: " . $key);
                error_log("Getting value from SQL Server data --(**".$key."**)--: " . serialize($data));
            }
        }

        return $value;
    }

    public function valueFromPHPData($data) 
    {
        if ($this->phpKey)
        {
            
        }
        return $data[$this->phpKey];
    }

    ////////////////////////////////////////////////////////////////////
    // Form Value
    // --------
    ////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////

    public function callCustomInputFunctionForUserItemOptions($user, $item, $options = null)
    {
        $debug = $this->debug ?? false;

        $value = ''; // $this->defaultValue;

        if ($item)
        {
            $value = $this->valueFromDatabase($item);
        }

        $currentValue = $value;
        
        if ($debug)
        {
            error_log("Got `customInputFunction` - "); // ".$this->customInputFunction);
            error_log("GTKColumnMapping Key: ".$this->phpKey);
            error_log("GTKColumnMapping Data Source: ".get_class($this->dataSource));
        }

        $customInputFunction        = $this->customInputFunction;
        $customInputFunctionObject  = $this->customInputFunctionObject;
        $customInputFunctionClass   = $this->customInputFunctionClass;
        $customInputFunctionOptions = $this->customInputFunctionOptions; // $this->customInputFunctionOptions;

        if ($debug)
        {
            error_log("Custom Input Function: ".$customInputFunction);
            error_log("Custom Input Function Object: ");
            error_log("Custom Input Function Class: ".$customInputFunctionClass);
            error_log("Custom Input Function Options: ".serialize($customInputFunctionOptions));
        }

        if ($customInputFunctionObject)
        {
            if ($debug)
            {
                error_log("Got `customInputFunctionObject`");
            }

            if (!method_exists($customInputFunctionObject, $customInputFunction))
            {
                if ($debug)
                {
                    error_log("Does NOT respond. Will call: `customInputFunctionObject`");
                }
                throw new Exception('$customInputFunctionObject - does not respond to $customInputFunction');
            }

            if ($debug)
            {
                error_log("Does respond. Will call: `customInputFunctionObject` of class: ".get_class($customInputFunctionObject));
            }

            $reflectionData = new ReflectionMethod($customInputFunctionObject, $customInputFunction);

            $nParamsForCustomFunction = $reflectionData->getNumberOfParameters();
            
            if ($debug)
            {
                error_log("Will call with $nParamsForCustomFunction - parameters");
            }

            switch ($nParamsForCustomFunction)
            {
                case 1:
                    $arg = new CustomInputFunctionArgument();
                    $arg->columnMapping = $this;
                    $arg->user          = $user;
                    $arg->item          = $item;
                    $arg->options       = $customInputFunctionOptions;

                    return $customInputFunctionObject->$customInputFunction($arg);
                case 5:

                    return $customInputFunctionObject->$customInputFunction(
                        $this,
                        $user,
                        $item,
                        $currentValue, // currentValue
                        $customInputFunctionOptions);
                case 6:
                    return $customInputFunctionObject->$customInputFunction(
                        $user,
                        $this->dataAccessor,
                        $options["identifier"],
                        $this->phpKey,
                        $currentValue,
                        $customInputFunctionOptions
                    );
                    break;
                case 7:
                    return $customInputFunctionObject->$customInputFunction(
                        $this, // $columnMapping,
                        $user,
                        $this->dataAccessor,
                        $objectID,
                        $foreignColumnName,
                        $foreignColumnValue,
                        $customInputFunctionOptions);
                default:
                    throw new Exception("Invalid function for `customInputFunction` protocol");
            }

            

        }
        else if ($customInputFunctionClass)
        {
            if ($debug)
            {
                error_log("Got `customInputFunctionClass`");
            }

            $customInputFunctionScope = $this->customInputFunctionScope;

            switch ($customInputFunctionScope)
            {
                case "class":
                    $reflectionData = new ReflectionMethod($customInputFunctionClass, $customInputFunction);

                    $nParamsForCustomFunction = $reflectionData->getNumberOfParameters();
            
                    if ($debug)
                    {
                        error_log("Will call with $nParamsForCustomFunction - parameters");
                    }
        
                    switch ($nParamsForCustomFunction)
                    {
                        case 1:
                            $arg = new CustomInputFunctionArgument();
                            $arg->columnMapping = $this;
                            $arg->user          = $user;
                            $arg->item          = $item;
                            $arg->options       = $customInputFunctionOptions;
        
                            return $customInputFunctionClass::$customInputFunction($arg);
                        default:
                            if ($debug)
                            {
                                error_log("Will call static function on class...`");
                            }
                        
                            return $customInputFunctionClass::$customInputFunction(
                                $user,
                                $this->dataAccessor,
                                $options["identifier"],
                                $this->phpKey,
                                $value,
                                $customInputFunctionOptions
                            );
                    }
                case "object":
                case "instance":
                case null:
                default:
                    if ($debug)
                    {
                        error_log("Will make new object from `customInputFunctionClass`");
                    }

                    $object = new $customInputFunctionClass();

                    if (!method_exists($object, $customInputFunction))
                    {
                        throw new Exception('$object - does not respond to $customInputFunction');
                    }

                    return $object->$customInputFunction(
                        $user,
                        $this->dataAccessor,
                        $options["identifier"],
                        $this->phpKey,
                        $value,
                        $customInputFunctionOptions);
            
            }

        }
        else if (is_callable($customInputFunction))
        {
            if ($debug)
            {
                error_log("Plain `customInputFunctionClass`");
            }

            $argCount = getFunctionArgumentCount($customInputFunction);

            if ($debug)
            {

            }

            switch ($argCount)
            {
                case 5:
                    $toReturn = $customInputFunction(
                        $this,
                        $user,
                        $item,
                        $value,
                        $options);
                        break;
                case 6:
                    $toReturn = $customInputFunction(
                        $user,
                        $this->dataAccessor,
                        $options["identifier"],
                        $this->phpKey,
                        $value,
                        $customInputFunctionOptions);
                        break;
                case 7:
                    $toReturn = $customInputFunction(
                        $this,
                        $user,
                        $this->dataAccessor,
                        $options["identifier"],
                        $this->phpKey,
                        $value,
                        $customInputFunctionOptions);
                    break;
                default:
                    throw new Exception("INVALID customInputFunction on GTKColumnMapping - ".get_class($this->dataSource)." - ".$this->phpKey." - Arg Count: ".$argCount);
            }

            return $toReturn;
        }
        else
        {
            if ($debug)
            {
                error_log("Custom input function is not callable.");
            }
            throw new Exception("Custom input function is not callable.");
        }
    }

    public function htmlInputForUserItem($user, $item, array $options = null)
    {  
        $debug = $this->debug ?? false;
        $value = ''; // $this->defaultValue;

        if ($debug)
        {
            error_log("HTML Input for User Item - ".$this->phpKey);

        }

        if ($item)
        {
            if ($debug)
            {
                error_log("`htmlInputForUserItem` - Got item...will getSQLServerData - ".$this->phpKey." - Item: ".print_r($item, true));
            }


            $value = $this->valueFromDatabase($item);
        }

        $inputType = $this->formInputType;

        if ($options)
        {
            if (array_key_exists('type', $options))
            {
                $inputType = $options['type'];
            }

            if ($debug)
            {
                error_log("`htmlInputForUserItem` - Options: ".print_r($options, true));
                error_log("`htmlInputForUserItem` - Got input type: ".$inputType);
            }
        }

        $inputType = $inputType ?: "text";
        $phpKey    = $this->phpKey;

        $inputIdentifier = "";

        if ((isset($options["identifier"])) && isset($options["dataSourceName"]))
        {
            $inputIdentifier = $options["dataSourceName"].'-'.$options["identifier"].'-'.$this->phpKey;
            if ($debug)
            {
                error_log("`htmlInputForUserItem` - Got identifier: ".$inputIdentifier);
            }
        }

        if ($this->customInputFunction)
        {
            if ($debug)
            {
                error_log("`htmlInputForUserItem` - Custom Input Function: ".$this->customInputFunction);
            }
            return $this->callCustomInputFunctionForUserItemOptions($user, $item, $options);
        }
        else
        {
            $formInputType = $this->formInputType();

            return $this->generateFormElementForUser(
                $user,
                $formInputType,
                $inputIdentifier,
                $phpKey,
                $value);
        }


    }

    public function generateFormElementForUser(
        $user,
        $inputType, 
        $inputIdentifier, 
        $phpKey, 
        $value
    ){
        $debug = $this->debug ?? false;

        if ($debug)
        {
            error_log("Generate Form Element");
        }
        // Escape user-generated values for use in HTML attributes
        $inputIdentifier = htmlspecialchars($inputIdentifier);
        $phpKey = htmlspecialchars($phpKey);
        $value = htmlspecialchars($value);
    
        $valueAttribute = ' value="' . $value . '" ';
        $idAttribute    = ' id="' . $inputIdentifier . '" ';
        $nameAttribute  = ' name="' . $phpKey . '" ';
    
        switch ($inputType) 
        {
            case "select":
                $element = "<select{$idAttribute}{$nameAttribute}>";
    
                if ($debug)
                 {
                    error_log("SELECT VALUE: " . $value);
                }
    
                foreach ($this->possibleValuesForUser($user) as $optionName => $option) {
                    $element .= '<option value="' . htmlspecialchars($optionName) . '"';
    
                    if ($value && $value === $optionName) {
                        $element .= " selected";
                    }
    
                    $label = $option["label"] ?? $optionName;
    
                    $element .= '>' . htmlspecialchars($label) . '</option>';
                }
    
                $element .= "</select>";
                return $element;
    
            case "textarea":
                $sizeAttribute = ' rows="6" cols="35" ';
                return "<textarea{$idAttribute}{$nameAttribute}{$sizeAttribute}>$value</textarea>";
    
            case "hidden":
                return "<input type=\"hidden\"{$valueAttribute}{$idAttribute}{$nameAttribute}>";
    
            case "submit":
            case "reset":
                return "<input type=\"$inputType\"{$valueAttribute}{$idAttribute}{$nameAttribute}>";
            
            case "button":
                return "<button type=\"button\"{$idAttribute}{$nameAttribute}>$value</button>";
            case "text":
            case "password":
            case "email":
            case "number":
            case "checkbox":
            case "radio":
            case "file":
            case "date":
            case "time":
            case "datetime-local":
            case "color":
            case "range":
            default:
                return "<input type=\"$inputType\"{$valueAttribute}{$idAttribute}{$nameAttribute}>";
        }
    }
    

    // Mark: - Check if exits

    public function doesColumnExist($db, $tableName)
    {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        switch ($driver)
        {
            case "sqlite":
                return $this->doesColumnExistSQLite($db, $tableName);
            case "sqlsrv":
                break;
            case "mysql":
                return $this->doesColumnExistMysql($db, $tableName);
            default:
                return $this->doesColumnExistSqlServer($db, $tableName);
                
        }
    }

    public function doesColumnExistSqlServer($db, $tableName)
    {
        $tableName = $tableName;

        $columnName = $this->getSqlColumnName();

        $sql = "SELECT * FROM information_schema.columns 
                WHERE 
                table_name = '{$tableName}' 
                AND 
                column_name = '{$columnName}'";

        $stmt = $db->prepare($sql);

        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return count($result) > 0;
    }
    public function doesColumnExistMysql($db, $tableName)
    {

        $columnName = $this->getSqlColumnName();

        $sql = "SELECT * FROM information_schema.columns 
                WHERE 
                table_name = '{$tableName}' 
                AND 
                column_name = '{$columnName}'";

        $stmt = $db->prepare($sql);

        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return count($result) > 0;
    }

    function doesColumnExistSQLite($db, $tableName)
    {
        $debug = $this->debug ?? false;


        $sql = "PRAGMA table_info(".$tableName.")";

        if ($debug)
        {
            error_log("SQL: {$sql}");
        }   

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $columnName = $this->getSqlColumnName();

        if ($debug)
        {
            error_log("`doesColumnExistSQLite` — Looking for... $columnName");
        }
        

        foreach ($columns as $column) 
        {
            $isMatch = ($column['name'] == $columnName);
            
            if ($debug)
            {
                error_log("`doesColumnExistSQLite` ($isMatch) checking on ".$column["name"]);
            }

            if ($isMatch)
            {
                return true;
            }
        }
        return false;
    }


    // Mark: - Create Columns
    public static function columnTypeForSqlite($columnType)
    {

        $sqlColumnTypes = [
            'NUMERIC'           => 'NUMERIC',
            // Integer Types
            'TINYINT'           => 'INTEGER',        // A very small integer
            'SMALLINT'          => 'INTEGER',        // A small integer
            'MEDIUMINT'         => 'INTEGER',        // A medium-sized integer
            'INT'               => 'INTEGER',        // A standard integer
            'INTEGER'           => 'INTEGER',        // A synonym for INT
            'BIGINT'            => 'INTEGER',        // A large integer
            // Decimal Types
            'DECIMAL'            => 'REAL',    // A fixed-point number
            'NUMERIC'            => 'REAL',    // An exact numeric value with a fixed precision and scale
            'FLOAT'              => 'REAL',      // A floating-point number
            'DOUBLE'             => 'REAL',     // A double precision floating-point number
            'REAL'               => 'REAL',       // A synonym for DOUBLE (except in SQL Server where it's a synonym for FLOAT)
            // Boolean Type
            'BOOLEAN'            => 'REAL',    // A boolean value (true or false)
            // Date and Time Types
            'DATE'               => 'REAL',    // A date value
            'DATETIME'           => 'REAL',    // A date and time combination
            'TIMESTAMP'          => 'TEXT',    // A timestamp value
            'TIME'               => 'REAL',    // A time value
            'YEAR'               => 'REAL',    // A year value
            // String Types
            'CHAR'               => 'TEXT',       // A fixed-length non-binary string (up to 255 characters)
            'VARCHAR'            => 'TEXT',    // A variable-length non-binary string
            'TINYTEXT'           => 'TEXT',   // A very small non-binary string
            'TEXT'               => 'TEXT',       // A standard non-binary string
            'MEDIUMTEXT'         => 'TEXT', // A medium-length non-binary string
            'LONGTEXT'           => 'TEXT',   // A large non-binary string
            // Binary String Types
            'BINARY'               => 'BLOB',     // A fixed-length binary string
            'VARBINARY'            => 'BLOB',  // A variable-length binary string
            'TINYBLOB'             => 'BLOB',   // A very small binary string
            'BLOB'                 => 'BLOB',       // A binary string
            'MEDIUMBLOB'           => 'BLOB', // A medium-length binary string
            'LONGBLOB'             => 'BLOB',   // A large binary string
            // Enumerated Types
            'ENUM',       // An enumeration
            'SET',        // A set of enumeration values
            // JSON Type
            'JSON',       // A JSON-formatted string
            // UUID Type
            'UUID'        // A universally unique identifier
        ];


        if (array_key_exists($columnType, $sqlColumnTypes))
        {
            return $sqlColumnTypes[$columnType];
        }
        else
        {
            throw new Exception("Invalid column type for SQLite: ".$columnType);
        }
    }
    
    public function getColumnTypeForDriverName($driver = null)
    {
        $debug = $this->debug ?? false;

        if ($this->isAutoIncrement())
        {
            if ($debug)
            {
                error_log("Returning `autoincrement` type.");
            }

            switch ($driver)
            {
                case "sqlite":
                    return "INTEGER";
                case "sqlsrv":
                default:
                    return "INT";
            }
        }

        if (!$this->columnType)
        {
            switch ($driver)
            {
                case "sqlite":
                    return '';
                default:
                    return "VARCHAR";
            }
        }

        $sqlColumnTypes = [
            // Integer Types
            'TINYINT',    // A very small integer
            'SMALLINT',   // A small integer
            'MEDIUMINT',  // A medium-sized integer
            'INT',        // A standard integer
            'INTEGER',    // A synonym for INT
            'BIGINT',     // A large integer
        
            // Decimal Types
            'DECIMAL',    // A fixed-point number
            'NUMERIC',    // An exact numeric value with a fixed precision and scale
            'FLOAT',      // A floating-point number
            'DOUBLE',     // A double precision floating-point number
            'REAL',       // A synonym for DOUBLE (except in SQL Server where it's a synonym for FLOAT)
        
            // Boolean Type
            'BOOLEAN',    // A boolean value (true or false)
        
            // Date and Time Types
            'DATE',       // A date value
            'DATETIME',   // A date and time combination
            'TIMESTAMP',  // A timestamp value
            'TIME',       // A time value
            'YEAR',       // A year value
        
            // String Types
            'CHAR',       // A fixed-length non-binary string (up to 255 characters)
            'VARCHAR',    // A variable-length non-binary string
            'TINYTEXT',   // A very small non-binary string
            'TEXT',       // A standard non-binary string
            'MEDIUMTEXT', // A medium-length non-binary string
            'LONGTEXT',   // A large non-binary string
        
            // Binary String Types
            'BINARY',     // A fixed-length binary string
            'VARBINARY',  // A variable-length binary string
            'TINYBLOB',   // A very small binary string
            'BLOB',       // A binary string
            'MEDIUMBLOB', // A medium-length binary string
            'LONGBLOB',   // A large binary string
        
            // Enumerated Types
            'ENUM',       // An enumeration
            'SET',        // A set of enumeration values
        
            // JSON Type
            'JSON',       // A JSON-formatted string
        
            // UUID Type
            'UUID'        // A universally unique identifier
        ];


        switch ($driver)
        {
            case "sqlite":
                return static::columnTypeForSqlite($this->columnType);
                // return '';
            default:
                if (in_array(strtoupper($this->columnType), $sqlColumnTypes))
                {
                    return $this->columnType;
                }
                break;
        }

        throw new Exception("INVALID COLUMN TYPE for $this->phpKey");
    }

    public function autoIncrementSyntaxForDriverName($driver)
    {
        if (!$this->isAutoIncrement)
        {
            return '';
        }

        $columnDef = null;

        switch ($driver)
        {
            case 'mysql':
                $columnDef .= "AUTO_INCREMENT ";
                break;
            case 'pgsql':
                throw new Exception("MUST ADAPT TO `SERIAL` TYPE FOR PostgresSQL");
                break;
            case 'sqlsrv':
                $columnDef .= "IDENTITY(1,1) ";
                break;
            case 'sqlite':
                $columnDef .= "AUTOINCREMENT ";
                break;
        }

        if (!$columnDef)
        {
            throw new Exception("No AUTO-INCREMENT syntax for PDO - $driver");
        }

        return $columnDef;

        
    }

    public function getPrimaryKeySyntaxForDriverName($driverName)
    {
        if ($this->isPrimaryKey())
        {
            return "PRIMARY KEY";
        }
        else
        {
            return '';
        }
    }

    public function getColumnSizeStatementForDriverName($driverName)
    {
        $columnType = $this->getColumnTypeForDriverName($driverName);

        $shouldUseColumnSize = in_array(strtoupper($columnType), ['NVARCHAR', 'VARCHAR', 'CHAR']);
        
        if ($shouldUseColumnSize || $this->columnSize) 
        {
            $columnSize = $this->columnSize ?? "255";
            return "($columnSize)";
        }
        else
        {
            return '';
        }
    }

    public function getCreateSQLForPDO($pdo = null)
    {
        $driverName = null;

        if ($pdo)
        {
            $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        }

        return $this->getCreateSQLForDriverName($driverName);
    }



    public function getCreateSQLForDriverName($driverName)
    {
        $columnName = $this->getSqlColumnName();

        // ." ".$this->getColumnTypeForDriverName($driverName).$this->getColumnSizeStatementForDriverName($driverName)." ".$this->getPrimaryKeySyntaxForDriverName($driverName)." ".$this->autoIncrementSyntaxForDriverName($driverName);
    
        $toReturn = $columnName;

       // $columnType = $this->getColumnTypeForDriverName($driverName);

        if($driverName == 'mysql')
        {
            
            $sql = $columnName." ";
            //TODO hacer funcionar el metodo getColumnTypeForDriverName
            //$type= $this->$this->getColumnTypeForDriverName($driverName);
            $type = $this->type;
            if(!$type)
            {
                throw new Exception("La columna: ".$this->phpKey." no tiene un valor de tipo de dato");
            }
            $sql .= $type." ";

            if(!$this->isNullable())
            {
                $sql .= "NOT NULL "; 
            }
            

            if($this->isAutoIncrement())
            {
                $sql .= "AUTO_INCREMENT ";
            }

            if($this->isPrimaryKey())
            {
                $sql.= "PRIMARY KEY";
            }


            return $sql;
        }

        if ($driverName == 'pgsql' && $this->isAutoIncrement()) 
        {
            // SERIAL:    Uses 4-byte integer, values from 1 to ------------2,147,483,647
            // BIGSERIAL: Uses 8-byte integer, values from 1 to 9,223,372,036,854,775,807
            // # of records to fill up DB in 10 years: 588,352 records per day
            // Consider BIG SERIAL FOR...
            // - IoT: device statuses, telemetry data
            // - Social Media: posts, likes, comments
            // - Financial: transactions, audit logs
            // - E-commerce: orders, customer activity
            // - Telecom: call data, usage records
            // - Streaming: viewer interactions, logs
            // - Healthcare: patient records, monitoring
            if ($columnType == "BIGSERIAL")
            {
                return $columnName.' '.'BIGSERIAL';
            }
            else
            {
                return $columnName.' '.'SERIAL';
            }

        } 

        if ($columnType != '')
        {
            $toReturn .= ' '.$columnType;
        }

        $columnSizeStatement = $this->getColumnSizeStatementForDriverName($driverName);

        if ($columnSizeStatement != '')
        {
            $toReturn .= ' '.$columnSizeStatement;
        }

        $primaryKeySyntax = $this->getPrimaryKeySyntaxForDriverName($driverName);

        if ($primaryKeySyntax != '')
        {
            $toReturn .= ' '.$primaryKeySyntax;
        }

        $autoIncrementSyntax = $this->autoIncrementSyntaxForDriverName($driverName);

        if ($autoIncrementSyntax != '')
        {
            $toReturn .= ' '.$autoIncrementSyntax;
        }

        $isNullable = $this->isNullable();

        if ($isNullable)
        {
            $toReturn .= ' NULL';
        }
        else
        {
            $toReturn .= ' NOT NULL';
        }

        return $toReturn;
    
    }

    // Mark: - Add

    public function addColumnIfNotExists($db, $tableName)
    {
        $driverName = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        $columnName = $this->getSqlColumnName();

        switch ($driverName)
        {
            case 'sqlite':
                $this->addColumnIfNotExistsSQLite($db, $tableName, $columnName);
                break;
            
            case 'sqlsrv':
                $this->addColumnIfNotExistsSQLServer($db, $tableName);
                break;
            
            case 'mysql':
                $this->addColumnIfNotExistsMySQL($db, $tableName);
                break;
            case 'pgsql':
            case 'oci': // Oracle
            default:
                error_log("`addColumnIfNotExists` - Connected to a database with unsupported driver: " . $driverName);
                die();
        }


    }

    function addColumnIfNotExistsSQLServer($db, $tableName)
    {
        $columnName = $this->getSqlColumnName();

        if (!$this->doesColumnExistSqlServer($db, $tableName))
        {
            $columnType   = $this->columnType   ?? 'nvarchar';
            $columnSize   = $this->columnSize   ?? 255;
            $allowNulls   = $this->allowNulls   ?? true;
            $defaultValue = $this->defaultValue ?? null;
            $constraint   = null;
    
            // Set data type with size if specified
            $sqlColumnType = ($columnSize) ? "{$columnType}({$columnSize})" : $columnType;
    
            // Check if the: column should allow nulls
            $sqlAllowNulls = ($allowNulls) ? "NULL" : "NOT NULL";
    
            // Construct the main ALTER TABLE statement
            $sql = "ALTER TABLE {$tableName} ADD {$columnName} {$sqlColumnType} {$sqlAllowNulls}";

            if ($constraint)
            {
                // 
            }
    
            // If there's a default value, append it to the SQL statement
            if ($defaultValue) {
                $sql .= " DEFAULT '{$defaultValue}'";
            }
    
            $stmt = $db->prepare($sql);
            $stmt->execute();
        }
    }
    function addColumnIfNotExistsMySQL($db, $tableName)
    {
        $columnName = $this->getSqlColumnName();

        if (!$this->doesColumnExistMysql($db, $tableName))
        {
            $type   =  $this->type   ?? 'nvarchar';
            $allowNulls   = $this->allowNulls   ?? true;
            $primaryKey = $this->_isPrimaryKey  ?? false;
            $autoIncrement = $this->isAutoIncrement ?? false;
            
    
            
            
            // Construct the main ALTER TABLE statement
            $sql = "ALTER TABLE ".$tableName. " ADD ";
            $sql .= $columnName." ";
            $sql .= $type." ";

            if(!$allowNulls)
            {
                $sql .= "NOT NULL "; 
            }

            if($autoIncrement)
            {
                $sql .= "AUTO_INCREMENT ";
            }

            if($primaryKey)
            {
                $sql.= "PRIMARY KEY";
            }



            
    
            $stmt = $db->prepare($sql);
            $stmt->execute();
        }
    }


    function addColumnIfNotExistsSQLite($db, $tableName)
    {
        $columnExists = $this->doesColumnExistSQLite($db, $tableName);

        if (!$columnExists)
        {
            $columnName = $this->getSqlColumnName();
            $sql = "ALTER TABLE ".$tableName." ADD COLUMN $columnName"; //  $columnType";
            $db->exec($sql);
        }
    }

    public function getMaxLength($options = null)
    {
        $sqlColumn = $this->getSqlColumnName();

        $sqlV1 = "";
        $sqlV1 .= "SELECT CHARACTER_MAXIMUM_LENGTH";
        $sqlV1 .= " FROM INFORMATION_SCHEMA.COLUMNS";
        $sqlV1 .= " WHERE TABLE_NAME = '".$this->dataSource->tableName()."'";
        $sqlV1 .= " AND COLUMN_NAME = '".$sqlColumn."'";

        /*
        SELECT max_length
        FROM sys.columns
        WHERE object_id = OBJECT_ID('yourTable')
        AND name = 'yourColumn';
        */

        try
        {
            $stmt = $this->dataSource->getPDO()->prepare($sqlV1);
            $stmt->execute(); 
        }
        catch (Exception $e)
        {
            $exCode    = $e->getCode();
            $exMessage = $e->getMessage();

            $errorMessage = "`getMaxLengthForColumnMapping` Got exception with code (".$exCode.") - ".$exMessage;
            $errorMessage .= "\n\nSQL: $sqlV1";

            gtk_log($errorMessage);

            throw $e;
        }

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $columnLength = $result['CHARACTER_MAXIMUM_LENGTH'];

        return $columnLength;
    }

    public function bindValueToStatementForItem($statement, $item)
    {
        $debug = $this->debug ?? false;

        if ($this->isAutoIncrement())
        {
            if ($debug)
            {
                gtk_log("`bindValueToStatementForItem`:".$this->phpKey." isAutoIncrement — skipping.");
            }
            return;
        }
        
        if ($this->isPrimaryKey())
        {
            if ($debug)
            {
                gtk_log("`bindValueToStatementForItem`:".$this->phpKey." isPrimaryKey — skipping.");
            }
            return;
        }

        if ($debug)
        {
            gtk_log("`bindValueToStatementForItem`:".$this->phpKey." binding value: ".$this->valueFromDatabase($item));
        }

        $statement->bindValue(":".$this->phpKey, $this->valueFromDatabase($item));
    }


}
