<?php

class GTKColumnMappingListDisplayArgument
{
    public $dataSource;
    public $user;
    public $item;
    public $itemIdentifier;
    public $options;

    public function __construct($dataSource = null, $user = null, $item = null, $itemIdentifier = null, $options = null)
    {
        $this->dataSource     = $dataSource;
        $this->user           = $user;
        $this->item           = $item;
        $this->itemIdentifier = $itemIdentifier;
        $this->options        = $options;
    }

}

class GTKColumnBase 
{
    public $debug = false;
    public $dataSource;
    public $phpKey;
    public $formLabel;
    public $dataAccessor;

    public $customInputFunction; //  objectID, columnName columnValue (?data access type??)
    public $customInputFunctionObject;
    public $customInputFunctionClass;
    public $customInputFunctionScope;
    public $customInputFunctionOptions;

    public $_listDisplayForDataSourceUserItem;

    public $formNewProcessFunction;
    public $formEditProcessFunction;

    // Options
    public $linkTo;
    public $type;
    public $formInputType;

    public $process;

    public $assignTo;
    public $required;
    public $display;

    public $_isAutoIncrement;
    public $_showOnSearch;
    public $_hideOnLists;
    public $_hideOnShow;
    public $_hideOnSearch;
    public $_hideOnForms;
    public $_hideOnNewForUser;
    public $_hideOnEditForUser;
    public $_removeOnForms;
    public $_hideOnInsert;
    public $_hideOnUpdate;
    public $_isNullable;
    public $_isPrimaryKey;
    public $_isUnique;
    public $_isInvalid;
    public $_groups;
    public $_processOnInsert;
    public $_processOnAll;
    public $_isSearchable;
    public $_onlyDisplayOnForms;
    public $_possibleValues;
    public $_formInputType;
    public $transformValueOnLists;
    public $hideOnListsForUserFunction;

    public function isInsertable()
    {
        return false;
    }

    public function isSearchable()
    {
        if ($this->_showOnSearch)
        {
            return true;
        }

        if ($this->_hideOnLists)
        {
            return false;
        }

        return true;
    }

    public function getLabel($dataAccess = null)
    {
        if ($this->formLabel)
        {
            return $this->getFormLabel($dataAccess);
        }

        return $this->phpKey;
    }

    public function doesItemContainOurKey($item)
    {
        return isset($item[$this->phpKey]);
    }

    public function __construct($dataSource, $phpKey, $options = [])
    {
        $debug = false;

        $this->dataSource = $dataSource;
        $this->phpKey     = trim($phpKey);
        
        if (isset($options['formLabel']))
        {
            $this->formLabel = $options['formLabel'];
        }
        else
        {
            $array = explode("_", $phpKey);
            
            $new = [];

            foreach ($array as $value)
            {
                if ($value == "id")
                {
                    $new[] = "ID";
                }
                else
                {
                    $new[] = ucfirst($value);
                }
                
            }

            $this->formLabel = implode(" ", $new);
        }

        $this->_isNullable  = $options["isNullable"] ?? true;
        $this->_isSearchable = $options["isSearchable"] ?? true;

        $hideOnLists = false;

        if (isset($options["hideOnLists"]))
        {
            $hideOnLists = $options["hideOnLists"];
        }

        if (isset($options["showOnLists"]))
        {
            $hideOnLists = !$options["showOnLists"];
        }

        if ($hideOnLists)
        {
            $this->_hideOnLists = true;
            $this->_hideOnSearch = true;
        }
        else
        {
            $this->_hideOnSearch = $options["hideOnSearch"] ?? false;
        }

        if ($debug)
        {
            error_log("Column: ".get_class($this->dataSource)."//".$this->phpKey." - Hide on lists: ".$this->_hideOnLists);

        }
    
        $this->type                       = $options["type"]                          ?? false;
        $this->_showOnSearch              = $options["showOnSearch"]                  ?? null;
        $this->_hideOnLists               = $options["hideOnLists"]                   ?? false;
        $this->_hideOnShow                = $options["hideOnShow"]                    ?? false;
        $this->_hideOnForms               = $options["hideOnForms"]                   ?? false;
        $this->_hideOnNewForUser          = $options["hideOnNewForUser"]              ?? false;
        $this->_hideOnEditForUser         = $options["hideOnEditForUser"]             ?? false;
        $this->_hideOnInsert              = $options["hideOnInsert"]                  ?? false;
        $this->_hideOnUpdate              = $options["hideOnUpdate"]                  ?? false;
        $this->type                       = $options["type"]                          ?? false;
        $this->process                    = $options["process"]                       ?? false;
        $this->assignTo                   = $options["assignTo"]                      ?? false;
        $this->required                   = $options["required"]                      ?? false;
        $this->display                    = $options["display"]                       ?? false; 
        $this->_isPrimaryKey              = $options["isPrimaryKey"]                  ?? false;
        $this->_isUnique                  = $options["isUnique"]                      ?? false;
        $this->_isAutoIncrement           = $options["isAutoIncrement"]               ?? false;
        $this->_isInvalid                 = $options["isInvalid"]                     ?? false;
        $this->customInputFunction        = $options['customInputFunction']           ?? false;
        $this->customInputFunctionClass   = $options['customInputFunctionClass']      ?? false;
        $this->customInputFunctionScope   = $options['customInputFunctionScope']      ?? false;
        $this->customInputFunctionObject  = $options['customInputFunctionObject']     ?? false;
        $this->customInputFunctionOptions = $options['customInputFunctionOptions']    ?? false;
        $this->formNewProcessFunction     = $options['formNewProcessFunction']        ?? false;
        $this->formEditProcessFunction    = $options['formEditProcessFunction']       ?? false;
        $this->_processOnInsert           = $options['processOnInsert']               ?? false;
        $this->_processOnAll              = $options['processOnAll']                  ?? false;
        $this->_onlyDisplayOnForms        = $options['onlyDisplayOnForms']            ?? false;
        $this->_removeOnForms             = $options['removeOnForms']                 ?? false;
        $this->_possibleValues            = $options['possibleValues']                ?? false;
        $this->_formInputType             = $options['formInputType']                 ?? false;
        $this->transformValueOnLists      = $options['transformValueOnLists']         ?? false;
        $this->hideOnListsForUserFunction = $options['hideOnListsForUserFunction']    ?? false;
        $this->linkTo                     = $options['linkTo']                        ?? false;
        $this->debug                      = $options['debug']                         ?? false;
        $this->_groups                    = $options['groups']                        ?? false;   
        $this->_listDisplayForDataSourceUserItem = $options['listDisplayForDataSourceUserItem'] ?? null;
    }

