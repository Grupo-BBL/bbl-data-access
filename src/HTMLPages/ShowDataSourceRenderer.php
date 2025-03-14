<?php

class ShowDataSourceRenderer extends GTKHTMLPage
{
    public $dataSource;
    public $user;

	public $itemHeader;
	public $itemFooter;

	public $dataMapping;
	public $columnMappings;
	public $primaryKeyMapping;
	public $nonPrimaryLookup;
    public $item;
    public $itemIdentifier;
	public $isNew;

	
    public function renderForDataSource($dataSource, $user, $options)
    {
        $this->dataSource = $dataSource;
        $this->user = $user;
		$this->itemHeader = $options["header"] ?? null;
		$this->itemFooter = $options["footer"] ?? null;
        return $this;
    }
	
    public function processGet($getObject)
	{
		$debug = false;

		parent::processGet($getObject);

		
		$this->primaryKeyMapping = $this->dataSource->dataMapping->primaryKeyMapping;
		$this->columnMappings = $this->dataSource->dataMapping->ordered;

		$this->isNew             = false;

		if (isset($_GET["isNew"]) && isTruthy($_GET["isNew"]))
		{
			$this->isNew = true;
		}
		else
		{
	
			if (array_key_exists($this->primaryKeyMapping->phpKey, $_GET))
			{
				$this->itemIdentifier = $_GET[$this->primaryKeyMapping->phpKey];
			} 
			else if (array_key_exists("identifier", $_GET)) 
			{
				$this->itemIdentifier = $_GET["identifier"];
			}
			else if (array_key_exists("id", $_GET))
			{
				$this->itemIdentifier = $_GET["id"];
			} 
		}


	    if ($debug)
		{
			error_log("Primary Key Mapping: ".$this->primaryKeyMapping->phpKey);
		}

	
		if ($this->itemIdentifier)
		{
			$maybeArray    = $this->dataSource->getMany($this->primaryKeyMapping->phpKey, $this->itemIdentifier);
	
			if (count($maybeArray) > 1)
			{
				die("More than one item found with {$this->primaryKeyMapping->phpKey} = {$this->itemIdentifier}");
			}
			else
			{
				$this->item = $maybeArray[0];
			}
	
			if (!$this->item)
			{
				die("No item found with {$this->primaryKeyMapping->phpKey} = {$this->itemIdentifier}");
			}
		}
		else if ($this->nonPrimaryLookup && array_key_exists($this->nonPrimaryLookup->phpKey, $_GET))
		{
			$this->itemIdentifier = $_GET[$this->nonPrimaryLookup->phpKey];
			$maybeArray    = $this->dataSource->getMany($this->nonPrimaryLookup->phpKey, $this->itemIdentifier);
	
			if (count($maybeArray) > 1)
			{
				die("More than one item found with {$this->nonPrimaryLookup->phpKey} = {$this->itemIdentifier}");
			}
			else
			{
				$this->item = $maybeArray[0];
			}
	
			if (!$this->item)
			{
				die("No item found with {$this->nonPrimaryLookup->phpKey} = {$this->itemIdentifier}");
			}
		}
		else
		{
			$this->isNew = true;
			$this->item = [];
			$this->itemIdentifier = 'new';
		}
	}

	public function renderBody()
    {
        $debug = false;

		$user = DAM::get("session")->getCurrentUser();

		if ($debug)
		{
			error_log("Data Source : ".get_class($this->dataSource));
			error_log("Data Set Mapping: ".get_class($this->dataSource->dataMapping));
			error_log("Ordered: ".count($this->dataSource->dataMapping->ordered));
		}

		$dataSourceName = get_class($this->dataSource);

        $inputOptions = [
        	"identifier"     => $this->itemIdentifier,
        	"dataSourceName" => $dataSourceName,
        ];

        ob_start(); ?>
        <h2 class="ml-4 mt-4 text-2xl font-bold"><?php echo $this->dataSource->singleItemName(); ?></h2>
        <?php echo $this->renderItemAttribute($this->itemHeader); ?>
        <table>
        	<?php foreach ($this->columnMappings as $columnMapping): ?>
        		<?php if ($columnMapping->hideOnShow()): ?>
					<?php continue; ?>
				<?php else: ?>
        			<tr>
        				<th><?php echo $columnMapping->getFormLabel($this->dataSource); ?></th>
        				<td>
        					<?php echo $columnMapping->valueFromDatabase($this->item); ?>
							<?php // echo $columnMapping->showHTMLForOptions($this->item, $inputOptions); ?>
        				</td>
        			</tr>
        		<?php endif; ?>
        	<?php endforeach; ?>
        </table>

		<div class="mt-8 ml-8 pt-3 pl-8 pb-6 bg-white shadow-md">
			<h1 class="font-bold">Actions</h1>
			<?php 
			$actions = $this->dataSource->actionsForLocationUserItem("edit", $user, $this->item);
			$actionSection = "";
			if (count($actions) > 0)
			{
				foreach ($actions as $action)
				{
	
					$actionSection .= $action->anchorLinkForItem($user, $this->item);
					$actionSection .= "<br/>";
				}
			}
			else
			{
				$actionSection .= "No hay ninguna acciones disponible.";
			}

			echo $actionSection;
			?>
		</div>

        <?php echo $this->renderItemAttribute($this->itemFooter); ?>
        <?php return ob_get_clean();
    }
}
