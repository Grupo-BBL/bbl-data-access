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
            new GTKColumnMapping($this, "yFGEcod", [
                "columnType" => "TEXT",
                "maxLength" => 40
            ]),
            new GTKColumnMapping($this, "yFGEdes", [
                "columnType" => "TEXT",
                "maxLength" => 3000
            ]),
            new GTKColumnMapping($this, "yFGEref", [
                "columnType" => "TEXT",
                "maxLength" => 40
            ]),
            new GTKColumnMapping($this, "yFGEsta", [
                "columnType" => "TEXT",
                "maxLength" => 2
            ]),
            new GTKColumnMapping($this, "yFGEdel", [
                "columnType" => "BIT"
            ]),
            new GTKColumnMapping($this, "yFGEtag", [
                "columnType" => "TEXT",
                "maxLength" => 40
            ]),
            new GTKColumnMapping($this, "yFGEval", [
                "columnType" => "DECIMAL"
            ]),
            new GTKColumnMapping($this, "yFGEcan", [
                "columnType" => "DECIMAL"
            ]),
            new GTKColumnMapping($this, "yFGEsub", [
                "columnType" => "DECIMAL"
            ]),
            new GTKColumnMapping($this, "yFGEexe", [
                "columnType" => "BIT"
            ]),
            new GTKColumnMapping($this, "yFGEnum", [
                "columnType" => "TEXT",
                "maxLength" => 100
            ]),
            new GTKColumnMapping($this, "yFGEcli", [
                "columnType" => "TEXT",
                "maxLength" => 100
            ])
        ];
        
        $this->dataMapping = new GTKDataSetMapping($this, $columnMappings);
    }

    /**
     * Obtener los items de una factura específica
     */
    public function getItemsPorFactura($facturaId)
    {
        $db = $this->getDB();
        $sql = "SELECT 
                yFGEcod as codigo,
                yFGEdes as descripcion,
                yFGEref as referencia,
                yFGEsta as estado,
                yFGEval as precio_unitario,
                yFGEcan as cantidad,
                yFGEsub as subtotal,
                yFGEtag as etiqueta,
                yFGEdel as eliminado,
                yFGEexe as exento
            FROM ffItemsFG 
            WHERE yFGEseq = :facturaId 
            AND (yFGEdel = 0 OR yFGEdel IS NULL)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':facturaId' => $facturaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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