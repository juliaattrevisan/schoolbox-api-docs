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
        $indent = str_repeat(' ', self::INDENT_WIDTH);
        return strtr(
            self::USAGE_TEMPLATE,
            [
                '{NAME}' => $this->wrapText(
                    $this->getOpt->get(GetOpt::SETTING_SCRIPT_NAME) .
                    ' - ' .
                    ($this->settings[self::NAME_KEY] ?? '')
                ),
                '{USAGE}' => $this->wrapText(
                    $this->getOpt->get(GetOpt::SETTING_SCRIPT_NAME)
                    . ' '
                    . $this->renderUsageCommand()
                    . $this->renderUsageOptions()
                    . $this->renderUsageOperands()
                ),
                '{DESCRIPTION}' => $this->wrapText($this->renderDescription()),
            ],
        );
    }

    private function wrapText(string $text): string
    {
        $indent   = str_repeat(' ', self::INDENT_WIDTH);
        $maxWidth = $this->getScreenWidth();

        // First split by line-break, appending a paragraph to each
        $paragraphs = preg_split('/(\n)/', $text, -1);
        $paragraphs = array_map(fn ($p) => $indent . $p, $paragraphs);

        // Break on space or hyphen if possible:
        // if the next word would still be too long, don't break.
        $paragraphs = array_map($this->splitParagraph(...), $paragraphs);
        return rtrim(implode(PHP_EOL, $paragraphs));
    }

    private function splitParagraph(string $paragraph, int $iteration = 1): string
    {
        // Break infinite recursion
        if ($iteration > 100) {
            return $paragraph;
        }

        $indent   = str_repeat(' ', self::INDENT_WIDTH);
        $maxWidth = $this->getScreenWidth();
        if (mb_strlen($paragraph) > $maxWidth) {
            // Find last space before maxWidth:
            // if there is none, split at first after maxWidth
            $lastSplit = mb_strrpos(mb_substr($paragraph, 0, $maxWidth), ' ');
            if ($lastSplit === false) {
                $lastSplit = mb_strpos($paragraph, ' ', $maxWidth);
            }
            return mb_substr($paragraph, 0, $lastSplit)
                . PHP_EOL
                . $indent
                . trim($this->splitParagraph(mb_substr($paragraph, $lastSplit), $iteration + 1));
        }
        return $paragraph;
    }
}
