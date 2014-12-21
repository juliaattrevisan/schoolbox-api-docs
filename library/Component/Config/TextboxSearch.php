<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_TextboxSearch extends Component_Config_Abstract {

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null) {
        $page->setVar('COMPONENT_TEXTBOX_ID', $configValue);

        $handler = new TextBox();

        $textbox = $handler->getByID($configValue);

        $page->setVar('COMPONENT_TEXTBOX_NAME', $textbox['heading']);
    }
}
