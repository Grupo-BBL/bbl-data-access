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

	public $itemHeader;
	public $itemFooter;

    public function renderForDataSource($dataSource, $user, $options)
    {
        $this->dataSource = $dataSource;
        $this->user 	  = $user;
		$this->itemHeader = $options["header"] ?? null;
		$this->itemFooter = $options["footer"] ?? null;
        return $this;
    }

	public function getNewHref()
	{
		/*
		$editHref = "new";

		$requestUri = $_SERVER['REQUEST_URI'];
		
		$uriWithoutQueryString = parse_url($requestUri, PHP_URL_PATH);
		
		
		if (stringEndsWith(".php", $uriWithoutQueryString))
		{
			$editHref = "new.php";
		}

		*/

	   return "new.php";
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

	public function renderBody()
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

		<?php $columnToSearch = isset($this->get['columnToSearch']) ? $this->get['columnToSearch'] : $this->dataSource->defaulSearchByColumnMapping()->phpKey; ?>

		<form action="<?php echo htmlspecialchars($this->server['REQUEST_URI']); ?>" method="get">
			<input type="text" name="search" id="search" value="<?php echo isset($this->get['search']) ? $_GET['search'] : ''; ?>">
			<button type="submit">Search</button>
			<select name="columnToSearch" id="columnToSearch_select">
    			<?php
        		foreach ($this->searchableColumns as $columnMapping) 
        		{
        		    echo '<option';
					echo ' value="'.$columnMapping->phpKey.'"';
					if ($columnToSearch == $columnMapping->phpKey)
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

			echo $this->generateTableForUser(
				DataAccessManager::get("persona")->getCurrentUser(),
				$this->columnsToDisplay,
				$this->queryObject(), 
				$this->dataSource, 
				$this->dataSource->dataAccessorName, 
				$debug);
		}

		echo $this->renderItemAttribute($this->itemHeader);
		echo $this->pageSection();

		return ob_get_clean();
	}


	public function generateTableForUser(
		$user,
		$columnsToDisplay, 
		$itemsOrQueryObject, 
		$dataSource, 
		$dataSourceName = null, 
		$debug = false
	) {
		$items = null;
		$count = 0;
	
		if (is_array($itemsOrQueryObject))
		{
			$count = gtk_count($itemsOrQueryObject);
			$items = $itemsOrQueryObject;
	
			if ($debug)
			{
				gtk_log("Is Array!");
				gtk_log("Items count: ".$count);
				if ($count < 200)
				{
					gtk_log("Items: ".print_r($items, true));
				}
				
			}
		}
		else
		{
			$count = $itemsOrQueryObject->count();
			$items = $itemsOrQueryObject->executeAndYield();
	
			if ($debug)
			{
				gtk_log("Is Query Object!");
				gtk_log("Items count: ".$count);
			}
		}
	
	
		if (!isset($dataSourceName))
		{
			$dataSourceName = $dataSource->dataAccessorName;
		}
	
		if ($debug)
		{
			error_log("Generating table for user: ".print_r($user, true));
			// error_log("Columns to display: ".print_r($columnsToDisplay, true));
			if (is_array($items))
			{
				error_log("Items: ".print_r($items, true));
			}
			else
			{
				error_log("Items: ".get_class($items));
			}
			error_log("Data source: ".get_class($dataSource));
		}
	
		$index = 0;
	
		ob_start(); // Start output buffering 
		?>
		<table>
			<thead>
				<tr>
					<th>Actions</th>
					<?php foreach ($columnsToDisplay as $columnMapping): ?>
						<?php
							if ($columnMapping->hideOnListsForUser($user)) 
							{
								continue;
							} 
							else 
							{
								echo "<th class='min-w-[75px]'>";
								echo $columnMapping->getFormLabel();
								echo "</th>";
							}
						?>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
			
			<?php if ($count == 0): ?>
			<tr>
				<td colspan="<?php echo gtk_count($columnsToDisplay) + 1; ?>">
					No hay elementos que mostrar.
				</td>
			</tr>
			<?php else: ?>
				<?php foreach ($items as $currentItem): ?>
					<?php if ($dataSource->itemIsVisibleToUser($user, $currentItem)): ?>
						<?php $itemIdentifier = $dataSource->dataMapping->valueForIdentifier($currentItem); ?>
						<tr 
							class="border-b border-gray-200"
							style=<?php echo '"'.$dataSource->rowStyleForItem($currentItem, $index).'"'; ?>
							id=<?php echo '"cell-'.$dataSource->dataAccessorName.'-'.$itemIdentifier.'"'; ?>
						>
							<?php echo $dataSource->tableRowContentsForUserItemColumns(
														$user, 
														$currentItem, 
														$columnsToDisplay); ?>
						</tr>
						<?php $index++; ?>
					<?php else: ?>
						<?php if ($debug): ?>
							<?php gtk_log("Item is not visible to user: ".print_r($currentItem, true)); ?>
						<?php endif; ?>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php return ob_get_clean(); // End output buffering and get the buffered content as a string
	}


	public function tableRowContentsForUserItemColumns($user, $item, $columnsToDisplay)
    {
        $debug = false;

        if ($debug)
        {
            gtk_log("tableRowContents --- item --- ".print_r($item, true));
        }

        $isFirstColumn = true;

        $toReturn = "";
    
        $toReturn .= "<td>".$this->dataSource->displayActionsForUserItem($user, $item)."</td>";
    
        $primaryKeyMapping = $this->dataSource->primaryKeyMapping();
        // $primaryKeyPHPKey  = $primaryKeyMapping->phpKey;
        $primaryKeyValue   = $primaryKeyMapping->valueFromDatabase($item);

        if ($debug)
        {
            error_log("Will display columns: ");
        }
    
        foreach ($columnsToDisplay as $columnMapping) 
        {
            $toReturn .= $columnMapping->listDisplayForDataSourceUserItem($this, $user, $item, $primaryKeyValue);
        }

        if ($debug)
        {
            error_log("tableRowContents --- ".$toReturn);
        }
        
        return $toReturn;
    }

}
