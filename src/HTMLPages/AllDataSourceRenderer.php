<?php

use function Deployer\error;

class AllDataSourceRenderer extends GTKHTMLPage
{
    public $dataSource;
    public $user;


	public $header;
	public $footer;

    public $primaryKeyMapping;
	public $columnsToDisplay;
	public $filters;
	public $itemActions;
	public $searchableColumns;
	public $page;
	public $offset;
	public $queryOptions = [];
	public $_count;
	public $_items;
	public $itemsPerPage;
    public $echoSelectedIfTrue;

    public function renderForDataSource(/* DataAccessInterface */ $dataSource, $user, $options)
    {
        $this->dataSource = $dataSource;
        $this->user 	  = $user;
		$this->header = $options["header"] ?? null;
		$this->footer = $options["footer"] ?? null;
        return $this;
    }

	public function getNewHref()
	{
		$editHref = "edit";

		$requestUri = $_SERVER['REQUEST_URI'];
		$uriWithoutQueryString = parse_url($requestUri, PHP_URL_PATH);
		
		if (stringEndsWith(".php", $uriWithoutQueryString))
		{
			$editHref = "edit.php";
		}

	   return $editHref."?isNew=true";
	}

	public function count()
	{
		if ($this->_count != null)
		{
			return $this->_count;
		}
		else
		{
			return $this->queryObject()->count();
		}
	} 
	

	public function getItems()
	{
		if ($this->_items)
		{
			return $this->_items;
		}
		else
		{
			return $this->queryObject()->executeAndYield();
		}
	
	}

	public function queryObject()
	{
		$debug = false;

		if ($debug)
		{
			error_log("Querying for user: ".print_r($this->user, true));
			error_log("Offset: ".$this->offset);
			error_log("Items per page: ".$this->itemsPerPage);
			error_log("Query options: ".print_r($this->queryOptions, true));
		}

		$options = $this->queryOptions;

		if (!isset($options['limit']))
		{
			$options['limit'] = $this->itemsPerPage;
		}

		if (!isset($options['offset']))
		{
			$options['offset'] = $this->offset;
		}	

		$queryObject = $this->dataSource->selectQueryObjectFromOffsetForUser(
			$this->user,
			$this->offset, 
			$this->itemsPerPage, 
			$this->queryOptions);

		$sqlQueryParamsSource = $this->get;

		if (isset($sqlQueryParamsSource["search"]) && isset($sqlQueryParamsSource["columnToSearch"]))
		{
			$search 		= $sqlQueryParamsSource["search"];
			$columnToSearch = $sqlQueryParamsSource["columnToSearch"];

			if ($debug)
			{
				gtk_log("`queryObject` - Search parameters: $columnToSearch :: $search");
			}

			$queryObject->where($columnToSearch, "LIKE", "%$search%");
		}
		else
		{
			if ($debug)
			{
				gtk_log("`queryObject` - No search parameters. Post is: ".print_r($this->post, true));
			}
		}


		return $queryObject;
	}
	
	
	public function processGet($getObject)
	{
        $debug = false;

        
        if (!$this->dataSource)
        {
        	die("No data source provided");
        }
        
        if (!$this->columnsToDisplay)
        {
        	$this->columnsToDisplay = [];

			foreach ($this->dataSource->dataMapping->ordered as $columnMapping)
			{
				
				if ($debug)
				{
					error_log("Debugging column: {$columnMapping->phpKey}");
				}
				
				
				$hideOnListsForUserFunction = $columnMapping->hideOnListsForUserFunction;

				if ($hideOnListsForUserFunction)
				{
					$hideOnLists = $hideOnListsForUserFunction($this->user, null);

					if ($hideOnLists)
					{
						continue;
					}
				}
				
		
				if ($columnMapping->hideOnListsForUser(DataAccessManager::get("persona")->getCurrentUser()))
				{
					if ($debug) { error_log("Hiding column {$columnMapping->phpKey}"); }
					continue;
				}

				if ($debug)
				{
					gtk_log("Not hiding column {$columnMapping->phpKey}");
				}

				$this->columnsToDisplay[] = $columnMapping;
			}
        }
        
        if (!$this->searchableColumns)
        {
        	$this->searchableColumns = $this->dataSource->getSearchableColumnsForUser(
				DataAccessManager::get("persona")->getCurrentUser()
			);
        }
        
        $this->primaryKeyMapping = $this->dataSource->dataMapping->primaryKeyMapping;
        
        // Get the page number and number of items per page
        $this->page = isset($_GET['page']) ? $_GET['page'] : 1;
        $this->itemsPerPage = 100;
        
        // Calculate the offset based on the page number and number of items per page
        $this->offset = ($this->page - 1) * $this->itemsPerPage;
    
        $this->echoSelectedIfTrue = function($columnName) { return false; };
	}

