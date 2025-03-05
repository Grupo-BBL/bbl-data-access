<?php

namespace Model\Base;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $connection = 'core';
    protected $table = 'tenants';
    
    protected $fillable = [
        'nombre',
        'slug',
        'database_name',
        'rnc',
        'es_emisor_electronico',
        'es_receptor_electronico',
        'url_recepcion',
        'url_aprobacion'
    ];

    protected $casts = [
        'es_emisor_electronico' => 'boolean',
        'es_receptor_electronico' => 'boolean',
    ];

    public function __construct()
    {
        $this->_tableName = 'tenants';
        $this->singleItemName = 'Tenant';
        $this->pluralItemName = 'Tenants';
        
        $this->dataMapping = [
            'nombre' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Nombre'
            ],
            'slug' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Slug'
            ],
            'database_name' => [
                'type' => 'string',
                'required' => true,
                'label' => 'Base de datos'
            ],
            'rnc' => [
                'type' => 'string',
                'required' => true,
                'label' => 'RNC',
                'validation' => '/^[0-9]{9,11}$/'
            ],
            'es_emisor_electronico' => [
                'type' => 'boolean',
                'required' => false,
                'label' => 'Es emisor electrónico'
            ],
            'es_receptor_electronico' => [
                'type' => 'boolean',
                'required' => false,
                'label' => 'Es receptor electrónico'
            ],
            'url_recepcion' => [
                'type' => 'string',
                'required' => false,
                'label' => 'URL de recepción'
            ],
            'url_aprobacion' => [
                'type' => 'string',
                'required' => false,
                'label' => 'URL de aprobación'
            ]
        ];
    }

    public function configure($databaseName = null)
    {
        $dbName = $databaseName ?? $this->valueForKey('database_name');
        if (!$dbName) {
            return false;
        }

        // Configurar la conexión para el tenant
        $config = [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $dbName,
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ];

        // TODO: Implementar la lógica de conexión usando el sistema existente
        return true;
    }

    public function insertFromForm($data)
    {
        // Validar RNC
        if (!preg_match('/^[0-9]{9,11}$/', $data['rnc'])) {
            throw new \Exception("RNC inválido. Debe tener entre 9 y 11 dígitos.");
        }

        // Generar slug si no existe
        if (empty($data['slug'])) {
            $data['slug'] = strtolower(preg_replace('/[^A-Za-z0-9-]/', '-', $data['nombre']));
        }

        // Generar nombre de base de datos si no existe
        if (empty($data['database_name'])) {
            $data['database_name'] = 'dgii_tenant_' . $data['slug'];
        }

        return parent::insertFromForm($data);
    }

    public function beforeInsert(&$data)
    {
        // Asegurarse de que los campos booleanos estén correctamente configurados
        $data['es_emisor_electronico'] = !empty($data['es_emisor_electronico']);
        $data['es_receptor_electronico'] = !empty($data['es_receptor_electronico']);
        return true;
    }
} 