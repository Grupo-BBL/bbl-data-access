<?php




class TestSelectFromWhere extends \PHPUnit\Framework\TestCase
{
    private $dataAccess;
    private $pdo;

    public function testTestWorks()
    {
        $this->assertTrue(false);
    }

    public function testSelectFromWhere()
    {
        $this->assertTrue(false);
    }
    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->dataAccess = new TestableDataAccess($this->pdo, [
            "tableName"      => "TestableDataAccess_".TestableDataAccess_generateMicroTimeUUID(),
            "runCreateTable" => true,
        ]);

        // $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        /*
        // Create the test table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->dataAccess->tableName()} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            a VARCHAR(255),
            b VARCHAR(255),
            date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            date_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        */
        // Insert test data
        $this->pdo->exec("INSERT INTO {$this->dataAccess->tableName()} (a, b) VALUES ('value1', 'value2')");
        $this->pdo->exec("INSERT INTO {$this->dataAccess->tableName()} (a, b) VALUES ('value3', 'value4')");
    }
    protected function tearDown(): void
    {
        // Drop the test table
        $this->pdo->exec("DROP TABLE IF EXISTS {$this->dataAccess->tableName()}");
    }
    public function testSelectAll()
    {
        $query = new SelectQuery($this->dataAccess);
        $results = $query->executeAndReturnAll();
        $this->assertCount(2, $results);
        $this->assertArrayHasKey('id', $results[0]);
        $this->assertArrayHasKey('a', $results[0]);
        $this->assertArrayHasKey('b', $results[0]);
    }
    public function testSelectWithWhere()
    {
        $query = new SelectQuery($this->dataAccess);
        $query->where('a', '=', 'value1');
        $results = $query->executeAndReturnAll();
        $this->assertCount(1, $results);
        $this->assertEquals('value1', $results[0]['a']);
    }
    public function testSelectWithMultipleWhereClauses()
    {
        $query = new SelectQuery($this->dataAccess);
        $query->where('a', '=', 'value1')
              ->where('b', '=', 'value2');
        $results = $query->executeAndReturnAll();
        $this->assertCount(1, $results);
        $this->assertEquals('value1', $results[0]['a']);
        $this->assertEquals('value2', $results[0]['b']);
    }
    public function testSelectWithOrderBy()
    {
        $query = new SelectQuery($this->dataAccess);
        $query->orderBy = [new OrderBy('a', 'DESC')];
        $results = $query->executeAndReturnAll();
        $this->assertCount(2, $results);
        $this->assertEquals('value3', $results[0]['a']);
        $this->assertEquals('value1', $results[1]['a']);
    }
    public function testSelectWithLimitAndOffset()
    {
        $query = new SelectQuery($this->dataAccess);
        $query->setLimit(1);
        $query->setOffset(1);
        $results = $query->executeAndReturnAll();
        $this->assertCount(1, $results);
        $this->assertEquals('value3', $results[0]['a']);
        $sql = $query->getSQL();
        $this->assertEquals("SELECT * FROM {$this->dataAccess->tableName()} LIMIT 1 OFFSET 1", $sql);
        $this->assertEquals("SELECT * FROM {$this->dataAccess->tableName()}", $sql);
    }
}