	public function pageSection()
	{
		$debug = false;

		$toReturn = "";

	    $totalPages = ceil($this->count() / $this->itemsPerPage);
    
	    $query_parameters_for_page_link = $_GET;
    
	    unset($query_parameters_for_page_link['page']);
    
	    $baseURL = $_SERVER['REQUEST_URI'];

		if ($debug)
		{
			gtk_log("`pageSection` - Base URL: $baseURL");
		}
    
	    if (strpos($baseURL, "/all/") === 0)
	    {
	    	$baseURL = str_replace("/all", "", $baseURL);
	    	
	    }
    
	    $base_url_for_page_link = $baseURL . '?' . http_build_query($query_parameters_for_page_link);
    
	    for ($i = 1; $i <= $totalPages; $i++) 
		{
	    	$url = $base_url_for_page_link . '&page=' . $i;
	    	$toReturn .= "<a href=\"$url\">$i</a> ";
	    }

		return $toReturn;
	}

	public function renderBody($get, $post, $server, $cookie, $session, $files, $env)
	{
		$debug = false;

        ob_start(); ?>

		<?php
			if (is_string($this->header))
			{
				echo $this->header;
			}
			else if (is_callable($this->header))
			{
				$header = $this->header;
				$header($this);
			}
		?>

	    <h2 class="ml-4 text-2xl font-bold">
			<?php echo $this->dataSource->getPluralItemName(); ?>
		</h2>

	    <?php if ($this->dataSource->userHasPermissionTo("create", $this->user)): ?>
	        <a href=<?php echo $this->getNewHref(); ?>
			   class="ml-8"
			>
		       Crear <?php echo $this->dataSource->singleItemName(); ?>
		    </a>
        <?php endif; ?>
			
        <h3 class="ml-8">
			Count: <?php echo $this->count(); ?>
	    </h3>

		<form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="get">
			<input type="text" name="search" id="search" value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
			<button type="submit">Search</button>
			<select name="columnToSearch" id="columnToSearch_select">
    			<?php
        		foreach ($this->searchableColumns as $columnMapping)
        		{
        		    echo '<option';
					echo ' value="'.$columnMapping->phpKey.'"';
					$isSelected = false;
					if($this->dataSource->defaultSearchColumn)
					{
						$isSelected = true;
					}
					if ($isSelected)
					{
						echo ' selected';
					}
					echo '>';
        		    echo $columnMapping->getFormLabel($this->dataSource);
        		    echo '</option>';
        		}
 	    		?>
			</select>
		</form>


		<?php 

		if ($this->_items)
		{
			echo generateTableForUser(
				DataAccessManager::get("persona")->getCurrentUser(),
				$this->columnsToDisplay,
				$this->_items, 
				$this->dataSource, 
				$this->dataSource->dataAccessorName, 
				$debug);

		}
		else
		{
			if ($debug)
			{
				gtk_log("SQL: ".$this->queryObject()->getSQL());
			}

			echo generateTableForUser(
				DataAccessManager::get("persona")->getCurrentUser(),
				$this->columnsToDisplay,
				$this->queryObject(), 
				$this->dataSource, 
				$this->dataSource->dataAccessorName, 
				$debug);
		}

		if (is_string($this->footer))
		{
			echo $this->footer;
		}
		else if (is_callable($this->footer))
		{
			$footer = $this->footer;
			$footer($this);
		}

		echo $this->pageSection();

		return ob_get_clean();
	}

}
