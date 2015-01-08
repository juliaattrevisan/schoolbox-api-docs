<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_TextboxSearch extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $page->setVar('COMPONENT_TEXTBOX_ID', $configValue);

        $handler = new TextBox();

        $textbox = $handler->getByID($configValue);

        $query = "
            SELECT *
            FROM component_definition
            INNER JOIN component_config_definition ON component_definition.id = component_config_definition.component_definition_id
            INNER JOIN component_config_instance ON component_config_instance.component_config_definition_id = component_config_definition.id
            INNER JOIN component_instance ON component_config_instance.component_instance_id = component_instance.id
            WHERE class = 'Component_Homepage_TextBoxController'
            AND `key` = 'textbox'
            AND `identifier` = {$textbox['categoryID']}
            AND `value` = {$textbox['textBoxID']}
        ";

        $allResults = $handler->db->getAll($query);

        /* Because you can have multiple textbox components displaying the
         * same textbox, just pick the first one that uses the textbox
         */
        $componentId = $allResults[0]['id'];

        $page->setVar(array(
            'COMPONENT_TEXTBOX_NAME' => $textbox['heading'],
            'VIEW_URL' => "/homepage/{$textbox['categoryID']}#component{$componentId}",
            'EDIT_URL' => "/homepage/{$textbox['categoryID']}/textBoxModify/{$componentId}#component{$componentId}",
        ));
    }
}
