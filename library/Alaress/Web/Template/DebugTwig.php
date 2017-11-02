<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Alaress\Web\Template;

abstract class DebugTwig extends \Twig_Template {

    public function display(array $context, array $blocks = array())
    {
        if (\Config::get('debug_mode') == 1) {
            if (substr($this->getTemplateName(), 0, 1) == '@') {
                echo '<!-- START: ' . $this->getTemplateName() . ' -->';
                echo '<div class="twigName" hidden><span class="gradient-1 small"><small>' . $this->getTemplateName() . '</small></span></div>';
            }
        }

        $this->displayWithErrorHandling($this->env->mergeGlobals($context), array_merge($this->blocks, $blocks));

        if (\Config::get('debug_mode') == 1) {
            if (substr($this->getTemplateName(), 0, 1) == '@') {
                echo '<!-- END: ' . $this->getTemplateName() . ' -->';
            }
        }

    }
}
?>