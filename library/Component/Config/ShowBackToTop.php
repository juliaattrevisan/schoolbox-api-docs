<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/**
 * Class Component_Config_ShowBackToTop
 *
 * This configuration decides whether to show a Back To Top button
 * at the bottom of a textbox; this button sends you to the top of
 * the current page you're on
 */
class Component_Config_ShowBackToTop extends Component_Config_Abstract
{
    public const KEY = 'showBackToTpp';

    /**
     * @param SitePage                          $page
     * @param string                            $key
     * @param string                            $keyblock
     * @param string                            $configValue
     * @param Component_ControllerAbstract|null $component
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, ?Component_ControllerAbstract $component = null)
    {

        if ($configValue == '1') {
            $page->setVar(array(
                'COMPONENT_SHOWBACKTOTOP_HIDE'            => 'hide',
                'COMPONENT_SHOWBACKTOTOP_SHOW'            => '',
                'COMPONENT_CONFIG_SHOWBACKTOTOP_SELECTED' => 'checked="checked"'
            ));
        } else {
            $page->setVar(array(
                'COMPONENT_SHOWBACKTOTOP_HIDE'            => '',
                'COMPONENT_SHOWBACKTOTOP_SHOW'            => 'hide',
                'COMPONENT_CONFIG_SHOWBACKTOTOP_SELECTED' => ''
            ));
        }
    }
}
