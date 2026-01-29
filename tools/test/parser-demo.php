#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(new class extends CliTool\BaseApp {
    public function run(): void
    {
        var_dump($this->optionParser->values());
    }

    /**
     * @inheritdoc
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }

    protected function getName(): string
    {
        return 'Test app';
    }

    protected function getDescription(): string
    {
        return 'Description of the test app';
    }

    protected function setOptions(): CliTool\BaseApp
    {
        $this->optionParser->option(
            '-t',
            'Test mode',
        );
        return $this;
    }

    protected function hasPerformMode(): bool
    {
        return false;
    }
});
$kernel->runApp();
