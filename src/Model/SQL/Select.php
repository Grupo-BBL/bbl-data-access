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
    public $_orderBy;
    public $limit;
    public $offset;
    public $desiredPageNumber;
    public $generator;
    public $queryModifier;
    public $joins = [];
    public $isDistinct = false;
    public $groupByColumns = [];


    /**
     * Join two tables using their column keys (e.g., 'users.id', 'posts.user_id')
     * @param string $tableColumnOneKey First table and column (format: 'table.column')
     * @param string $tableColumnTwoKey Second table and column (format: 'table.column')
     * @param string $type Join type ('LEFT JOIN' or 'INNER JOIN')
     * @return $this
     * @throws Exception If invalid join type or format
     */
    public function join($tableColumnOneKey, $tableColumnTwoKey, $type = 'LEFT JOIN') 
    {
        // Validate join type
        $validTypes = ['INNER JOIN', 'LEFT JOIN'];
        if (!in_array($type, $validTypes)) {
            throw new Exception("Invalid join type: {$type}. Must be one of: " . implode(', ', $validTypes));
        }

        // Parse table.column format for both keys
        list($daOneName, $daOneColumnName) = $this->parseTableColumnKey($tableColumnOneKey);
        list($daTwoName, $daTwoColumnName) = $this->parseTableColumnKey($tableColumnTwoKey);

        // Get data accessors
        $accessorOne = DAM::get($daOneName);
        $accessorTwo = DAM::get($daTwoName);

        // Determine which accessor is the data source and which should be joined
        $sourceAccessorName = $this->dataSource->dataAccessorName;
        
        if ($accessorOne === $this->dataSource) {
            $joinAccessor = $accessorTwo;
            $joinColumn = $daTwoColumnName;
            $sourceColumn = $daOneColumnName;
            $sourceAccessor = $accessorOne;
        } else if ($accessorTwo === $this->dataSource) {
            $joinAccessor = $accessorOne;
            $joinColumn = $daOneColumnName;
            $sourceColumn = $daTwoColumnName;
            $sourceAccessor = $accessorTwo;
            // Swap the columns since we're reversing the join order
            list($tableColumnOneKey, $tableColumnTwoKey) = [$tableColumnTwoKey, $tableColumnOneKey];
        } 
        else 
        {
            throw new Exception("Neither table matches the query's data source");
        }

        // Build the ON condition
        $dbColumnNameSource = $sourceAccessor->dbColumnNameForKey($sourceColumn, true);
        $dbColumnNameJoin = $joinAccessor->dbColumnNameForKey($joinColumn, true);

        $onCondition = "{$dbColumnNameSource} = {$dbColumnNameJoin}";

        // Add the join
        $this->joins[] = [
            'type' => $type,
            'table' => $joinAccessor->tableName(),
            'condition' => $onCondition
        ];

        return $this;
    }

    public function innerJoin($tableColumnOneKey, $tableColumnTwoKey)
    {
        return $this->join($tableColumnOneKey, $tableColumnTwoKey, 'INNER JOIN');
    }

    public function leftJoin($tableColumnOneKey, $tableColumnTwoKey)
    {
        return $this->join($tableColumnOneKey, $tableColumnTwoKey, 'LEFT JOIN');
    }
    

    /**
     * Parse a table.column key into its components
     * @param string $tableColumnKey Format: 'table.column'
     * @return array [tableName, columnName]
     * @throws Exception If invalid format
     */
    private function parseTableColumnKey($tableColumnKey) 
    {
        $parts = explode('.', $tableColumnKey);
        
        if (count($parts) !== 2) {
            throw new Exception("Invalid table.column format: {$tableColumnKey}. Expected format: 'table.column'");
        }

        return $parts;
    }



    public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    /**
     * Set the LIMIT clause for the query
     * @param int $limit Maximum number of rows to return
     * @return $this For method chaining
     */
    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
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

    public function __construct($maybeDataSource, $columns = null, $whereClauses = null, $queryOptions = [])
    {
        if ($maybeDataSource instanceof DataAccess)
        {
            $this->dataSource = $maybeDataSource;
        }
        else
        {
            $this->dataSource = DAM::get($maybeDataSource);
        }

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

    /**
     * Set the columns to be selected in the query
     * @param array $columns Array of column names to select
     *                      Can include raw SQL expressions like "COUNT(*) as count"
     *                      or column names like "id" or "table.column"
     * @return $this For method chaining
     */
    public function select($columns)
    {
        $this->columns = $columns;
        return $this;
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

        // If we have joins and the column name doesn't already include a table qualifier,
        // prefix it with the table name to avoid ambiguous column references
        if (!empty($this->joins) && strpos($dbColumnName, '.') === false) {
            $tableName = $dataAccessor->tableName();
            return $tableName . '.' . $dbColumnName;
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
            $this->_orderBy = $whereClause;
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
        
        if ($this->isDistinct) {
            $sql .= "DISTINCT ";
        }

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
                    // Check if this is a raw SQL expression (like COUNT(*) as count)
                    if (strpos($column, ' as ') !== false || strpos($column, '(') !== false) {
                        $toQueryColumns[] = $column;
                    } else {
                        $toQueryColumns[] = $this->dbColumnNameForKey($column);
                    }
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
            // When selecting all columns, explicitly prefix with table names
            $mainTable = $this->dataSource->tableName();
            $sql .= $mainTable . '.*';
            
            // Optionally add columns from joined tables
            foreach ($this->joins as $join) {
                $sql .= ', ' . $join['table'] . '.*';
            }
        }

        $sql .= " FROM ".$this->dataSource->tableName();

        foreach ($this->joins as $join) 
        {
            $sql .= " {$join['type']} {$join['table']} ON {$join['condition']}";
        }
        
        if ($this->whereGroup && (count($this->whereGroup->clauses) > 0))
        {
            $sql .= ' WHERE ' . $this->whereGroup->getSQLForSelectQuery($this, $params);
        }          
        
        // Add GROUP BY clause if specified
        if (!empty($this->groupByColumns)) {
            $sql .= ' GROUP BY ';
            $groupByColumns = [];
            
            foreach ($this->groupByColumns as $column) {
                if (is_string($column)) {
                    // Check if this is a raw SQL expression
                    if (strpos($column, '(') !== false) {
                        $groupByColumns[] = $column;
                    } else {
                        $groupByColumns[] = $this->dbColumnNameForKey($column);
                    }
                }
            }
            
            $sql .= implode(', ', $groupByColumns);
        }
        
        if (!$this->isCountQuery)
        {
            $didSetOrderBy = false;

            if (is_array($this->_orderBy) && (count($this->_orderBy) > 0))
            {
                $sql .= ' ORDER BY ';
                $isFirst = true;
                $isEven  = false;
                foreach ($this->_orderBy as $orderBy) 
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
            else if (is_string($this->_orderBy))
            {
                // if string contains ASC or DESC, use it as is, otherwise default to DESC
                if (strpos($this->_orderBy, 'ASC') !== false || strpos($this->_orderBy, 'DESC') !== false)
                {
                    $sql .= ' ORDER BY '.$this->_orderBy;
                }
                else
                {
                    $sql .= ' ORDER BY '.$this->_orderBy." DESC";
                }
            }
            else if ($this->_orderBy instanceof OrderBy)
            {
                $sql .= ' ORDER BY '.$this->dbColumnNameForKey($this->_orderBy->column)." ".$this->_orderBy->order;
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

    public function orderBy($toOrderBy)
    {
        return $this->setOrderBy($toOrderBy);
    }
    public function setOrderBy($toOrderBy)
    {
        $this->_orderBy = $toOrderBy;
    }

    /**
     * Add GROUP BY clause to the query
     * @param array|string $columns Column(s) to group by
     * @return $this For method chaining
     */
    public function groupBy($columns)
    {
        $this->groupByColumns = is_array($columns) ? $columns : [$columns];
        return $this;
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
        $debug = false;

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

    public function getAll(GTKSelectQueryModifier &$queryModifier = null)
    {
        return $this->executeAndReturnAll($this->queryModifier);
    }
    
    public function executeAndReturnAll(GTKSelectQueryModifier &$queryModifier = null)
    {
        $statement = $this->executeAndReturnStatement($queryModifier);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOne(GTKSelectQueryModifier &$queryModifier = null)
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

    public function distinct()
    {
        $this->isDistinct = true;
        return $this;
    }

}
