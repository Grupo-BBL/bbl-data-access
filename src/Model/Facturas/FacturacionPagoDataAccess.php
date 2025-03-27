<?php

class FacturacionPagoDataAccess extends DataAccess
{
    public function register()
    {
        $columnMappings = [
            new GTKColumnMapping($this, "FGEseq", [
                "isPrimaryKey" => true,
                "isAutoIncrement" => true,
                "columnType" => "INTEGER"
            ]),
            new GTKColumnMapping($this, "FGEfpa", [
                "columnType" => "TEXT"
            ]),
            new GTKColumnMapping($this, "FGEpla", [
                "columnType" => "INTEGER"
            ]),
            new GTKColumnMapping($this, "FGEmnd", [
                "columnType" => "TEXT"
            ])
        ];
        
        $this->dataMapping = new GTKDataSetMapping($this, $columnMappings);
    }

    /**
     * Obtener información de pago de una factura
     */
    public function getInfoPago($facturaId)
    {
        $query = new SelectQuery($this);
        $query->where("FGEseq", "=", $facturaId);
        return $query->executeAndReturnFirst();
    }

    public function getTableName()
    {
        return "ffFactGral";
    }

    public function getDBConfigName()
    {
        return "oldStoneDB";
    }
}