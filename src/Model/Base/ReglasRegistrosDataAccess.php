<?php

class ReglasRegistrosDataAccess extends DataAccess
{
    public function register()
    {
        $columnMappings = [
            new GTKColumnMapping($this, "id", [
                "isPrimaryKey" => true,
                "isAutoIncrement" => true,
                "hideOnForms" => true,
                "formLabel" => "ID"
            ]),
            new GTKColumnMapping($this, "nombre", [
                "isNullable" => false,
                "formLabel" => "Nombre de la Regla"
            ]),
            new GTKColumnMapping($this, "modelo", [
                "isNullable" => false,
                "formLabel" => "Modelo",
                'formInputType' => 'select',
                'customInputFunctionClass' => null,
                'customInputFunctionScope' => "object",
                'customInputFunctionObject' => DataAccessManager::get('DataAccessManager'),
                'customInputFunction' => "generateSelectForDataAccessors",
            ]),
            new GTKColumnMapping($this, "role_id", [
                "formLabel" => "Rol",
                'formInputType' => 'select',
                'customInputFunctionClass' => null,
                'customInputFunctionScope' => "object",
                'customInputFunctionObject' => DataAccessManager::get('DataAccessManager'),
                'customInputFunction' => "generateSelectForRoles",
            ]),
            new GTKColumnMapping($this, "expresion_dominio", [
                "isNullable" => false,
                "formLabel" => "Expresión de Dominio",
                'formInputType' => 'textarea',
                'validation' => 'json',
            ]),
            new GTKColumnMapping($this, "aplicar_lectura", [
                "formLabel" => "Aplicar a Lectura",
                'formInputType' => 'select',
                'possibleValues' => [
                    true => ['label' => 'SÍ'],
                    false => ['label' => 'NO'],
                ],
                'defaultValue' => true
            ]),
            new GTKColumnMapping($this, "aplicar_escritura", [
                "formLabel" => "Aplicar a Escritura",
                'formInputType' => 'select',
                'possibleValues' => [
                    true => ['label' => 'SÍ'],
                    false => ['label' => 'NO'],
                ],
                'defaultValue' => true
            ]),
            new GTKColumnMapping($this, "aplicar_creacion", [
                "formLabel" => "Aplicar a Creación",
                'formInputType' => 'select',
                'possibleValues' => [
                    true => ['label' => 'SÍ'],
                    false => ['label' => 'NO'],
                ],
                'defaultValue' => true
            ]),
            new GTKColumnMapping($this, "aplicar_eliminacion", [
                "formLabel" => "Aplicar a Eliminación",
                'formInputType' => 'select',
                'possibleValues' => [
                    true => ['label' => 'SÍ'],
                    false => ['label' => 'NO'],
                ],
                'defaultValue' => true
            ]),
            new GTKColumnMapping($this, "es_global", [
                "formLabel" => "Es Regla Global",
                'formInputType' => 'select',
                'possibleValues' => [
                    true => ['label' => 'SÍ'],
                    false => ['label' => 'NO'],
                ],
                'defaultValue' => false,
                'helpText' => 'Las reglas globales actúan como restricciones (AND) y se aplican a todos los usuarios.'
            ]),
            new GTKColumnMapping($this, "is_active", [
                "formLabel" => "Está Activo",
                'formInputType' => 'select',
                'possibleValues' => [
                    true => ['label' => 'SÍ'],
                    false => ['label' => 'NO'],
                ],
                'defaultValue' => true
            ]),
            new GTKColumnMapping($this, "date_created", [
                "hideOnForms" => true,
                "formLabel" => "Fecha Creación"
            ]),
            new GTKColumnMapping($this, "date_modified", [
                "hideOnForms" => true,
                "formLabel" => "Fecha Modificación"
            ]),
        ];

        $this->dataMapping = new GTKDataSetMapping($this, $columnMappings);
        $this->defaultOrderByColumnKey = "nombre";
        $this->defaultOrderByOrder = "ASC";
    }

    public function migrate()
    {
        $this->getDB()->query("CREATE TABLE IF NOT EXISTS {$this->tableName()} (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(255) NOT NULL,
            modelo VARCHAR(100) NOT NULL,
            role_id INT NULL,
            expresion_dominio TEXT NOT NULL,
            aplicar_lectura BOOLEAN DEFAULT TRUE,
            aplicar_escritura BOOLEAN DEFAULT TRUE,
            aplicar_creacion BOOLEAN DEFAULT TRUE,
            aplicar_eliminacion BOOLEAN DEFAULT TRUE,
            es_global BOOLEAN DEFAULT FALSE,
            is_active BOOLEAN DEFAULT TRUE,
            date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            date_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (role_id) REFERENCES roledataaccess(id) ON DELETE CASCADE
        )");

        $this->getDB()->query("CREATE INDEX IF NOT EXISTS idx_reglas_modelo ON {$this->tableName()}(modelo, is_active)");
        $this->getDB()->query("CREATE INDEX IF NOT EXISTS idx_reglas_role ON {$this->tableName()}(role_id, is_active)");
        $this->getDB()->query("CREATE INDEX IF NOT EXISTS idx_reglas_global ON {$this->tableName()}(es_global, is_active)");
    }

    public function getReglasAplicables($modelo, $operacion, $roleIds = [])
    {
        $options = [
            'where' => [
                'modelo' => $modelo,
                'is_active' => 1,
            ]
        ];
        
        if (in_array($operacion, ['lectura', 'escritura', 'creacion', 'eliminacion'])) {
            $options['where']["aplicar_{$operacion}"] = 1;
        }
        
        if (!empty($roleIds)) {
            $rolesStr = implode(',', array_map('intval', $roleIds));
            $options['whereCustom'] = "(es_global = 1 OR role_id IN ({$rolesStr}))";
        } else {
            $options['where']['es_global'] = 1;
        }
        
        return $this->getAll($options);
    }

    public function validateExpresionDominio($value, $record, $columnKey)
    {
        if (empty($value)) {
            return "La expresión de dominio no puede estar vacía";
        }
        
        json_decode($value);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return "La expresión de dominio debe ser un JSON válido";
        }
        
        return true;
    }

    public function beforeSave(&$record)
    {
        if (isset($record['es_global']) && $record['es_global'] == 1) {
            $record['role_id'] = null;
        } else if ((!isset($record['es_global']) || $record['es_global'] != 1) && empty($record['role_id'])) {
            throw new Exception("Las reglas no globales deben tener un rol asignado");
        }
        
        return true;
    }
    

    public function createRegla($data)
    {
        return $this->insert($data);
    }

    public function updateRegla($id, $data)
    {
        return $this->update($id, $data);
    }

    public function deleteRegla($id)
    {
        return $this->delete($id);
    }

    public function getReglaById($id)
    {
        return $this->getById($id);
    }
}