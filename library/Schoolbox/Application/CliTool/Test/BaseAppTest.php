<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool\Test;

use Alaress\Database\Connector\NullConnector;
use Aura\Di\Container;
use Aura\Di\ContainerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Schoolbox\Application\CliTool\BaseApp;

#[CoversClass(BaseApp::class)]
class BaseAppTest extends TestCase
{
    public function testGetOpt(): void
    {
        $testCliTool = new class extends BaseApp {
            public function run(): void
            {
                // Parse this tool's options I guess?
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
                // TODO set from data provider
                return $this;
            }

            protected function hasPerformMode(): bool
            {
                // TODO set from data provider
                return false;
            }
        };

        // Somehow set argv here
        $di = new ContainerBuilder()->newInstance()
            ->set('config', new \Schoolbox\Config\ArrayLoader())
            ->set('databaseInternal', new NullConnector());
        $testCliTool->setup(
            [],
            $di,
            STDERR,
            new NullLogger(),
            false,
        );

        $testCliTool->run();

        // Do tests here
        $this->assertSame('v', 'r');
    }
}