    public function isPartOfGroupAndUser($groupName, $user)
    {
        if ($groupName === "all")
        {
            return true;
        }

        if ($groupName === "searchable")
        {
            return $this->_isSearchable;
        }   

        if ($groupName === "lists")
        {
            return $this->hideOnListsForUser($user);
        }

        if ($groupName === "forms")
        {
            return $this->hideOnFormsForUser($user);
        }

        return in_array($groupName, $this->_groups);
    }

    public function setDataAccessor($dataAccessor)
    {
        $this->dataAccessor = $dataAccessor;
    }


    public function hideOnListsForUser($user)
    {
        /*
        if ($this->phpKey === "password")
        {
            error_log("Hiding on lists: " . $this->_hideOnLists);
        }
        */
        
        return isset($this->_hideOnLists) ? $this->_hideOnLists : false;
    }

    public function isNullable()
    {
        return isset($this->_isNullable) ? $this->_isNullable : false;
    }

    public function isPrimaryKey()
    {
        return isset($this->_isPrimaryKey) ? $this->_isPrimaryKey : false;
    }

    public function isUnique()
    {
        if ($this->isPrimaryKey())
        {
            return true;
        }

        if ($this->isAutoIncrement())
        {
            return true;
        }
        
        return isset($this->_isUnique) ? $this->_isUnique : false;
    }

    public function isAutoIncrement()
    {
        $debug = false;

        if (isset($this->_isAutoIncrement))
        {
            if ($debug)
            {
                error_log("Auto Increment: `$this->_isAutoIncrement");
            }
            return $this->_isAutoIncrement;
        }
        else
        {
            return false;
        } 
    }


    public function isVirtual()
    {
        return false;
    }

    // MARK: Hiders
    
    public function hideOnShow()
    {
        return isset($this->_hideOnShow) ? $this->_hideOnShow : false;
    }

    public function hideOnSearch()
    {
        return isset($this->_hideOnSearch) ? $this->_hideOnSearch : false;
    }

    public function hideOnNewForUser($user)
    {
        if (!$this->_hideOnNewForUser)
        {
            return $this->hideOnFormsForUser($user);
        }

        $hideOnNewForUser = $this->_hideOnNewForUser; 

        if (is_callable($hideOnNewForUser))
        {
            return $hideOnNewForUser($user);
        }

        if (is_bool($hideOnNewForUser))
        {
            return $hideOnNewForUser;
        }

        return $hideOnNewForUser;
    }

