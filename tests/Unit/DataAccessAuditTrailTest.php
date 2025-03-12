<?php  

use PHPUnit\Framework\TestCase;

class DataAccessAuditTrailTest extends TestCase 
{
    private $auditTrail;
    private $testDataAccess;
    private $mockUser;

    protected function setUp(): void 
    {
        parent::setUp();
        $this->auditTrail = DAM::get('data_access_audit_trail');
        $this->testDataAccess = DAM::get('testable_items');
        
        // Mock user data
        $this->mockUser = [
            'id' => 1,
            'email' => 'test@example.com'
        ];
    }

    public function testAutomaticAuditOnInsert() 
    {
        // Insert a record through normal DataAccess methods
        $newData = [
            'a'  => 'Test User',
            'b' => 'test@example.com'
        ];

        $recordId = $this->testDataAccess->insert($newData);
        $this->assertNotNull($recordId);

        // Verify the audit trail was created

        $select = new SelectQuery($this->testDataAccess);
        $select->where('data_access_name', '=', 'TestableDataAccess');
        $select->where('record_id',        '=', $recordId);
        $select->where('action_type',      '=', 'INSERT');

        $audits = $select->executeAndReturnAll();

        $this->assertCount(1, $audits);
        $audit = $audits[0];
        
        // Verify audit contents
        $changes = json_decode($audit['changes'], true);
        $this->assertEquals($newData['name'], $changes['name']);
        $this->assertEquals($newData['email'], $changes['email']);
    }

    public function testAutomaticAuditOnUpdate() 
    {
        // First insert a record
        $recordId = $this->testDataAccess->insert([
            'a' => 'Original Name',
            'b' => 'original@example.com'
        ]);

        // Update the record
        $updateData = [
            'a' => 'Updated Name',
            'b' => 'updated@example.com'
        ];
        
        $this->testDataAccess->update($recordId, $updateData);

        $select = new SelectQuery($this->testDataAccess);
        $select->where('data_access_name', '=', 'TestableDataAccess');
        $select->where('record_id',        '=', $recordId);
        $select->where('action_type',      '=', 'UPDATE');

        $audits = $select->executeAndReturnAll();

        $this->assertCount(1, $audits);
        $audit = $audits[0];
        
        // Verify changes show before/after
        $changes = json_decode($audit['changes'], true);
        $this->assertEquals('Original Name', $changes['name']['old']);
        $this->assertEquals('Updated Name', $changes['name']['new']);
        $this->assertEquals('original@example.com', $changes['email']['old']);
        $this->assertEquals('updated@example.com', $changes['email']['new']);
    }

    protected function tearDown(): void 
    {
        parent::tearDown();
    }
}