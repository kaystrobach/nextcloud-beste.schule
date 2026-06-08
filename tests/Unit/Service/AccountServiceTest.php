<?php

declare(strict_types=1);

namespace OCA\BesteSchule\Tests\Unit\Service;

use OCA\BesteSchule\Db\Account;
use OCA\BesteSchule\Db\AccountMapper;
use OCA\BesteSchule\Exception\BesteSchuleException;
use OCA\BesteSchule\Service\AccountService;
use OCA\BesteSchule\Service\BesteSchuleService;
use OCP\IConfig;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;

class AccountServiceTest extends TestCase
{
    private $accountMapper;
    private $apiService;
    private $crypto;
    private $config;
    private $service;

    protected function setUp(): void
    {
        $this->accountMapper = $this->createMock(AccountMapper::class);
        $this->apiService = $this->createMock(BesteSchuleService::class);
        $this->crypto = $this->createMock(ICrypto::class);
        $this->config = $this->createMock(IConfig::class);

        $this->service = new AccountService(
            $this->accountMapper,
            $this->apiService,
            $this->crypto,
            $this->config
        );
    }

    public function testCreateAccountAutoSelectStudent(): void
    {
        $userId = 'testuser';
        $token = 'testtoken';

        $students = [
            ['id' => 123, 'forename' => 'John', 'name' => 'Doe'],
            ['id' => 456, 'forename' => 'Jane', 'name' => 'Smith'],
        ];

        $this->apiService->expects($this->once())
            ->method('students')
            ->with($token)
            ->willReturn($students);

        $this->crypto->expects($this->once())
            ->method('encrypt')
            ->with($token)
            ->willReturn('encrypted-token');

        $this->accountMapper->expects($this->once())
            ->method('insert')
            ->with($this->callback(function (Account $account) use ($userId) {
                return $account->getUserId() === $userId &&
                       $account->getStudentId() === 123 &&
                       $account->getStudentName() === 'John Doe' &&
                       $account->getAccessToken() === 'encrypted-token';
            }))
            ->willReturnArgument(0);

        $result = $this->service->createAccount($userId, $token);

        $this->assertInstanceOf(Account::class, $result);
        $this->assertEquals(123, $result->getStudentId());
        $this->assertEquals('John Doe', $result->getStudentName());
    }

    public function testCreateAccountSpecificStudent(): void
    {
        $userId = 'testuser';
        $token = 'testtoken';
        $studentId = 456;

        $students = [
            ['id' => 123, 'forename' => 'John', 'name' => 'Doe'],
            ['id' => 456, 'forename' => 'Jane', 'name' => 'Smith'],
        ];

        $this->apiService->method('students')->willReturn($students);
        $this->crypto->method('encrypt')->willReturn('encrypted-token');
        $this->accountMapper->method('insert')->willReturnArgument(0);

        $result = $this->service->createAccount($userId, $token, $studentId);

        $this->assertEquals(456, $result->getStudentId());
        $this->assertEquals('Jane Smith', $result->getStudentName());
    }

    public function testCreateAccountNoStudents(): void
    {
        $this->apiService->method('students')->willReturn([]);

        $this->expectException(BesteSchuleException::class);
        $this->expectExceptionMessage('No students accessible with this token.');

        $this->service->createAccount('user', 'token');
    }

    public function testUpdateAccountSyncInterval(): void
    {
        $userId = 'testuser';
        $accountId = 1;
        $account = new Account();
        $account->setSyncInterval(24);

        $this->accountMapper->method('findByUserAndId')->with($userId, $accountId)->willReturn($account);
        $this->accountMapper->method('update')->willReturnArgument(0);

        $result = $this->service->updateAccount($userId, $accountId, ['sync_interval' => 12]);
        $this->assertEquals(12, $result->getSyncInterval());

        $resultLow = $this->service->updateAccount($userId, $accountId, ['sync_interval' => 0]);
        $this->assertEquals(1, $resultLow->getSyncInterval());
    }
}
