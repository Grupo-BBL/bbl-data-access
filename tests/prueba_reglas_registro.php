<?php
/**
 * Script de prueba para validar el sistema de reglas de registro
 * 
 * @file prueba_reglas_registro.php
 */

// Cargar dependencias
require_once 'config.php';
require_once 'vendor/autoload.php';

// Inicializar DataAccessManager si es necesario
// DataAccessManager::init();

// Registrar el nuevo DataAccess
DataAccessManager::register("reglas_registros", new ReglasRegistrosDataAccess());

// Función de test
function testReglaRegistroServicio() {
    echo "=== Iniciando prueba de ReglaRegistroServicio ===\n";
    
    // 1. Crear algunas reglas de prueba
    $reglasDA = DataAccessManager::get("reglas_registros");
    
    // Eliminar reglas existentes para la prueba
    $reglasExistentes = $reglasDA->getAll(['where' => [
        'modelo' => 'clientes'
    ]]);
    
    foreach ($reglasExistentes as $regla) {
        $reglasDA->delete($regla['id']);
    }
    
    // Crear una regla global que restringe clientes inactivos
    $reglaGlobal = [
        'nombre' => 'Ocultar clientes inactivos',
        'modelo' => 'clientes',
        'expresion_dominio' => json_encode(['is_active', '=', true]),
        'aplicar_lectura' => true,
        'aplicar_escritura' => true,
        'aplicar_creacion' => false,
        'aplicar_eliminacion' => true,
        'es_global' => true
    ];
    
    $reglaGlobalId = $reglasDA->create($reglaGlobal);
    echo "- Regla global creada con ID: {$reglaGlobalId}\n";
    
    // Crear una regla específica para un rol (asumimos que existe un rol con ID 1)
    $rolId = 1; // Cambiar según la estructura real
    $reglaEspecifica = [
        'nombre' => 'Ver solo clientes asignados',
        'modelo' => 'clientes',
        'role_id' => $rolId,
        'expresion_dominio' => json_encode(['gestor_id', '=', '${user_id}']),
        'aplicar_lectura' => true,
        'aplicar_escritura' => true,
        'aplicar_creacion' => false,
        'aplicar_eliminacion' => true,
        'es_global' => false
    ];
    
    $reglaEspecificaId = $reglasDA->create($reglaEspecifica);
    echo "- Regla específica creada con ID: {$reglaEspecificaId}\n";
    
    // 2. Crear datos de prueba
    $clientes = [
        [
            'id' => 1,
            'nombre' => 'Cliente 1',
            'is_active' => true,
            'gestor_id' => 1
        ],
        [
            'id' => 2,
            'nombre' => 'Cliente 2',
            'is_active' => false,
            'gestor_id' => 1
        ],
        [
            'id' => 3,
            'nombre' => 'Cliente 3',
            'is_active' => true,
            'gestor_id' => 2
        ],
        [
            'id' => 4,
            'nombre' => 'Cliente 4',
            'is_active' => true,
            'gestor_id' => 3
        ]
    ];
    
    // 3. Probar filtrado
    $servicio = new ReglaRegistroServicio();
    
    // Usuario 1 con rol 1: debería ver Cliente 1 (activo y asignado a él)
    $userId = 1;
    $clientesFiltrados = $servicio->filtrarRegistrosPorReglas('clientes', $clientes, $userId, 'lectura');
    
    echo "- Usuario 1 puede ver " . count($clientesFiltrados) . " clientes\n";
    foreach ($clientesFiltrados as $cliente) {
        echo "  * Cliente {$cliente['id']}: {$cliente['nombre']}\n";
    }
    
    // Usuario 2 con rol 1: debería ver Cliente 3 (activo y asignado a él)
    $userId = 2;
    $clientesFiltrados = $servicio->filtrarRegistrosPorReglas('clientes', $clientes, $userId, 'lectura');
    
    echo "- Usuario 2 puede ver " . count($clientesFiltrados) . " clientes\n";
    foreach ($clientesFiltrados as $cliente) {
        echo "  * Cliente {$cliente['id']}: {$cliente['nombre']}\n";
    }
    
    // 4. Probar operación específica
    $userId = 1;
    $operacion = 'escritura';
    
    foreach ($clientes as $cliente) {
        $resultado = $servicio->filtrarRegistrosPorReglas('clientes', [$cliente], $userId, $operacion);
        $permitido = !empty($resultado);
        echo "- Usuario 1 puede realizar {$operacion} en Cliente {$cliente['id']}: " . ($permitido ? "SÍ" : "NO") . "\n";
    }
    
    // 5. Limpiar datos de prueba
    $reglasDA->delete($reglaGlobalId);
    $reglasDA->delete($reglaEspecificaId);
    
    echo "=== Prueba completada ===\n";
}

// Ejecutar prueba
testReglaRegistroServicio();