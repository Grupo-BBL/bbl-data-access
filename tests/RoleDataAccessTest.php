<?php

use PHPUnit\Framework\TestCase;

class RoleDataAccessTest extends TestCase
{
    protected $roleDataAccess;
    protected $pdo;

    protected function setUp(): void
    {
        // Configurar una conexión PDO para pruebas
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Crear tablas necesarias para las pruebas
        $this->pdo->exec("
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY,
                name TEXT
            );
            CREATE TABLE permissions (
                id INTEGER PRIMARY KEY,
                name TEXT
            );
            CREATE TABLE role_permissions (
                role_id INTEGER,
                permission_id INTEGER,
                PRIMARY KEY (role_id, permission_id)
            );
        ");

        // Insertar datos de prueba
        $this->pdo->exec("
            INSERT INTO roles (id, name) VALUES (1, 'Admin');
            INSERT INTO permissions (id, name) VALUES (2, 'Edit');
        ");

        // Pasar ambos argumentos necesarios al constructor de RoleDataAccess
        $this->roleDataAccess = new RoleDataAccess($this->pdo, 'additional_config'); // Reemplaza 'additional_config' con el valor adecuado
    }

    public function testAssignToOneSuccess()
    {
        $result = $this->roleDataAccess->assignToOne(1, 2);
        $this->assertTrue($result);

        // Verificar que el permiso fue asignado al rol
        $stmt = $this->pdo->query("SELECT * FROM role_permissions WHERE role_id = 1 AND permission_id = 2");
        $this->assertNotFalse($stmt->fetch());
    }

    public function testAssignToOneFailure()
    {
        // Intentar asignar un permiso a un rol inexistente
        $result = $this->roleDataAccess->assignToOne(999, 2);
        $this->assertFalse($result);

        // Verificar que el permiso no fue asignado
        $stmt = $this->pdo->query("SELECT * FROM role_permissions WHERE role_id = 999 AND permission_id = 2");
        $this->assertFalse($stmt->fetch());
    }

    public function testRemoveFromOneSuccess()
    {
        // Asignar primero el permiso al rol
        $this->pdo->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES (1, 2)");

        $result = $this->roleDataAccess->removeFromOne(1, 2);
        $this->assertTrue($result);

        // Verificar que el permiso fue removido del rol
        $stmt = $this->pdo->query("SELECT * FROM role_permissions WHERE role_id = 1 AND permission_id = 2");
        $this->assertFalse($stmt->fetch());
    }

    public function testRemoveFromOneFailure()
    {
        // Intentar remover un permiso de un rol inexistente
        $result = $this->roleDataAccess->removeFromOne(999, 2);
        $this->assertFalse($result);

        // Verificar que el permiso no fue removido
        $stmt = $this->pdo->query("SELECT * FROM role_permissions WHERE role_id = 999 AND permission_id = 2");
        $this->assertFalse($stmt->fetch());
    }
}