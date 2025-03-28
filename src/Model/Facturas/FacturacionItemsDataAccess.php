<?php


use PDOException;
use Exception;

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
                yFGEseq,
                yFGEcod,
                yFGEdes,
                yFGEref,
                yFGEsta,
                COALESCE(yFGEval, 0) as yFGEval,
                COALESCE(yFGEcan, 0) as yFGEcan,
                COALESCE(yFGEsub, 0) as yFGEsub,
                COALESCE(yFGEexe, 0) as yFGEexe,
                yFGEnum,
                yFGEcli
            FROM ffItemsFG 
            WHERE yFGEnum = :facturaId 
            AND (yFGEdel IS NULL OR yFGEdel = 0)
            AND (
                yFGEcod IS NOT NULL AND yFGEcod <> ''
                OR yFGEdes IS NOT NULL AND yFGEdes <> ''
                OR yFGEval <> 0
                OR yFGEcan <> 0
                OR yFGEsub <> 0
            )
            ORDER BY yFGEseq ASC";
        
        try {
            error_log("Buscando items para número interno de factura: " . $facturaId);
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':facturaId' => $facturaId]);
            
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Número de items encontrados: " . count($items));
            
            // Si no hay items, retornar array vacío
            if (empty($items)) {
                error_log("No se encontraron items para la factura: " . $facturaId);
                return [];
            }
            
            // Log de los items encontrados
            foreach ($items as $index => $item) {
                error_log("Item " . ($index + 1) . ": " . json_encode($item));
            }
            
            return $items;
        } catch (PDOException $e) {
            error_log("Error al obtener items de factura: " . $e->getMessage());
            throw new Exception("Error al obtener items de factura: " . $e->getMessage());
        }
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