<?php

use PHPUnit\Framework\TestCase;

// Configurar la variable global antes de cualquier otra cosa
$_GLOBALS["ENV_FILE_PATH"] = __DIR__ . '/env.test.php';

require_once __DIR__ . '/../src/Model/User/CustomRoleDataAccess.php';

class CustomRoleDataAccessTest extends TestCase
{
    private $pdo;
    private $customRoleDataAccess;

    protected function setUp(): void
    {
        // Configurar una base de datos en memoria para pruebas
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Crear tablas de ejemplo
        $this->pdo->exec("CREATE TABLE pcs (id INTEGER PRIMARY KEY, name TEXT)");
        $this->pdo->exec("CREATE TABLE parts (id INTEGER PRIMARY KEY, name TEXT)");
        $this->pdo->exec("CREATE TABLE pc_part (pc_id INTEGER, part_id INTEGER, date_created TEXT)");

        // Insertar datos de ejemplo
        $this->pdo->exec("INSERT INTO pcs (id, name) VALUES (1, 'Gaming PC')");
        $this->pdo->exec("INSERT INTO parts (id, name) VALUES (1, 'Graphics Card')");

        // Inicializar la clase a probar
        $this->customRoleDataAccess = new CustomRoleDataAccess(
            $this->pdo,
            'pcs',
            'pc_part',
            ['one' => 'pc_id', 'mucho' => 'part_id']
        );
    }


    public function testAssignToOne()
    {
        $result = $this->customRoleDataAccess->assignToOne(1, 1);
        $this->assertTrue($result);

        // Verificar que la relación se haya insertado
        $stmt = $this->pdo->query("SELECT * FROM pc_part WHERE pc_id = 1 AND part_id = 1");
        $this->assertEquals(1, $stmt->rowCount());
    }

    public function testRemoveFromOne()
    {
        // Primero asignar para luego eliminar
        $this->customRoleDataAccess->assignToOne(1, 1);

        $result = $this->customRoleDataAccess->removeFromOne(1, 1);
        $this->assertTrue($result);

        // Verificar que la relación se haya eliminado
        $stmt = $this->pdo->query("SELECT * FROM pc_part WHERE pc_id = 1 AND part_id = 1");
        $this->assertEquals(0, $stmt->rowCount());
    }
}