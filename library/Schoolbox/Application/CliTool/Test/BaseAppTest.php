<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool\Test;

use Alaress\Database\Connector\NullConnector;
use Aura\Di\ContainerBuilder;
use GetOptionKit\Option;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Schoolbox\Application\CliTool\BaseApp;

#[CoversClass(BaseApp::class)]
class BaseAppTest extends TestCase
{
    /**
     * Backup for `$_SERVER['argv']`: set before running each test, and restored
     * after completion.
     *
     * @var array
     */
    private array $argvBackup = [];

    /**
     * Set backup for `$_SERVER['argv']`
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->argvBackup = $_SERVER['argv'];
    }

    /**
     * Restore `$_SERVER['argv']` from backup
     *
     * @return void
     */
    public function tearDown(): void
    {
        $_SERVER['argv'] = $this->argvBackup;
    }

    #[DataProvider('getTestGetOptData')]
    public function testGetOpt(array $options, array $arguments, array $expectedOptionValues): void
    {
        $testCliTool = new class (...$options) extends BaseApp {
            /**
             * @var array<Option>
             */
            private array $options;

            public function __construct(Option ...$options)
            {
                $this->options = $options;
            }

            public function run(): void
            {
            }

            public function getOpt(string $name)
            {
                return parent::getOpt($name);
            }

            protected function getName(): string
            {
                return 'name';
            }

            protected function getDescription(): string
            {
                return 'description';
            }

            protected function setOptions(): BaseApp
            {
                foreach ($this->options as $option) {
                    $this->optionCollection->addOption($option);
                }
                return $this;
            }

            protected function hasPerformMode(): bool
            {
                // TODO set from data provider?
                return false;
            }
        };

        // Somehow set argv here
        $di = new ContainerBuilder()->newInstance()
            ->set('config', new \Schoolbox\Config\ArrayLoader())
            ->set('databaseInternal', new NullConnector());

        // Override argv manually for now:
        // setup reads argv from these settings
        $_SERVER['argv'] = array_merge([__FILE__], $arguments);
        $testCliTool->setup(
            [],
            $di,
            STDERR,
            new NullLogger(),
            true,
        );
        $testCliTool->run();

        // Do tests here
        $actualOptionValues = [];
        foreach ($expectedOptionValues as $option => $expectedValue) {
            $actualOptionValues[$option] = $testCliTool->getOpt($option);
        }
        $this->assertSame($expectedOptionValues, $actualOptionValues);
    }

    /**
     * Data provider for `testGetOpt`
     *
     * @return iterable
     */
    public static function getTestGetOptData(): iterable
    {
        yield 'default options only, no arguments provided' => [
            [],
            [],
            [
                'v'         => 0,
                'p'         => false,
                'perform'   => false,
                'porcelain' => false,
            ],
        ];
    }
}