    public function hideOnEditForUser($user)
    {
        if (!$this->_hideOnEditForUser)
        {
            return $this->hideOnFormsForUser($user);
        }

        $hideOnEditForUser = $this->_hideOnEditForUser; 

        if (is_callable($hideOnEditForUser))
        {
            return $hideOnEditForUser($user);
        }

        if (is_bool($hideOnEditForUser))
        {
            return $hideOnEditForUser;
        }

        return $hideOnEditForUser;
    }

    public function hideOnFormsForUser($user)
    {
        return isset($this->_hideOnForms) ? $this->_hideOnForms : false;
    }

    public function removeOnFormsForUser($user)
    {
        return isset($this->_removeOnForms) ? $this->_removeOnForms : false;
    }

    public function hideOnInsertForUser($user)
    {
        return isset($this->_hideOnInsert) ? $this->_hideOnInsert : false;
    }

    public function hideOnUpdateForUser($user)
    {
        return isset($this->_hideOnUpdate) ? $this->_hideOnUpdate : false;
    }

    public function isRequiredForUser($user)
    {
        return isset($this->required) ? $this->required : false;
    }

    
    public function processOnInsertForUser($user)
    {
        return isset($this->_processOnInsert) ? $this->_processOnInsert : false;
    }

    public function processOnAllForUser($user)
    {
        return isset($this->_processOnAll) ? $this->_processOnAll : false;
    }

    public function onlyDisplayOnFormsForUser($user)
    {
        return isset($this->_onlyDisplayOnForms) ? $this->_onlyDisplayOnForms : false;
    }

    public function possibleValuesForUser($user)
    {
        if (!isTruthy($this->_possibleValues))
        {
            return null;
        }
        
        return $this->_possibleValues;
    }

    public function getFormLabel($dataSource = null)
    {
        if (!$dataSource)
        {
            $dataSource = $this->dataSource;
        }

        $currentClass = get_class($dataSource);

        while ($currentClass) 
        {
            $translationKey = $currentClass . "/" . $this->phpKey;
            $translation = Glang::get($translationKey, ["allowReturnOfNull" => true]);
    
            if ($translation) 
            {
                return $translation;
            }
    
            $currentClass = get_parent_class($currentClass);
        }
    
        return snakeToSpaceCase($this->phpKey);
    }

    public function formInputType()
    {
        return isset($this->_formInputType) ? $this->_formInputType : false;
    }


    public function listDisplayForDataSourceUserItem($dataSource, $user, $item, $itemIdentifier, $options = null)
    {
        $debug = false; // $this->debug ?? false; 

        if ($this->_listDisplayForDataSourceUserItem)
        {
            if (is_callable($this->_listDisplayForDataSourceUserItem))
            {
                $toCall = $this->_listDisplayForDataSourceUserItem;

                $reflection = new ReflectionFunction($toCall);
                $numberOfParameters = $reflection->getNumberOfParameters();

                //die("Number of parameters: ".$numberOfParameters);

                if ($numberOfParameters == 1)
                {
                    $argument = new GTKColumnMappingListDisplayArgument(
                        $dataSource, 
                        $user, 
                        $item, 
                        $itemIdentifier,
                        $options);

                    return call_user_func($toCall, $argument);
                }         
                else
                {
                    return $toCall($dataSource, $user, $item, $itemIdentifier, $options);
                }       
            }
            else
            {
                throw new Exception("Invalid `_listDisplayForDataSourceUserItem` for ".get_class($this)." `_listDisplayForDataSourceUserItem`: ".print_r($this->_listDisplayForDataSourceUserItem, true));
            }        }

        if ($debug)
        {
            error_log("GTKColumnMapping->listDispaly --- ".$this->phpKey);
        }

        $toReturn     = "";
        
        $value = $this->valueForItem($item);

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

            $link = $dataSource->mostAdvancedLinkForUserItem($user, $item);

            if ($link)
            {
                return '<td>'.$link.'</td>';
            }
            else
            {
                return '<td>'.$value.'</td>';
            }
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

}


class GTKItemCellContentPresenter
{
    public $label;
    public $presenterFunction;

    public function __construct($label, $presenterFunction)
    {
        $this->label = $label;
        $this->presenterFunction = $presenterFunction;
    }

    
    public function getColumnName()
    {
        return $this->label;
    }

    public function getFormLabel()
    {
        return $this->label;
    }

    public function valueFromDatabase($item)
    {
        $presenterFunction = $this->presenterFunction;

        return $presenterFunction($item);
    }
}
