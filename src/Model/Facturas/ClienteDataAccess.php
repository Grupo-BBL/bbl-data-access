<?php

class ClienteDataAccess extends DataAccess
{
    public function register()
    {
        $columnMappings = [
            new GTKColumnMapping($this, "stCCODE", [
                "isPrimaryKey" => true,
                "columnType" => "TEXT",
                "maxLength" => 50
            ]),
            new GTKColumnMapping($this, "stCCDESCRIP", [
                "columnType" => "TEXT",
                "maxLength" => 255
            ]),
            new GTKColumnMapping($this, "stCCONTACTO", [
                "columnType" => "TEXT",
                "maxLength" => 100
            ]),
            new GTKColumnMapping($this, "stCRNC", [
                "columnType" => "TEXT",
                "maxLength" => 50
            ]),
            new GTKColumnMapping($this, "stFZONAFRANCA", [
                "columnType" => "TEXT",
                "maxLength" => 1
            ]),
            new GTKColumnMapping($this, "stFDTIPO", [
                "columnType" => "INTEGER"
            ]),
            new GTKColumnMapping($this, "stFDGUBERNAMENTAL", [
                "columnType" => "TEXT",
                "maxLength" => 1
            ]),
            new GTKColumnMapping($this, "stFCFAX", [
                "columnType" => "TEXT",
                "maxLength" => 50
            ]),
            new GTKColumnMapping($this, "stCDIRECCION1", [
                "columnType" => "TEXT",
                "maxLength" => 255
            ]),
            new GTKColumnMapping($this, "stCDIRECCION2", [
                "columnType" => "TEXT",
                "maxLength" => 255
            ]),
            new GTKColumnMapping($this, "stCDIRECCION3", [
                "columnType" => "TEXT",
                "maxLength" => 255
            ]),
            new GTKColumnMapping($this, "stFCTEL1", [
                "columnType" => "TEXT",
                "maxLength" => 50
            ]),
            new GTKColumnMapping($this, "stFCRNC_CED", [
                "columnType" => "TEXT",
                "maxLength" => 50
            ]),
            new GTKColumnMapping($this, "stTipoNCF", [
                "columnType" => "INTEGER"
            ]),
            new GTKColumnMapping($this, "nombre_o_razon_social", [
                "columnType" => "TEXT",
                "maxLength" => 255
            ]),
            new GTKColumnMapping($this, "nombre_comercial", [
                "columnType" => "TEXT",
                "maxLength" => 255
            ]),
            new GTKColumnMapping($this, "email", [
                "columnType" => "TEXT",
                "maxLength" => 255
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
            stCCODE,
            COALESCE(nombre_o_razon_social, stCCDESCRIP) as nombre,
            stCRNC,
            stFCRNC_CED,
            CASE 
                WHEN LEN(RTRIM(stFCRNC_CED)) = 11 THEN 'RNC'
                WHEN LEN(RTRIM(stFCRNC_CED)) = 13 THEN 'CED'
                ELSE 'RNC'
            END as tipo_documento,
            stCDIRECCION1 + 
                CASE WHEN stCDIRECCION2 IS NOT NULL AND stCDIRECCION2 <> '' 
                    THEN ', ' + stCDIRECCION2 
                    ELSE '' 
                END +
                CASE WHEN stCDIRECCION3 IS NOT NULL AND stCDIRECCION3 <> '' 
                    THEN ', ' + stCDIRECCION3 
                    ELSE '' 
                END as direccion,
            stFCTEL1 as telefono,
            email
        FROM FCLIENTE 
        WHERE stCCODE = :clienteId";
        
        try {
            error_log("Buscando cliente con ID: " . $clienteId);
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':clienteId' => $clienteId]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                error_log("No se encontró el cliente: " . $clienteId);
                return null;
            }
            
            error_log("Cliente encontrado: " . json_encode($cliente));
            return $cliente;
        } catch (PDOException $e) {
            error_log("Error al obtener información del cliente: " . $e->getMessage());
            throw new Exception("Error al obtener información del cliente: " . $e->getMessage());
        }
    }

    public function getTableName()
    {
        return "FCLIENTE";
    }

    public function getDBConfigName()
    {
        return "StwdDataBase_A_CARTY";
    }
} 