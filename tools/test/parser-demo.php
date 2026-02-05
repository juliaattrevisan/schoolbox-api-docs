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
        $this->optionParser->process();
        var_dump($this->optionParser->getOptions());
        echo $this->optionParser->getHelpText();
    }

    /**
     * @inheritdoc
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }

//    public function getShortOpts(): array
//    {
//        return ['x'];
//    }

    protected function getName(): string
    {
        return 'Test app';
    }

    protected function getDescription(): string
    {
        return <<<DESC
            Description of the test app.

            Test content goes here
            DESC;
    }

    protected function setOptions(): CliTool\BaseApp
    {
        $this->optionParser->addOption(
            new GetOpt\Option(
                't',
                null,
                GetOpt\GetOpt::NO_ARGUMENT,
            )->setDescription('Test mode')
        );
        $this->optionParser->addOption(
            new GetOpt\Option(
                'f',
                'folder',
                GetOpt\GetOpt::REQUIRED_ARGUMENT,
            )->setDescription('Folder on which to operate: a very long text output goes here but I do not know why, because it\'s only for testing whether text wraps')
        );
        $this->optionParser->addOption(
            new GetOpt\Option(
                null,
                'dry-run',
                GetOpt\GetOpt::OPTIONAL_ARGUMENT,
            )->setDescription('Dry run?')
        );
        return $this;
    }

    protected function hasPerformMode(): bool
    {
        return false;
    }
});
$kernel->runApp();
