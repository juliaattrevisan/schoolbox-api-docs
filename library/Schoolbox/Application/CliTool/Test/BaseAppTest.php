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
                return true;
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
        yield 'default options, no arguments provided' => [
            [],
            [],
            [
                'v'         => 0,
                'p'         => false,
                'perform'   => false,
                'porcelain' => false,
            ],
        ];

        yield 'default options, --perform provided' => [
            [],
            ['--perform'],
            [
                'v'         => 0,
                'p'         => true,
                'perform'   => true,
                'porcelain' => false,
            ],
        ];

        yield 'default options, -p provided' => [
            [],
            ['-p'],
            [
                'v'         => 0,
                'p'         => true,
                'perform'   => true,
                'porcelain' => false,
            ],
        ];

        yield 'default options, --porcelain provided' => [
            [],
            ['--porcelain'],
            [
                'v'         => 0,
                'p'         => false,
                'perform'   => false,
                'porcelain' => true,
            ],
        ];

        yield 'default options, -v provided' => [
            [],
            ['-v'],
            [
                'v'         => 1,
                'p'         => false,
                'perform'   => false,
                'porcelain' => false,
            ],
        ];

        yield 'default options, -vv provided' => [
            [],
            ['-vv'],
            [
                'v'         => 2,
                'p'         => false,
                'perform'   => false,
                'porcelain' => false,
            ],
        ];

        yield 'default options, -vvv provided' => [
            [],
            ['-vvv'],
            [
                'v'         => 3,
                'p'         => false,
                'perform'   => false,
                'porcelain' => false,
            ],
        ];

        yield 'default options, -v -v provided' => [
            [],
            ['-v', '-v'],
            [
                'v'         => 2,
                'p'         => false,
                'perform'   => false,
                'porcelain' => false,
            ],
        ];

        yield 'default options, -vvvp provided' => [
            [],
            ['-vvvp'],
            [
                'v'         => 3,
                'p'         => true,
                'perform'   => true,
                'porcelain' => false,
            ],
        ];

        $simpleOptions = [
            new Option('b|bool')->isa('boolean'),
            new Option('i|int:')->isa('number'),
            new Option('m|multiple+'),
            new Option('o|optional?'),
        ];

        yield 'simple options, no arguments provided' => [
            $simpleOptions,
            [],
            [
                'b'        => null,
                'bool'     => null,
                'i'        => null,
                'int'      => null,
                'm'        => null,
                'multiple' => null,
                'o'        => null,
                'optional' => null,
            ],
        ];

        yield 'simple options, -b provided' => [
            $simpleOptions,
            ['-b'],
            [
                'b'        => true,
                'bool'     => true,
                'i'        => null,
                'int'      => null,
                'm'        => null,
                'multiple' => null,
                'o'        => null,
                'optional' => null,
            ],
        ];

        yield 'simple options, --bool provided' => [
            $simpleOptions,
            ['--bool'],
            [
                'b'        => true,
                'bool'     => true,
                'i'        => null,
                'int'      => null,
                'm'        => null,
                'multiple' => null,
                'o'        => null,
                'optional' => null,
            ],
        ];

        yield 'simple options, -i1 provided' => [
            $simpleOptions,
            ['-i1'],
            [
                'b'        => null,
                'bool'     => null,
                'i'        => 1,
                'int'      => 1,
                'm'        => null,
                'multiple' => null,
                'o'        => null,
                'optional' => null,
            ],
        ];

        yield 'simple options, -bi1 provided' => [
            $simpleOptions,
            ['-bi1'],
            [
                'b'        => true,
                'bool'     => true,
                'i'        => 1,
                'int'      => 1,
                'm'        => null,
                'multiple' => null,
                'o'        => null,
                'optional' => null,
            ],
        ];

        yield 'simple options, -i=1 provided' => [
            $simpleOptions,
            ['-i=1'],
            [
                'b'        => null,
                'bool'     => null,
                'i'        => 1,
                'int'      => 1,
                'm'        => null,
                'multiple' => null,
                'o'        => null,
                'optional' => null,
            ],
        ];

        yield 'simple options, --int=1 provided' => [
            $simpleOptions,
            ['--int=1'],
            [
                'b'        => null,
                'bool'     => null,
                'i'        => 1,
                'int'      => 1,
                'm'        => null,
                'multiple' => null,
                'o'        => null,
                'optional' => null,
            ],
        ];

        yield 'simple options, -i 1 provided' => [
            $simpleOptions,
            ['-i', '1'],
            [
                'b'        => null,
                'bool'     => null,
                'i'        => 1,
                'int'      => 1,
                'm'        => null,
                'multiple' => null,
                'o'        => null,
                'optional' => null,
            ],
        ];

        yield 'simple options, --int 1 provided' => [
            $simpleOptions,
            ['--int', '1'],
            [
                'b'        => null,
                'bool'     => null,
                'i'        => 1,
                'int'      => 1,
                'm'        => null,
                'multiple' => null,
                'o'        => null,
                'optional' => null,
            ],
        ];
    }
}
