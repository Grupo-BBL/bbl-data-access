<?php


class FacturacionAuxiliarDataAccess extends DataAccess
{
    public function register()
    {
        $columnMappings = [
            new GTKColumnMapping($this, "AUSEQ", [
                "isPrimaryKey" => true,
                "isAutoIncrement" => true,
                "columnType" => "INTEGER"
            ]),
            new GTKColumnMapping($this, "AUCOD", [
                "columnType" => "TEXT",
                "maxLength" => 24
            ]),
            new GTKColumnMapping($this, "AUDES", [
                "columnType" => "TEXT",
                "maxLength" => 80
            ]),
            new GTKColumnMapping($this, "AUORI", [
                "columnType" => "TEXT",
                "maxLength" => 2
            ]),
            new GTKColumnMapping($this, "AUCHK", [
                "columnType" => "TINYINT"
            ]),
            new GTKColumnMapping($this, "AUNUM", [
                "columnType" => "DECIMAL"
            ]),
            new GTKColumnMapping($this, "AUDIS", [
                "columnType" => "DECIMAL"
            ]),
            new GTKColumnMapping($this, "AUCTR", [
                "columnType" => "TEXT",
                "maxLength" => 16
            ]),
            new GTKColumnMapping($this, "AUCAN", [
                "columnType" => "TEXT",
                "maxLength" => 40
            ])
        ];
        
        $this->dataMapping = new GTKDataSetMapping($this, $columnMappings);
    }

    /**
     * Obtener información completa del cliente
     */
    public function getClienteInfo($clienteId)
    {
        $db = $this->getDB();
        $sql = "SELECT 
            AUCOD as AUXcod,
            AUDES as AUXnom,
            AUNUM as AUXrnc,
            AUCAN as AUXdir,
            AUCTR as AUXtel,
            AUORI as origen,
            AUCHK as check_status,
            AUDIS as disponible,
            CASE 
                WHEN LEN(RTRIM(CAST(AUNUM as varchar))) = 11 THEN 'RNC'
                WHEN LEN(RTRIM(CAST(AUNUM as varchar))) = 13 THEN 'CED'
                ELSE 'RNC'
            END as tipo_documento
        FROM ffAuxiliar 
        WHERE AUCOD = :clienteId";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':clienteId' => $clienteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getTableName()
    {
        return "ffAuxiliar";
    }

    public function getDBConfigName()
    {
        return "oldStoneDB";
    }
}