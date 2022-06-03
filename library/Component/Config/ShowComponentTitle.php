<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/**
 * Class Component_Config_ShowComponentTitle
 *
 * This configuration decides whether to show the component's title
 *
 * It's the diametric opposite of Hide Heading which was migrated
 * into this context
 */
class Component_Config_ShowComponentTitle extends Component_Config_Abstract
{
    public const KEY = 'showComponentTitle';

    /**
     * @param SitePage                          $page
     * @param string                            $key
     * @param string                            $keyblock
     * @param string                            $configValue
     * @param Component_ControllerAbstract|null $component
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        if ($configValue == '1') {
            $page->setVar(array(
                'COMPONENT_SHOWCOMPONENTTITLE_HIDE'            => 'hide',
                'COMPONENT_SHOWCOMPONENTTITLE_SHOW'            => '',
                'COMPONENT_CONFIG_SHOWCOMPONENTTITLE_SELECTED' => 'checked="checked"'
            ));
        } else {
            $page->setVar(array(
                'COMPONENT_SHOWCOMPONENTTITLE_HIDE'            => '',
                'COMPONENT_SHOWCOMPONENTTITLE_SHOW'            => 'hide',
                'COMPONENT_CONFIG_SHOWCOMPONENTTITLE_SELECTED' => ''
            ));
        }
    }
}
