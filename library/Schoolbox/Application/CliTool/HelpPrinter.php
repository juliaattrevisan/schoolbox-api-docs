<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Application\CliTool;

use GetOptionKit\Option;
use GetOptionKit\OptionCollection;
use GetOptionKit\OptionPrinter\OptionPrinter;

/**
 * Customised help printer.
 */
class HelpPrinter implements OptionPrinter
{
    /**
     * Default indent width.
     */
    private const int DEFAULT_INDENT_WIDTH = 4;

    /**
     * Fallback screen width, if it can't be calculated any other way.
     */
    private const int FALLBACK_SCREEN_WIDTH = 78;

    /**
     * Constant template for the tool's usage.
     */
    private const string USAGE_TEMPLATE = <<<TEMPLATE
    NAME:
    {NAME}

    DESCRIPTION:
    {DESCRIPTION}

    OPTIONS:
    {OPTIONS}

    TEMPLATE;

    public function __construct(
        private string $name,
        private string $description,
        private int $indent = self::DEFAULT_INDENT_WIDTH,
        private ?int $screenWidth = null,
    ) {
    }

    /**
     * Render readable spec.
     */
    public function renderOption(Option $option)
    {
        $c1 = '';
        if ($option->short && $option->long) {
            $c1 = sprintf('-%s, --%s', $option->short, $option->long);
        } else if ($option->short) {
            $c1 = sprintf('-%s', $option->short);
        } else if ($option->long) {
            $c1 = sprintf('--%s', $option->long);
        }
        $c1 .= $option->renderValueHint();

        return $c1;
    }

    /**
     * render option descriptions.
     *
     * @return string output
     */
    public function render(OptionCollection $options)
    {
        // Attempt to calculate screen width if not provided
        $screenWidth = $this->screenWidth;
        if (!isset($screenWidth)) {
            exec('tput cols', $output);
            $screenWidth = filter_var($output[0], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        }
        if (!isset($screenWidth)) {
            $screenWidth = self::FALLBACK_SCREEN_WIDTH;
        }

        // Indent all output by this many lines
        $baseIndent = str_repeat(' ', $this->indent);

        $lines = [];
        foreach ($options as $option) {
            $c1 = $this->renderOption($option);
            $lines[] = $baseIndent . $c1;
            $lines[] = wordwrap($baseIndent . $baseIndent . $option->desc, $screenWidth, "\n" . $baseIndent . $baseIndent);
            $lines[] = '';
        }

        return strtr(
            self::USAGE_TEMPLATE,
            [
                '{NAME}'        => $baseIndent . $this->name,
                '{DESCRIPTION}' => wordwrap($baseIndent . $this->description, $screenWidth, "\n" . $baseIndent),
                '{OPTIONS}'     => implode("\n", $lines),
            ]
        );
    }
}
