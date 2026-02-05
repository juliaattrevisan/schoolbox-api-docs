<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool;

use GetOpt\GetOpt;
use GetOpt\Help as BaseHelp;

/**
 * Customised help renderer.
 */
class Help extends BaseHelp
{
    /**
     * Hard-coded indent width to match what the GetOpt library outputs by
     * default.
     */
    private const int INDENT_WIDTH = 2;

    /**
     * Constant for the script's name.
     */
    private const string NAME_KEY = 'name';

    /**
     * Constant template for the tool's usage.
     */
    private const string USAGE_TEMPLATE = <<<TEMPLATE
        NAME:
        {NAME}

        USAGE:
        {USAGE}

        DESCRIPTION:
        {DESCRIPTION}
        TEMPLATE;

    public function __construct(array $settings = [])
    {
        parent::__construct($settings);
        $this->setTexts([
            'operands-title' => "OPERANDS:" . PHP_EOL,
            'options-title'  => "OPTIONS:" . PHP_EOL,
            'commands-title' => "COMMANDS:" . PHP_EOL,
        ]);
    }

    public function setName(string $name): self
    {
        $this->set(self::NAME_KEY, $name);
        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->set(self::DESCRIPTION, $description);
        return $this;
    }

    /**
     * Despite being called "usage", this renders out the name, usage and
     * description of the help tool
     *
     * @return string
     */
    public function renderUsage(): string
    {
        // Figure out how to indent?
        return strtr(
            self::USAGE_TEMPLATE,
            [
                '{NAME}'  => str_repeat(' ', self::INDENT_WIDTH) .
                    $this->getOpt->get(GetOpt::SETTING_SCRIPT_NAME) .
                    ' - ' .
                    ($this->settings[self::NAME_KEY] ?? ''),
                '{USAGE}' => str_repeat(' ', self::INDENT_WIDTH) .
                    $this->getOpt->get(GetOpt::SETTING_SCRIPT_NAME)
                    . ' '
                    . $this->renderUsageCommand()
                    . $this->renderUsageOptions()
                    . $this->renderUsageOperands(),
                '{DESCRIPTION}' => $this->renderDescription(),
            ],
        );
    }
}
