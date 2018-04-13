<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Alaress\Web\Template;

abstract class DebugTwig extends \Twig_Template
{
    // To cause twig to not render a debug wrapper around templates, set this key to FALSE in the template's context data
    public const DEBUG_WRAP = 'DEBUG_WRAP';

    public function display(array $context, array $blocks = array())
    {
        $wrapInDebug = $this->renderDebugWrapper($context);

        if ($wrapInDebug) {
            echo "<!-- START: {$this->getTemplateName()} -->";
            echo "<div class=\"twigName\" hidden><mark><small>{$this->getTemplateName()}</small></mark></div>";
        }

        $this->displayWithErrorHandling($this->env->mergeGlobals($context), array_merge($this->blocks, $blocks));

        if ($wrapInDebug) {
            echo "<!-- END: {$this->getTemplateName()} -->";
        }
    }


    /**
     * Returns whether the context should be wrapped in a debug wrapper
     *
     * @param array $context
     * @return bool
     */
    private function renderDebugWrapper(array $context): bool
    {
        return '@' === $this->getTemplateName()[0] && (!isset($context[self::DEBUG_WRAP]) || $context[self::DEBUG_WRAP]);
    }
}
