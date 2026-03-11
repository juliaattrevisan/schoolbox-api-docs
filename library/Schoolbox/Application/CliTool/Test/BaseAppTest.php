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
use Schoolbox\Config\ArrayLoader;

#[CoversClass(BaseApp::class)]
class BaseAppTest extends TestCase
{
    /**
     * Backup for `$_SERVER['argv']`: set before running each test, and restored
     * after completion.
     */
    private array $argvBackup = [];

    /**
     * Backup for `$_SERVER['argc']`: set before running each test, and restored
     * after completion.
     */
    private int $argcBackup = 0;

    /**
     * Set backup for argv and argc
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->argvBackup = $_SERVER['argv'];
        $this->argcBackup = $_SERVER['argc'];
    }

    /**
     * Restore argv and argc from backup
     *
     * @return void
     */
    public function tearDown(): void
    {
        $_SERVER['argv'] = $this->argvBackup;
        $_SERVER['argc'] = $this->argcBackup;
    }

    #[DataProvider('getTestGetOptData')]
    public function testGetOpt(array $options, array $arguments, array $expectedOptionValues): void
    {
        $testCliTool = $this->buildTestCliTool($options, $arguments, true);

        // Do tests here
        $actualOptionValues = [];
        foreach ($expectedOptionValues as $option => $expectedValue) {
            // @phpstan-ignore-next-line - getOpt is public in $testCliTool
            $actualOptionValue = $testCliTool->getOpt($option);

            // As mentioned in the data provider, the underlying library parses
            // multiples of the same option strangely:
            // previous options will be re-added to the value.
            // Thus something like `-m1 -m2 -m3` will be something like
            // `[1, 1, 2, 1, 2, 3]`: we are only interested in unique values.
            if (is_array($actualOptionValue)) {
                $actualOptionValue = array_values(array_unique($actualOptionValue));
            }
            $actualOptionValues[$option] = $actualOptionValue;
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

        yield 'simple options, -m 1 provided' => [
            $simpleOptions,
            ['-m', '1'],
            [
                'b'        => null,
                'bool'     => null,
                'i'        => null,
                'int'      => null,
                'm'        => ['1'],
                'multiple' => ['1'],
                'o'        => null,
                'optional' => null,
            ],
        ];

        // Multiple options are parsed strangely:
        // when a multiple option is added, its previous value is re-added.
        // Thus the below option actually parses to `[1, 1, 2]`: the test will
        // strip duplicates.
        yield 'simple options, -m 1 -m=2 provided' => [
            $simpleOptions,
            ['-m', '1', '-m=2'],
            [
                'b'        => null,
                'bool'     => null,
                'i'        => null,
                'int'      => null,
                'm'        => ['1', '2'],
                'multiple' => ['1', '2'],
                'o'        => null,
                'optional' => null,
            ],
        ];

        yield 'simple options, -m 1 -m=2 -m3 provided' => [
            $simpleOptions,
            ['-m', '1', '-m=2', '-m3'],
            [
                'b'        => null,
                'bool'     => null,
                'i'        => null,
                'int'      => null,
                'm'        => ['1', '2', '3'],
                'multiple' => ['1', '2', '3'],
                'o'        => null,
                'optional' => null,
            ],
        ];

        // Unexpectedly, this parses to `null` rather than something truthy:
        // I don't expect optional args will be used much anyway
        yield 'simple options, -o provided' => [
            $simpleOptions,
            ['-o'],
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

        yield 'simple options, -o=1 provided' => [
            $simpleOptions,
            ['-o=1'],
            [
                'b'        => null,
                'bool'     => null,
                'i'        => null,
                'int'      => null,
                'm'        => null,
                'multiple' => null,
                'o'        => '1',
                'optional' => '1',
            ],
        ];
    }

    /**
     * Build and run a CLI tool for the purpose of testing option parsing.
     *
     * @param array<Option> $options    The options the tool accepts.
     * @param array<string> $arguments  The arguments to pass to the tool.
     * @param bool          $hasPerform Does the tool have perform mode?
     * @return BaseApp
     */
    private function buildTestCliTool(array $options, array $arguments, bool $hasPerform): BaseApp
    {
        // This test tool simply sets options based on `$options`, and exposes
        // `getOpt` so they can be retrieved.
        $testCliTool = new class ($hasPerform, ...$options) extends BaseApp {
            /**
             * @var array<Option>
             */
            private array $options;

            public function __construct(private bool $hasPerform, Option ...$options)
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
                return $this->hasPerform;
            }
        };

        // Build dummy DI container with dummy config and DB connection
        $di = new ContainerBuilder()->newInstance()
            ->set('config', new ArrayLoader())
            ->set('databaseInternal', new NullConnector());

        // Override argv and argc manually: setup reads argv from these settings
        $_SERVER['argv'] = array_merge([__FILE__], $arguments);
        $_SERVER['argc'] = count($_SERVER['argv']);

        // Setup, so that CLI args are parsed
        $testCliTool->setup([], $di, STDERR, new NullLogger(), true);
        return $testCliTool;
    }
}
