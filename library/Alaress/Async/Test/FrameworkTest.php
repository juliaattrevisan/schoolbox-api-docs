<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Async\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

#[CoversClass(Framework::class)]
class FrameworkTest extends PHPUnitTestCase
{
    private string $rootDir;

    public function testBuildTestCaseInstantiatesPhpunitTestCase(): void
    {
        $className = $this->writeNotificationTestFixture();
        $framework = new Framework(
            $this->createStub(ContainerInterface::class),
            $this->createStub(LoopInterface::class),
            $this->createStub(LoggerInterface::class),
            rootDir: $this->rootDir,
        );

        $testCase = $framework->buildTestCase($className);

        $this->assertInstanceOf($className, $testCase);
    }

    public function testBuildTestCaseRunsSetUpBeforePrepare(): void
    {
        $className = $this->writeNotificationTestFixture(
            <<<'PHP'
    public static bool $setUpCalled = false;
    public static bool $prepareRanAfterSetUp = false;

    public function setUp(): void
    {
        self::$setUpCalled = true;
    }

    public function requiredDaemons()
    {
        return [self::DAEMON_SCHEDULE];
    }

    public function prepare()
    {
        self::$prepareRanAfterSetUp = self::$setUpCalled;
    }
PHP
        );
        $framework = new Framework(
            $this->createStub(ContainerInterface::class),
            $this->createStub(LoopInterface::class),
            $this->createStub(LoggerInterface::class),
            rootDir: $this->rootDir,
        );

        $framework->buildTestCase($className);

        $this->assertTrue($className::$prepareRanAfterSetUp);
    }

    protected function tearDown(): void
    {
        if (isset($this->rootDir)) {
            $testDir = $this->rootDir . '/test/notif/tests';
            foreach (glob($testDir . '/test*.php') ?: [] as $file) {
                unlink($file);
            }

            rmdir($testDir);
            rmdir($this->rootDir . '/test/notif');
            rmdir($this->rootDir . '/test');
            rmdir($this->rootDir);
        }
    }

    private function writeNotificationTestFixture(?string $methods = null): string
    {
        $className = 'AsyncFrameworkFixture' . bin2hex(random_bytes(8));
        $this->rootDir = sys_get_temp_dir() . '/' . $className;
        $testDir = $this->rootDir . '/test/notif/tests';
        mkdir($testDir, 0777, true);

        $methods ??= <<<'PHP'
    public function requiredDaemons()
    {
        return [self::DAEMON_SCHEDULE];
    }

    public function prepare()
    {
    }
PHP
        ;

        file_put_contents(
            $testDir . '/test' . $className . '.php',
            <<<PHP
<?php

use Alaress\Async\Test\AbstractTestCase;

class {$className} extends AbstractTestCase
{
{$methods}
}
PHP
        );

        return $className;
    }
}
