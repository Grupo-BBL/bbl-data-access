<?php

require_once dirname($_SERVER["DOCUMENT_ROOT"]).'\vendor\autoload.php';
require_once "tablePlusHelpers.php";

class TablePlusDataSourceHandlerPageSection extends TablePlusPage
{
    public $dataSource;

    public function handlePost($postObject)
    {
        $debug = false;

        if (!isset($_GET['data_source'])) {
            die("<h1>No data source provided </h1><pre>" . print_r($_GET, true) . "</pre><pre>" . print_r($_POST, true) . "</pre>");
        }
        
        $dataSourceName = $_GET['data_source'];
        $dataSource     = DataAccessManager::get($dataSourceName);
        
        if (!$dataSource)
        {
            die("<h1>Invalid data source provided</h1>");
        }
        
        $searchableColumns = $dataSource->getSearchableColumns();
    }

    public function render($get, $post, $server, $cookie, $session, $files, $env)
    {
        if ($debug)
        {
            error_log("Running `dataSourceHandler.php`: request URI: ".$_SERVER['REQUEST_URI']);
            echo "<pre>GET</pre>";
            echo "<pre>";
            echo print_r($_GET, true);
            echo "</pre>";
            echo "<pre>POST</pre>";
            echo "<pre>";
            echo print_r($_POST, true);
            echo "</pre>";
        }


        // HTML...
        return $this->columnAndFilterSelectionAreaForDataSource($dataSource);
    }
}