<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Cli\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Socs\Cli\ImporterTool;

#[CoversClass(ImporterTool::class)]
class ImporterToolTest extends TestCase
{
    public function testFixtureImportUsesFixtureEndpointHost(): void
    {
        $tool = new class extends ImporterTool {
            public function getFixtureEndpointHostForTest(): string
            {
                return $this->resolveFixtureEndpointHost();
            }
        };

        $tool->setup(
            [],
            $this->buildContainer(new ArrayLoader([
                ConfigMapper::NAME_SOCS_ENDPOINT_HOST => 'https://calendar.example.test',
                ConfigMapper::NAME_SOCS_ENDPOINT_HOST_FIXTURE => 'https://fixtures.example.test',
            ])),
            STDERR,
            new NullLogger(),
            false,
        );

        self::assertSame('https://fixtures.example.test', $tool->getFixtureEndpointHostForTest());
    }


    public function testFixtureImportFallsBackToEndpointHost(): void
    {
        $tool = new class extends ImporterTool {
            public function getFixtureEndpointHostForTest(): string
            {
                return $this->resolveFixtureEndpointHost();
            }
        };

        $tool->setup(
            [],
            $this->buildContainer(new ArrayLoader([
                ConfigMapper::NAME_SOCS_ENDPOINT_HOST => 'https://calendar.example.test',
                ConfigMapper::NAME_SOCS_ENDPOINT_HOST_FIXTURE => '',
            ])),
            STDERR,
            new NullLogger(),
            false,
        );

        self::assertSame('https://calendar.example.test', $tool->getFixtureEndpointHostForTest());
    }

    private function buildContainer(ArrayLoader $config): ContainerInterface
    {
        return new class ($config) implements ContainerInterface {
            public function __construct(private ArrayLoader $config)
            {
            }

            public function get(string $id): mixed
            {
                return match ($id) {
                    'config' => $this->config,
                    'databaseInternal' => null,
                    default => null,
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, ['config', 'databaseInternal'], true);
            }
        };
    }
}
