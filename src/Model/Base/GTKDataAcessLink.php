<?php


class GTKDataAccessLink
{
	public $url;
	public $permission;
    public $canEchoResult;
	public $dataSource;
    public $key;
	public $label;
    public $allowedFor;

	public $hideOnListsForUserItemObject; 
    public $hideOnListsForUserItemFunction;
	public $hideOnEditForUserItemObject;
    public $hideOnEditForUserItemFunction;

	public $isInvalidForUserItemDelegateOptionsObject;
    public $isInvalidForUserItemDelegateOptionsFunction;

    public function __construct($dataSource, $permission, $url, $options)
    {
		$this->url = $url;
        $this->dataSource = $dataSource;
		$this->permission = $permission;	
        $this->key        = $url;

		
		if (isset($options["label"]))
		{
			$this->label = $options["label"];
		}

		if (isset($options["isInvalidForUserItemDelegateOptionsObject"]))
		{
			$this->isInvalidForUserItemDelegateOptionsObject = $options["isInvalidForUserItemDelegateOptionsObject"];
		}

		if (isset($options["isInvalidForUserItemDelegateOptionsFunction"]))
		{
			$this->isInvalidForUserItemDelegateOptionsFunction = $options["isInvalidForUserItemDelegateOptionsFunction"];
		}
    }

	public function labelForUserItem($user, $item)
	{
		$label = $this->label;
		if (is_callable($label))
		{
			return $label($user, $item);
		}
		else if ($label)
		{
			return $label;
		}
		else
		{
			return $this->permission;
		}
	}

    public function linkForItem($item, $options = [])
    {
        $defaultQueryArguments = [
            "actionName"     => $this->key,
            "dataSourceName" => $this->dataSource->dataAccessorName,
            "identifier"     => $this->dataSource->valueForIdentifier($item),
        ];

        $queryArguments = array_merge($defaultQueryArguments, $options);

        return $this->url.'?'.http_build_query($queryArguments);
    }

	
    public function anchorLinkForItem($user, $item, $options = null)
    {
        $label = $options["label"] ?? $this->label;

		if (is_callable($label))
		{
			$label = $label($user, $item);
		}
        
        $toReturn  = "";
        $toReturn .= "<a class='button' href=\"{$this->linkForItem($item)}\">{$label}</a>";
        $toReturn .= "<br/>";
        $toReturn .= "<br/>";

        return $toReturn;
    }


	//--------------------------------------------------------------------------
	//--------------------------------------------------------------------------
	//--------------------------------------------------------------------------
	
	public function hideOnListsForUserItem($user, $item)
	{
		$debug = false;
		
		if ($this->hideOnListsForUserItemObject)
        {
			$hideOnListsForUserItemFunction = $this->hideOnListsForUserItemFunction;
			return $this->hideOnListsForUserItemObject->$hideOnListsForUserItemFunction($user, $item);
        }
        else if ($this->hideOnListsForUserItemFunction)
		{
			$hideOnListsForUserItemFunction = $this->hideOnListsForUserItemFunction;
			return $hideOnListsForUserItemFunction($user, $item);
		}
    
        return $this->isInvalidForUserOnItem($user, $item);
	}

	public function hideOnEditForUserItem($user, $item)
	{
		$debug = false; 

        if ($this->hideOnEditForUserItemObject)
        {
			if ($debug)
			{
				error_log("hideOnEditForUserItem - hasCustomIsInvalidLogic");
			}
			$hideOnEditForUserItemFunction = $this->hideOnEditForUserItemFunction;
			return $this->hideOnEditForUserItemObject->$hideOnEditForUserItemFunction($user, $item);
        }
        else if ($this->hideOnListsForUserItemFunction)
		{
			$hideOnEditForUserItemFunction = $this->hideOnEditForUserItemFunction;
			return $hideOnEditForUserItemFunction($user, $item);
		}

        return $this->isInvalidForUserOnItem($user, $item);
	}


	public function isInvalidForUserOnItem(&$user, &$item, &$delegate = null, &$options = null)
    {
		$debug = false;

		$hasPermission = DataAccessManager::get('persona')->hasPermission($this->permission, $user);
    
		if (!$hasPermission)
		{
			if ($debug)
			{
				error_log("isInvalidForUserOnItem - no permission to: ".$this->permission);
			}
			return true;
		}

		if ($this->hasCustomIsInvalidLogic())
		{
			$isInvalidForUserItemDelegateOptionsFunction = $this->isInvalidForUserItemDelegateOptionsFunction;

			if ($this->isInvalidForUserItemDelegateOptionsObject)
			{
				return $this->isInvalidForUserItemDelegateOptionsObject->$isInvalidForUserItemDelegateOptionsFunction($user, $item, $delegate, $options);
			}
			else if (is_callable($isInvalidForUserItemDelegateOptionsFunction))
			{
				return $isInvalidForUserItemDelegateOptionsFunction($user, $item, $delegate, $options);
			}
			else
			{
				$message = "No callable action defined for this action: ".$isInvalidForUserItemDelegateOptionsFunction." on ".get_class($this->dataSource)." for ".$this->permission;
				throw new Exception($message);
			}
		}

		return false;
	}

	public function hasCustomIsInvalidLogic()
    {
        if ($this->isInvalidForUserItemDelegateOptionsFunction)
        {
            return true;
        }
        return false;
    }
}
