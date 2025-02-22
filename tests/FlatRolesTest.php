<?php

use PHPUnit\Framework\TestCase;
use Model\User\FlatRoles; 
class FlatRolesTest extends TestCase
{
    private $flatRoles;

    protected function setUp(): void
    {
        // Mock de PDO
        $mockPdo = $this->createMock(\PDO::class);
        $mockStatement = $this->createMock(\PDOStatement::class);
        
        // Configurar el mock PDO para devolver un statement mock
        $mockPdo->method('prepare')->willReturn($mockStatement);
        
        $this->flatRoles = $this->getMockBuilder(FlatRoles::class)
            ->setConstructorArgs([$mockPdo])
            ->onlyMethods(['roleRelationsForUser'])
            ->getMock();
    }

    public function testAssignRolesToUser()
    {
        // Arrange
        $userId = 1;
        $newRoles = [1, 2, 3];
        $existingRoles = [
            ['role_id' => 1]
        ];

        $this->flatRoles
            ->expects($this->once())
            ->method('roleRelationsForUser')
            ->with($userId)
            ->willReturn($existingRoles);

        // Act
        $result = $this->flatRoles->assignRolesToUser($userId, $newRoles);

        // Assert
        $this->assertTrue($result);
    }

    public function testAssignRolesToUserWithExistingRoles()
    {
        // Arrange
        $userId = 1;
        $newRoles = [1, 2];
        $existingRoles = [
            ['role_id' => 1],
            ['role_id' => 2]
        ];

        $this->flatRoles
            ->expects($this->once())
            ->method('roleRelationsForUser')
            ->with($userId)
            ->willReturn($existingRoles);

        // Act
        $result = $this->flatRoles->assignRolesToUser($userId, $newRoles);

        // Assert
        $this->assertTrue($result);
    }
}