<?php



class DataAccessorColumnKey
{
    public $dataAccessorName;
    public $columnKey;

    public function __construct($dataAccessorName, $columnKey)
    {
        $this->dataAccessorName = $dataAccessorName;
        $this->columnKey = $columnKey;
    }

}

class SelectQuery implements IteratorAggregate, 
                            Countable, 
                            SQLWhereInterface
{
    public $isCountQuery = false;
    public $dataSource;
    public $columns;
    public $queryOptions = [];
    public $whereGroup;
    public $orderBy;
    public $limit;
    public $offset;
    public $desiredPageNumber;
    public $generator;
    public $queryModifier;

    public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    public function setOffset($offset)
    {
        $this->offset = $offset;
    }

    public function currentPage()
    {
        if (!$this->limit)
        {
            return 1;
        }
    
        return (int) floor($this->offset / $this->limit) + 1;
    }

    public function numberOfPages()
    {
        if (!$this->limit)
        {
            return 0;
        }

        return ceil($this->count() / $this->limit);
    }

    public function __construct($dataSource, $columns = null, $whereClauses = null, $queryOptions = [])
    {
        $this->dataSource = $dataSource;
        $this->columns    = $columns;

        if ($whereClauses)
        {
            $this->addWhereClauses($whereClauses);    
        }
        

        $this->queryOptions = $queryOptions;
    }

    public function getPDO()
    {
        return $this->dataSource->getPDO();
    }

    public function getDriverName()
    {
        return $this->getPDO()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function setDataSource($dataSource)
    {
        $this->dataSource = $dataSource;
    }

    public function setColumns($columns)
    {
        $this->columns = $columns;
    }

    public function getDataAccessorColumnKey($key)
    {
        if (strpos($key, '.') !== false) 
        {
            list($dataAccessorName, $columnKey) = explode('.', $key);
            return new DataAccessorColumnKey($dataAccessorName, $columnKey);
        }
        else
        {
            return new DataAccessorColumnKey($this->dataSource->dataAccessorName, $key);
        }
    }

    public function dbColumnNameForKey($key)
    {
        $dataAccessorColumnKey = $this->getDataAccessorColumnKey($key);
        $dataAccessorName = $dataAccessorColumnKey->dataAccessorName;
        $columnKey = $dataAccessorColumnKey->columnKey;

        $dataAccessor = DAM::get($dataAccessorName);

        $dbColumnName = $dataAccessor->dbColumnNameForKey($columnKey);

        if (!$dbColumnName)
        {
            throw new Exception("Column not found: ".$columnKey.' in '.$dataAccessorName);
        }

        return $dbColumnName;
    }

    public function columnMappingForKey($key)
    {
        $dataAccessorColumnKey = $this->getDataAccessorColumnKey($key);
        $dataAccessorName = $dataAccessorColumnKey->dataAccessorName;
        $columnKey = $dataAccessorColumnKey->columnKey;

        $dataAccessor = DAM::get($dataAccessorName);

        $toReturn = $dataAccessor->columnMappingForKey($columnKey);

        if (!$toReturn)
        {
            throw new Exception("Column not found: ".$columnKey.' in '.$dataAccessorName);
        }

        return $toReturn;
    }

    public function addClause($whereClause)
    {
        if (($whereClause instanceof WhereClause) || ($whereClause instanceof WhereGroup) || ($whereClause instanceof RawWhereClause))
        {
            return $this->addWhereClause($whereClause);
        }
        else if ($whereClause instanceof OrderBy)
        {
            $this->orderBy = $whereClause;
        }
        else if ($whereClause instanceof LimitClause)
        {
            $this->limit = $whereClause;
        }
        else
        {
            throw new Exception("Don't know how to handle clause of type: ".get_class($whereClause));
        }
    }

    public function addWhereClause($whereClause) 
    {
        if (!$this->whereGroup) 
        {
            $this->whereGroup = new WhereGroup();
        }
        $this->whereGroup->addClause($whereClause);
        return $this;
    }

    public function addWhereClauses($whereClauses) 
    {
        if ($whereClauses instanceof WhereGroup)
        {
            $this->whereGroup = $whereClauses;
        }
        else if ($whereClauses) 
        {
            $this->whereGroup = new WhereGroup();
            foreach ($whereClauses as $whereClause) {
                $this->whereGroup->addClause($whereClause);
            }
        }
        return $this;
    }

    public function whereRaw($sql, ...$params)
    {
        $this->where(new RawWhereClause($sql, ...$params));
        return $this;
    }

    public function between($column, $start, $end, $inclusive = true) 
    {
        $this->where(new BetweenClause($column, $start, $end, $inclusive));
        return $this;
    }

    public function where($column, $operator = null, ...$values) 
    {
        $whereClause = null;

        if (($column instanceof WhereClause) || ($column instanceof RawWhereClause) || ($column instanceof BetweenClause))
        {
            $whereClause = $column;
        }
        else if ($column instanceof WhereGroup)
        {
            $whereClause = $column;
        }
        else
        {
            $whereClause = new WhereClause($column, $operator, ...$values);
        }
        
        if (!$this->whereGroup) {
            $this->whereGroup = new WhereGroup();
        }
        $this->whereGroup->addClause($whereClause);

        return $this;
    }

    public function whereGroup($logicalOperator = 'AND') 
    {
        if (!$this->whereGroup) 
        {
            $this->whereGroup = new WhereGroup();
        }
        $whereGroup = new WhereGroup($logicalOperator);
        $this->whereGroup->addGroup($whereGroup);
        return $whereGroup;
    }

    public function getSQLAndUpdateParams(&$params, $pdo = null) 
    {
        $debug = false;

        $sql = "";
        $sql .= "SELECT ";

        if ($this->isCountQuery)
        {
             $sql .= " COUNT(*) as COUNT";
        }
        else if ($this->columns && count($this->columns) > 0) 
        {
            $toQueryColumns = [];

            foreach ($this->columns as $column) 
            {
                if (is_string($column))
                {
                    $toQueryColumns[] = $this->dbColumnNameForKey($column);
                }
                else 
                {
                    /* Ignore — likely a virutal column. */
                }
                
            }

            $sql .= implode(',', $toQueryColumns);
        } 
        else 
        {
            $sql .= '*';
        }

        $sql .= " FROM ".$this->dataSource->tableName();
        
        if ($this->whereGroup && (count($this->whereGroup->clauses) > 0))
        {
            $sql .= ' WHERE ' . $this->whereGroup->getSQLForSelectQuery($this, $params);
        }          
        
        if (!$this->isCountQuery)
        {
            $didSetOrderBy = false;

            if (is_array($this->orderBy) && (count($this->orderBy) > 0))
            {
                $sql .= ' ORDER BY ';
                $isFirst = true;
                $isEven  = false;
                foreach ($this->orderBy as $orderBy) 
                {
                    if ($debug)
                    {
                        error_log("Handling order by case: ".print_r($orderBy,true));
                    }
                    if (!$isFirst)
                    {
                        $sql .= ', ';
                    }
                    $isFirst = false;
    
                    if (is_string($orderBy))
                    {
                        $sql .= $this->dbColumnNameForKey($orderBy)." ASC";
                    }
                    else if (is_array($orderBy) && (count($orderBy) == 2))
                    {
    
                        $sql .= $this->dbColumnNameForKey($orderBy[0])." ".$orderBy[1];
                    }
                    else if ($orderBy instanceof OrderBy)
                    {
                        $sql .= $this->dbColumnNameForKey($orderBy->column)." ".$orderBy->order;
                    }
                    else
                    {
                        throw new Exception("Don't know how to handle ORDER BY object of gtype: ".get_class($orderBy));
                    }
                    
    
                    $isEven = !$isEven;
                }
    
                $didSetOrderBy = true;
            }
            else if (is_string($this->orderBy))
            {
    
            }
            else if ($this->orderBy instanceof OrderBy)
            {
                $sql .= ' ORDER BY '.$this->dbColumnNameForKey($this->orderBy->column)." ".$this->orderBy->order;
            }
            else if ($this->dataSource->defaultOrderByColumnKey)
            {
                $sql .= " ORDER BY ".$this->dataSource->defaultOrderByColumnKey." ".($this->dataSource->defaultOrderByOrder ?? "DESC");
                $didSetOrderBy = true;
            }

            if ($this->desiredPageNumber)
            {
                $this->limit  = $this->limit ?? 100;
                $this->offset = ($this->desiredPageNumber - 1) * $this->limit;
            }

            if ($this->limit || $this->offset)
            {
                $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

                if (!$didSetOrderBy && ($driverName == "sqlsrv"))
                {
                    $orderByColumn = $this->dataSource->defaultOrderByColumnKey;
                    $orderByOrder  = $this->dataSource->defaultOrderByOrder ?? "DESC";
                
                    if (!$orderByColumn)
                    {
                        throw new Exception("Cannot do limit/offset without an ORDER BY clause - SQL: ".$sql);
                    }

                    $sql .= " ORDER BY ".$orderByColumn." ".$orderByOrder;
                }

                if ($this->limit instanceof LimitClause)
                {
                    $this->limit  = $this->limit->limit;
                    $this->offset = $this->limit->offset;
                }
                
                $sql .= $this->sqlForLimitOffset($this->limit, $this->offset, $pdo);
            }

        }

        if ($debug)
        {
            error_log("SQL@SelectFromWhere - DataSource(".get_class($this->dataSource).": ".$sql);
        }

        return $sql;
    }

    public function setOrderBy($toOrderBy)
    {
        $this->orderBy = $toOrderBy;
    }

    public function sqlForLimitOffset($limit, $offset, $pdo)
    {
        $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        $sql = "";

        switch ($driverName)
        {
            case 'mysql':
            case 'sqlite':
                if ($limit > 0)
                {
                    $sql .= " LIMIT {$limit}";
                    if ($offset)
                    {
                        $sql .= " OFFSET {$offset}";    
                    } 
                    
                }
                break;
            case 'pgsql':
            case 'sqlsrv':
                if ($limit > 0)
                {
                   $offsetToUse = $offset ?? 0;                    
                    $sql .= " OFFSET ".$offsetToUse." ROWS";
                    $sql .= " FETCH NEXT {$limit} ROWS ONLY";
                }
                break;
            case 'oci': // Oracle
                // $sql = "SELECT * FROM (
                //     SELECT *, ROW_NUMBER() OVER (ORDER BY {$orderByColumn} {$orderByOrder}) AS row_num
                //     FROM {$this->tableName()}
                // ) WHERE row_num BETWEEN {$offset} + 1 AND {$offset} + {$limit}";
                // break;
            default:
                gtk_log("Connected to a database with unsupported driver: " . $driverName);
                die();
        }

        return $sql;
    }

    public function getPDOStatement(&$params)
    {
        $debug = false;

        $pdo = $this->dataSource->getPDO();

        $sql = $this->getSQLAndUpdateParams($params, $pdo);
        
        if ($debug)
        {
            error_log("SQL (".get_class($this->dataSource)."): ".$sql);
        }

        try
        {
            $pdoStatement = $pdo->prepare($sql); 
        }
        catch (Exception $e)
        {
            if ($this->isCountQuery)
            {
                throw $e;
            }
            else
            {
                return QueryExceptionManager::manageQueryExceptionForDataSource($this->dataSource, $e, $sql, $params, $outError);
            }
        }

        return $pdoStatement;
        // return $this->dataSource->execute($sql, $params);
    }

    public function getCount()
    {
        return $this->count();
    }

    public function count($debug = false) : int
    {
        $debug = true;

        $this->isCountQuery = true;

        $params = [];
        $pdoStatement = $this->getPDOStatement($params);
        if ($debug)
        {
            gtk_log("COUNT Query: ".$pdoStatement->queryString);
        }
        $pdoStatement->execute($params);
        $result = $pdoStatement->fetch(PDO::FETCH_ASSOC);
        if ($debug)
        {
            gtk_log("COUNT: ".print_r($result, true));
        }
        $this->isCountQuery = false;
        return $result['COUNT'];
    }

    public function sql()
    {
        return $this->getSQL();
    }

    public function getSQL()
    {
        $params = [];
        return $this->getSQLAndUpdateParams($params, $this->dataSource->getPDO());
    }

    public function executeAndReturnStatement(GTKSelectQueryModifier &$queryModifier = null)
    {
        $params = [];
        
        if ($queryModifier)
        {
            $queryModifier->applyToQuery($this);
        }

        $statement = $this->getPDOStatement($params);
        try
        {
            // echo "<h1>"."Statement: ".$statement->queryString." Params: ".print_r($params, true)."</h1>";
            $statement->execute($params);
        }
        catch (Exception $e)
        {
            return QueryExceptionManager::manageQueryExceptionForDataSource($this->dataSource, $e, $statement->queryString, $params, $outError);
        }
        // $statement->execute($params);
        return $statement;
    }

    public function getIterator(): Generator {
        if (!$this->generator) 
        {
            $this->generator = $this->executeAndYield($this->queryModifier);
        }
        yield from $this->generator;
    }

    public function executeAndYield(GTKSelectQueryModifier &$queryModifier = null)
    {
        $statement = $this->executeAndReturnStatement($queryModifier);
    
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) 
        {
            yield $row;
        }
    }

    public function executeAndReturnCountableGenerator(GTKSelectQueryModifier &$queryModifier = null)
    {
        $count = $this->count($queryModifier);
        $generator = $this->executeAndYield($queryModifier);
        return new GTKCountableGenerator($generator, $count);
    }

    public function executeAndReturnAll(GTKSelectQueryModifier &$queryModifier = null)
    {
        $statement = $this->executeAndReturnStatement($queryModifier);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function executeAndReturnOne(GTKSelectQueryModifier &$queryModifier = null)
    {
        $useLimitStyle = false; 

        if ($useLimitStyle)
        {
            $this->limit = 1;
            $statement = $this->executeAndReturnStatement($queryModifier);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            $this->limit = null;
            
            if ($row)
            {
                return $row;
            }
            else
            {
                return null;
            }
        }
        else
        {
            $results = $this->executeAndReturnAll($queryModifier);
            if (count($results) > 0)
            {
                return $results[0];
            }
            else
            {
                return null;
            }
        }
    }

    public function generatePagination(GTKSelectQueryModifier $queryModifier = null, PaginationStyler $styler = null)
    {
        $styler = $styler ?? new PaginationStyler();

        $urlBase                    = $styler->urlBase                ?? '';
        $paginationDivClass         = $styler->paginationDivClass     ?? '';
        $pageQueryParameterName     = $styler->pageQueryParameterName ?? 'page';
        $paginationLinkClass        = $styler->paginationLinkClass    ?? 'page-link';
        $paginationActiveLinkClass  = $styler->paginationActiveLinkClass ?? 'active';
        $paginationLinkStyle        = $styler->paginationLinkStyle    ?? '';
        $paginationActicveLinkStyle = $styler->paginationActiveLinkStyle ?? '';


        $currentPage = $this->currentPage();
        $totalPages  = $this->numberOfPages();

        ob_start();
        ?>
        <div class="<?php echo $paginationDivClass; ?>">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php

                $queryParameters =[];

                if ($queryModifier)
                {
                    $queryModifier->serializeToQueryParameters($queryParameters);
                }

                $queryParameters[$pageQueryParameterName] = $i;
                $queryParameters = array_merge($queryParameters, $styler->extraQueryParameters);
                
                $linkHref = $urlBase.'?'.http_build_query($queryParameters);
                
                $linkClassTag = [
                    $paginationLinkClass,
                ];

                $isActivePage = ($i == $currentPage);

                if ($isActivePage)
                {
                    $linkClassTag[] = $paginationActiveLinkClass;
                }

                $linkClassTag = implode(' ', $linkClassTag);

                $linkStyleTag = $paginationLinkStyle;

                if ($isActivePage)
                {
                    $linkStyleTag .= ' '.$paginationActicveLinkStyle;
                }
                ?>
    
                <a href  = "<?php echo $linkHref; ?>"
                   class = "<?php echo $linkClassTag; ?>"
                   style = "<?php echo $linkStyleTag; ?>"
                >
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function generateTableForUser($user, $columnsToDisplay = null, $options = null)
    {
        $debug = false;
		$items = null;
		$count = 0;

        $rowStyleForItem = $options["rowStyleForItem"] ?? Closure::fromCallable([$this->dataSource, 'rowStyleForItem']);

        $whileIterating = $options["whileIterating"] ?? null;

        $columnMappingsToDisplay = null;

        if (!$columnsToDisplay)
        {
            $columnMappingsToDisplay = $this->dataSource->dataMapping->ordered;
        }
        else
        {
            $columnMappingsToDisplay = [];

            foreach ($columnsToDisplay as $maybeColumnMapping)
            {
                if (is_string($maybeColumnMapping))
                {
                    $columnMappingsToDisplay[] = $this->columnMappingForKey($maybeColumnMapping);
                }
                else if (($maybeColumnMapping instanceof GTKColumnBase) || ($maybeColumnMapping instanceof GTKItemCellContentPresenter))
                {
                    $columnMappingsToDisplay[] = $maybeColumnMapping;
                }
            }
        }

        $count = $this->count();
        $items = $this->getIterator();
		$index = 0;
	
		ob_start(); // Start output buffering 
		?>
		<table>
			<thead>
				<tr>
					<?php foreach ($columnMappingsToDisplay as $columnMapping): ?>
						<?php
                            echo "<th class='min-w-[75px]'>";
							echo $columnMapping->getFormLabel();
							echo "</th>";
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
				<?php foreach ($items as $index => $currentItem): ?>
						<?php 
                            $itemIdentifier = $this->dataSource->valueForIdentifier($currentItem); 
                            if ($whileIterating)
                            {
                                $whileIterating($currentItem, $index);
                            }
                        ?>
                        <?php 
                        $rowStyle = '';

                        if (is_callable($rowStyleForItem)) 
                        { 
                            $rowStyle = $rowStyleForItem($currentItem, $index);
                        }
                        else if (is_string($rowStyleForItem))
                        {
                            $rowStyle = $rowStyleForItem;
                        }

                        ?>
						<tr class="border-b border-gray-200"
							style=<?php echo '"'.$rowStyle.'"'; ?>
							id=<?php echo '"cell-'.$itemIdentifier.'"'; ?>
						>
                        <?php 
                        foreach ($columnMappingsToDisplay as $columnMapping)
                        {
                            $displayFunction = null;

                            $toDisplay = null;

                            if ($displayFunction)
                            {
                                $argument = new GTKColumnMappingListDisplayArgument();

                                $argument->user    = $user;
                                $argument->item    = $currentItem;
                                $argument->options = null;
                                
                                $toDisplay = $displayFunction($argument);
                            }
                            else
                            {
                                $toDisplay = $columnMapping->valueFromDatabase($currentItem);
                            }

                            echo "<td>".$toDisplay."</td>";
                        } 
                        ?>
						</tr>
						<?php $index++; ?>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php return ob_get_clean(); // End output buffering and get the buffered content as a string
	}


    
}
