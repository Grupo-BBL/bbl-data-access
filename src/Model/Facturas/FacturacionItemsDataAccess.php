<?php

class FacturacionItemsDataAccess extends DataAccess
{
    public function register()
    {
        $columnMappings = [
            new GTKColumnMapping($this, "yFGEseq", [
                "isPrimaryKey" => true,
                "isAutoIncrement" => true,
                "columnType" => "INTEGER"
            ]),
            new GTKColumnMapping($this, "yFGEdes", [
                "isRequired" => true,
                "columnType" => "TEXT"
            ]),
            new GTKColumnMapping($this, "yFGEcan", [
                "isRequired" => true,
                "columnType" => "DECIMAL"
            ]),
            new GTKColumnMapping($this, "yFGEval", [
                "isRequired" => true,
                "columnType" => "DECIMAL"
            ])
        ];
        
        $this->dataMapping = new GTKDataSetMapping($this, $columnMappings);
    }

    /**
     * Obtener items por ID de factura
     */
    public function getItemsPorFactura($facturaId)
    {
        $query = new SelectQuery($this);
        $query->where("yFGEseq", "=", $facturaId);
        return $query->executeAndReturnAll();
    }

    public function getTableName()
    {
        return "ffItemsFG";
    }

    public function getDBConfigName()
    {
        return "oldStoneDB";
    }
}