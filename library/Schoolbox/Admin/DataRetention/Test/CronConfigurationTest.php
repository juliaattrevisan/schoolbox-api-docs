<?php

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Test;

use PHPUnit\Framework\TestCase;

class CronConfigurationTest extends TestCase
{
    public function testNightlyCronRunsDataRetentionProcessTool(): void
    {
        $cronFile = realpath(__DIR__ . '/../../../../../tools/cron/schoolbox');
        $this->assertIsString($cronFile);

        $cronConfiguration = file_get_contents($cronFile);
        $this->assertIsString($cronConfiguration);

        $this->assertStringContainsString(
            'php /usr/share/schoolbox/tools/dataRetention/process.php -vvvp',
            $cronConfiguration,
        );
        $this->assertStringNotContainsString(
            'php /usr/share/schoolbox/tools/schema/data-retention.php -vvvp',
            $cronConfiguration,
        );
    }
}
