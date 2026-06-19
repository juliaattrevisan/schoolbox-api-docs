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
        var_dump($this->optionResult->toArray());
        var_dump($this->getOpt('v'));
        var_dump($this->getOpt('p'));
        var_dump($this->getOpt('perform'));
    }

    /**
     * @inheritdoc
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    protected function getName(): string
    {
        return 'Test app';
    }

    /**
     * @inheritdoc
     */
    protected function getDescription(): string
    {
        return <<<DESC
            Description of the test app.

            Test content goes here with a very long description, some of which may be relevant but is only for testing though it is uncertain exactly how it wraps.
            DESC;
    }

    /**
     * Add some test options relevant only to this app.
     */
    protected function setOptions(): CliTool\BaseApp
    {
        $this->optionCollection->add('t', 'Test mode');
        $this->optionCollection->add('f|folder:', 'Folder on which to operate: a very long text output goes here but I do not know why, because it\'s only for testing whether text wraps');
        $this->optionCollection->add('dry-run?', 'Dry run?');
        return $this;
    }

    /**
     * This demo app supports perform mode: though being a demo, it does not
     * actually perform anything.
     */
    protected function hasPerformMode(): bool
    {
        return true;
    }
});
$kernel->runApp();
