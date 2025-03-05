<?php

function gtk_log($toLog)
{
    echo $toLog."\n";
    error_log($toLog);
}

function getErrorLogPath()
{
    $repoRoot = dirname(__FILE__, 2);
    $GTK_DIRECTORY_SEPERATOR = DIRECTORY_SEPARATOR;
    return $repoRoot.$GTK_DIRECTORY_SEPERATOR."seed.log";
}

ini_set('memory_limit', '1G');
ini_set("error_log", getErrorLogPath());
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__FILE__, 2)."/vendor/autoload.php";

// Configuración de bases de datos
$config = [
    'core' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'database' => 'dgii_core',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ]
];

// Crear la base de datos core
$mysql = new PDO(
    "mysql:host={$config['core']['host']}", 
    $config['core']['username'], 
    $config['core']['password']
);

gtk_log("Creando base de datos core...");
$mysql->exec("CREATE DATABASE IF NOT EXISTS {$config['core']['database']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Conectar a la base de datos core
$mysql = new PDO(
    "mysql:host={$config['core']['host']};dbname={$config['core']['database']}", 
    $config['core']['username'], 
    $config['core']['password']
);

// Crear tablas del core
gtk_log("Creando tablas del core...");

// Tabla de tenants (organizaciones)
$mysql->exec("
    CREATE TABLE IF NOT EXISTS tenants (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        slug VARCHAR(50) UNIQUE NOT NULL,
        database_name VARCHAR(100) NOT NULL,
        rnc VARCHAR(11) UNIQUE NOT NULL,
        es_emisor_electronico BOOLEAN DEFAULT FALSE,
        es_receptor_electronico BOOLEAN DEFAULT FALSE,
        url_recepcion VARCHAR(255),
        url_aprobacion VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
");

// Tabla de contribuyentes (RNC compartidos)
$mysql->exec("
    CREATE TABLE IF NOT EXISTS contribuyentes (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        rnc VARCHAR(11) UNIQUE NOT NULL,
        razon_social VARCHAR(150) NOT NULL,
        nombre_comercial VARCHAR(150),
        tipo_contribuyente ENUM('PERSONA_FISICA', 'PERSONA_JURIDICA') NOT NULL,
        estado ENUM('activo', 'inactivo') DEFAULT 'activo',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
");

// Tabla de roles
$mysql->exec("
    CREATE TABLE IF NOT EXISTS roles (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(50) NOT NULL,
        descripcion TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
");

// Tabla de permisos
$mysql->exec("
    CREATE TABLE IF NOT EXISTS permisos (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        descripcion TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
");

// Tabla de roles_permisos
$mysql->exec("
    CREATE TABLE IF NOT EXISTS roles_permisos (
        rol_id BIGINT UNSIGNED NOT NULL,
        permiso_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (rol_id, permiso_id),
        FOREIGN KEY (rol_id) REFERENCES roles(id),
        FOREIGN KEY (permiso_id) REFERENCES permisos(id)
    ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
");

// Crear roles básicos
gtk_log("Creando roles básicos...");
$roles = [
    ['nombre' => 'Dev', 'descripcion' => 'Desarrollador con acceso total'],
    ['nombre' => 'Admin', 'descripcion' => 'Administrador del sistema'],
    ['nombre' => 'Contador', 'descripcion' => 'Usuario que maneja facturación'],
    ['nombre' => 'Consulta', 'descripcion' => 'Usuario con acceso de solo lectura']
];

foreach ($roles as $role) {
    $mysql->exec("INSERT IGNORE INTO roles (nombre, descripcion) VALUES ('{$role['nombre']}', '{$role['descripcion']}')");
}

// Función para crear base de datos de tenant
function createTenantDatabase($config, $tenantSlug) {
    gtk_log("Creando base de datos para tenant: {$tenantSlug}");
    
    $dbName = "dgii_tenant_{$tenantSlug}";
    $mysql = new PDO(
        "mysql:host={$config['core']['host']}", 
        $config['core']['username'], 
        $config['core']['password']
    );
    
    $mysql->exec("CREATE DATABASE IF NOT EXISTS {$dbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Conectar a la base de datos del tenant
    $mysql = new PDO(
        "mysql:host={$config['core']['host']};dbname={$dbName}", 
        $config['core']['username'], 
        $config['core']['password']
    );
    
    // Crear tablas específicas del tenant
    
    // Tabla de secuencias e-NCF
    $mysql->exec("
        CREATE TABLE IF NOT EXISTS secuencias_encf (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tipo_comprobante VARCHAR(2) NOT NULL,
            serie CHAR(1) NOT NULL,
            secuencial_desde BIGINT NOT NULL,
            secuencial_hasta BIGINT NOT NULL,
            secuencial_actual BIGINT,
            fecha_vencimiento DATE NOT NULL,
            activo BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    
    // Tabla de comprobantes fiscales
    $mysql->exec("
        CREATE TABLE IF NOT EXISTS comprobantes_fiscales (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            encf VARCHAR(13) NOT NULL UNIQUE,
            tipo_comprobante VARCHAR(2) NOT NULL,
            fecha_emision DATE NOT NULL,
            fecha_firma TIMESTAMP NOT NULL,
            rnc_emisor VARCHAR(11) NOT NULL,
            rnc_receptor VARCHAR(11) NOT NULL,
            monto_gravado DECIMAL(18,2) DEFAULT 0,
            monto_exento DECIMAL(18,2) DEFAULT 0,
            total_itbis DECIMAL(18,2) DEFAULT 0,
            monto_total DECIMAL(18,2) NOT NULL,
            estado ENUM('borrador', 'firmado', 'enviado', 'aceptado', 'rechazado') DEFAULT 'borrador',
            xml_documento LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    
    // Tabla de detalles de comprobantes
    $mysql->exec("
        CREATE TABLE IF NOT EXISTS detalles_comprobantes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            comprobante_id BIGINT UNSIGNED NOT NULL,
            numero_linea INTEGER NOT NULL,
            descripcion VARCHAR(1000) NOT NULL,
            cantidad DECIMAL(18,2) NOT NULL,
            precio_unitario DECIMAL(18,2) NOT NULL,
            descuento DECIMAL(18,2) DEFAULT 0,
            monto_item DECIMAL(18,2) NOT NULL,
            tipo_ingreso CHAR(2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (comprobante_id) REFERENCES comprobantes_fiscales(id)
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    
    return $dbName;
}

// Crear tenant de ejemplo
gtk_log("Creando tenant de ejemplo...");
$tenantSlug = 'empresa1';
$dbName = createTenantDatabase($config, $tenantSlug);

$mysql = new PDO(
    "mysql:host={$config['core']['host']};dbname={$config['core']['database']}", 
    $config['core']['username'], 
    $config['core']['password']
);

$mysql->exec("
    INSERT IGNORE INTO tenants (nombre, slug, database_name, rnc) 
    VALUES ('Empresa de Ejemplo', '{$tenantSlug}', '{$dbName}', '101123456')
");

gtk_log("Seed completado exitosamente.");
die("Finished running seed.\n");