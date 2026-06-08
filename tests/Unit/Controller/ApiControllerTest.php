<?php

declare(strict_types=1);

namespace OCA\BesteSchule\Tests\Unit\Controller;

use OCA\BesteSchule\Controller\ApiController;
use OCA\BesteSchule\Db\FinalGradeMapper;
use OCA\BesteSchule\Db\GradeMapper;
use OCA\BesteSchule\Service\AccountService;
use OCA\BesteSchule\Service\SyncService;
use OCP\Calendar\IManager as ICalendarManager;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class ApiControllerTest extends TestCase
{
    private $request;
    private $userSession;
    private $groupManager;
    private $accountService;
    private $syncService;
    private $gradeMapper;
    private $finalGradeMapper;
    private $calendarManager;
    private $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(IRequest::class);
        $this->userSession = $this->createMock(IUserSession::class);
        $this->groupManager = $this->createMock(IGroupManager::class);
        $this->accountService = $this->createMock(AccountService::class);
        $this->syncService = $this->createMock(SyncService::class);
        $this->gradeMapper = $this->createMock(GradeMapper::class);
        $this->finalGradeMapper = $this->createMock(FinalGradeMapper::class);
        $this->calendarManager = $this->createMock(ICalendarManager::class);

        $this->controller = new ApiController(
            $this->request,
            $this->userSession,
            $this->groupManager,
            $this->accountService,
            $this->syncService,
            $this->gradeMapper,
            $this->finalGradeMapper,
            $this->calendarManager
        );
    }

    /**
     * @dataProvider gradeValueProvider
     */
    public function testParseGradeValue(string $value, ?float $expected): void
    {
        $reflection = new \ReflectionClass(ApiController::class);
        $method = $reflection->getMethod('parseGradeValue');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $value);
        $this->assertEquals($expected, $result);
    }

    public static function gradeValueProvider(): array
    {
        return [
            ['1', 1.0],
            ['1+', 0.75],
            ['1-', 1.25],
            ['2', 2.0],
            ['2+', 1.75],
            ['2-', 2.25],
            ['6', 6.0],
            ['2+/-', 2.0],
            ['invalid', null],
            ['', null],
            [' 1 ', 1.0],
        ];
    }

    public function testComputeAverage(): void
    {
        $reflection = new \ReflectionClass(ApiController::class);
        $method = $reflection->getMethod('computeAverage');
        $method->setAccessible(true);

        $grade1 = new \OCA\BesteSchule\Db\Grade();
        $grade1->setValue('1');

        $grade2 = new \OCA\BesteSchule\Db\Grade();
        $grade2->setValue('2');

        $grade3 = new \OCA\BesteSchule\Db\Grade();
        $grade3->setValue('invalid');

        $grades = [$grade1, $grade2, $grade3];

        $result = $method->invoke($this->controller, $grades);
        $this->assertEquals(1.5, $result);
    }

    public function testComputeAverageEmpty(): void
    {
        $reflection = new \ReflectionClass(ApiController::class);
        $method = $reflection->getMethod('computeAverage');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, []);
        $this->assertNull($result);
    }
}
