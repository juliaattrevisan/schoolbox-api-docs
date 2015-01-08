<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\Collection;
use Schoolbox\Textbox\Model;
use Schoolbox\Textbox\Mapper;

class Component_Config_TextboxSearch extends Component_Config_Abstract {
{
    /**
     * Parse
     *
     * @param SitePage $page
     * @param $key
     * @param $keyblock
     * @param $configValue
     * @param array $allConfigs
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $mapper = new Mapper();

        try {
            $textbox = $mapper->findById($configValue);

            $allUsages = $mapper->findComponentUsage($textbox);
        }
        catch (\Exception $e) {
            $textbox = new Model();

            $allUsages = new Collection();
            $allUsages->add(new \Component_instance());
        }

        /* Because you can have multiple textbox components displaying the
         * same textbox, just pick the first one that uses the textbox
         */
        $componentId = $allUsages->first()->getId();

        $page->setVar(array(
            'COMPONENT_TEXTBOX_ID' => $configValue,
            'COMPONENT_TEXTBOX_NAME' => $textbox->getHeading(),
            'VIEW_URL' => "/homepage/{$textbox->getCategoryId()->getId()}#component{$componentId}",
            'EDIT_URL' => "/homepage/{$textbox->getCategoryId()->getId()}/textBoxModify/{$componentId}#component{$componentId}",
        ));
    }
}
