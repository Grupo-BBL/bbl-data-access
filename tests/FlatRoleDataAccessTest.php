<?php

use PHPUnit\Framework\TestCase;

class FlatRoleDataAccessTest extends TestCase
{
    private $flatRoleDataAccess;

    protected function setUp(): void
    {
        $this->flatRoleDataAccess = $this->getMockBuilder(FlatRoleDataAccess::class)
            ->onlyMethods(['roleRelationsForUser', 'insert', 'delete'])
            ->getMock();
    }

    public function testAssignRolesToUser()
    {
        $userID = 1;
        $roles = [2, 3, 4];

        $currentRoles = [
            ['user_id' => 1, 'role_id' => 2],
        ];

        $this->flatRoleDataAccess->expects($this->once())
            ->method('roleRelationsForUser')
            ->with($userID)
            ->willReturn($currentRoles);

        $this->flatRoleDataAccess->expects($this->exactly(2))
            ->method('insert')
            ->withConsecutive(
                [['user_id' => 1, 'role_id' => 3]],
                [['user_id' => 1, 'role_id' => 4]]
            );

        $result = $this->flatRoleDataAccess->assignRolesToUser($userID, $roles);

        $this->assertTrue($result);
    }

    public function testRemoveRoleFromUser()
    {
        $user = ['id' => 1];
        $roleToRemove = ['id' => 2];

        $userRoles = [
            ['user_id' => 1, 'role_id' => 2],
            ['user_id' => 1, 'role_id' => 3],
        ];

        $this->flatRoleDataAccess->expects($this->once())
            ->method('roleRelationsForUser')
            ->with($user)
            ->willReturn($userRoles);

        $this->flatRoleDataAccess->expects($this->once())
            ->method('delete')
            ->with(['user_id' => 1, 'role_id' => 2]);

        $result = $this->flatRoleDataAccess->removeRoleFromUser($user, $roleToRemove);

        $this->assertTrue($result);
    }

    public function testRemoveRoleFromUserRoleNotFound()
    {
        $user = ['id' => 1];
        $roleToRemove = ['id' => 4];

        $userRoles = [
            ['user_id' => 1, 'role_id' => 2],
            ['user_id' => 1, 'role_id' => 3],
        ];

        $this->flatRoleDataAccess->expects($this->once())
            ->method('roleRelationsForUser')
            ->with($user)
            ->willReturn($userRoles);

        $this->flatRoleDataAccess->expects($this->never())
            ->method('delete');

        $result = $this->flatRoleDataAccess->removeRoleFromUser($user, $roleToRemove);

        $this->assertFalse($result);
    }
}