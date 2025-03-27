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
            new GTKColumnMapping($this, "FGEtfa", [
                "columnType" => "TEXT",
                "maxLength" => 10
            ]),
            new GTKColumnMapping($this, "FGEdtf", [
                "columnType" => "TEXT",
                "maxLength" => 80
            ]),
            new GTKColumnMapping($this, "FGEtex", [
                "columnType" => "DECIMAL"
            ]),
            new GTKColumnMapping($this, "FGEtgr", [
                "columnType" => "DECIMAL"
            ]),
            new GTKColumnMapping($this, "FGEitb", [
                "columnType" => "DECIMAL"
            ]),
            new GTKColumnMapping($this, "FGEtot", [
                "columnType" => "DECIMAL"
            ]),
            new GTKColumnMapping($this, "FGEtas", [
                "columnType" => "DECIMAL"
            ]),
            new GTKColumnMapping($this, "FGEtdo", [
                "columnType" => "DECIMAL"
            ])
        ];
        
        $this->dataMapping = new GTKDataSetMapping($this, $columnMappings);
    }

    /**
     * Obtener información de pago de una factura
     */
    public function getInfoPago($facturaId)
    {
        $db = $this->getDB();
        $sql = "SELECT 
                COALESCE(FGEtfa, '1') as forma_pago,
                COALESCE(FGEdtf, '') as descripcion_forma_pago,
                COALESCE(FGEtdo, 0) as plazo,
                COALESCE(FGEtas, 0) as tasa,
                COALESCE(FGEtex, 0) as total_exento,
                COALESCE(FGEtgr, 0) as total_gravado,
                COALESCE(FGEitb, 0) as itbis,
                COALESCE(FGEtot, 0) as total,
                'DOP' as moneda
            FROM ffFactGral 
            WHERE FGEseq = :facturaId";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':facturaId' => $facturaId]);
        $pago = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pago) {
            return [
                'forma_pago' => '1',
                'descripcion_forma_pago' => '',
                'plazo' => 0,
                'tasa' => 0,
                'total_exento' => 0,
                'total_gravado' => 0,
                'itbis' => 0,
                'total' => 0,
                'moneda' => 'DOP'
            ];
        }

        return $pago;
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