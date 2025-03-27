<?php

class FacturacionDataAccess extends DataAccess
{
    public function register()
    {
        $columnMappings = [
            new GTKColumnMapping($this, "FGEseq", [
                "isPrimaryKey" => true,
                "isAutoIncrement" => true,
                "columnType" => "INTEGER"
            ]),
            new GTKColumnMapping($this, "FGEcli", [
                "isRequired" => true,
                "columnType" => "TEXT"
            ]),
            new GTKColumnMapping($this, "FGEncf", [
                "isRequired" => true,
                "isUnique" => true,
                "columnType" => "TEXT"
            ]),
            new GTKColumnMapping($this, "FGEfec", [
                "isRequired" => true,
                "columnType" => "DATETIME"
            ]),
            new GTKColumnMapping($this, "FGEtot", [
                "isRequired" => true,
                "columnType" => "DECIMAL"
            ]),
            new GTKColumnMapping($this, "FGEitb", [
                "isRequired" => true,
                "columnType" => "DECIMAL"
            ]),
            new GTKColumnMapping($this, "FGEtip", [
                "columnType" => "TEXT"
            ])
        ];
        
        $this->dataMapping = new GTKDataSetMapping($this, $columnMappings);
    }

    /**
     * Obtener todas las facturas ordenadas por fecha descendente
     * 
     * @return array Lista de facturas
     */
    public function selectAll()
    {
        $query = new SelectQuery($this);
        $query->orderBy("FGEfec", "DESC");
        return $query->executeAndReturnAll();
    }

    /**
     * Obtener una factura por su ID
     * 
     * @param int $id ID de la factura
     * @return array|null La factura o null si no se encuentra
     */
    public function getFacturaPorId($id)
    {
        return $this->getOne("FGEseq", $id);
    }

    /**
     * Obtener facturas por rango de fechas
     * 
     * @param string $fechaInicio Fecha inicial en formato Y-m-d
     * @param string $fechaFin Fecha final en formato Y-m-d
     * @return array Lista de facturas
     */
    public function getFacturasPorFecha($fechaInicio, $fechaFin)
    {
        $query = new SelectQuery($this);
        $query->where("FGEfec", ">=", $fechaInicio);
        $query->where("FGEfec", "<=", $fechaFin);
        $query->orderBy("FGEfec", "DESC");
        return $query->executeAndReturnAll();
    }

    /**
     * Obtener facturas por cliente
     * 
     * @param string $cliente Código del cliente
     * @return array Lista de facturas
     */
    public function getFacturasPorCliente($cliente)
    {
        $query = new SelectQuery($this);
        $query->where("FGEcli", "=", $cliente);
        $query->orderBy("FGEfec", "DESC");
        return $query->executeAndReturnAll();
    }

    /**
     * Obtener factura por NCF
     * 
     * @param string $ncf Número de Comprobante Fiscal
     * @return array|null La factura o null si no se encuentra
     */
    public function getFacturaPorNCF($ncf)
    {
        return $this->getOne("FGEncf", $ncf);
    }

    /**
     * Obtener el nombre de la tabla
     */
    public function getTableName()
    {
        return "ffFactGral";
    }

    /**
     * Obtener el nombre de la configuración de base de datos
     */
    public function getDBConfigName()
    {
        return "oldStoneDB";
    }
}
