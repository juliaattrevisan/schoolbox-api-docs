<?php
/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;

class Component_Config_TextboxSearch extends Component_Config_Abstract
{
    /**
     * Parse
     *
     * @param SitePage                          $page
     * @param string                            $key
     * @param string                            $keyblock
     * @param mixed                             $configValue
     * @param Component_ControllerAbstract|null $component
     * @throws PDOException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        /* Find the textbox specified by $configValue
         *
         * This gives you a categoryID (a folder id) which you can use to construct the View url
         */
        $mapper = TextboxMapper::getInstance();

        try {
            $textbox = $mapper->findById($configValue);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $textbox = $mapper->createModel();
        }

        /* Using that folder id, find the first usage of a textbox component with $configValue
         * as its currently selected textbox. This gives you a $componentId which you can use to
         * construct the View url
         *
         * These two pieces let you construct an Edit url too
         */
        $firstUsageComponentId = null;

        try {
            $allUsages = $mapper->findComponentUsage($textbox);

            if ($allUsages->count()) {
                $firstUsageComponentId = $allUsages->first()->getId();
            }
        } catch (\Schoolbox\Mapper\Exception $e) {

            /* noop
             */
        }

        if ($firstUsageComponentId && $textbox->getCategory()->getId()) {

            /* Construct urls if there is a component instance which displays this textbox
             */
            $viewUrl = "/homepage/{$textbox->getCategory()->getId()}#component{$firstUsageComponentId}";
            $editUrl = "/homepage/{$textbox->getCategory()->getId()}/textBoxModify/{$firstUsageComponentId}#component{$firstUsageComponentId}";
        } else {

            /* Otherwise, you've linked to a now-deleted textbox and you don't have an explicit
             * component in which to edit its contents
             *
             * TODO How do we deal with that scenario?
             *  Wasn't there a Modify screen somewhere
             */
            $viewUrl = '';
            $editUrl = '';
        }

        // Is this component linked to a TextBox yet?
        $isLinked = 0 != $configValue;

        $page->setBlock($key, 'is_linked', 'is_linked_block');

        $page->setVar([
            'COMPONENT_INSTANCE_ID'  => $firstUsageComponentId,
            'LINKED_TEXTBOX_ID'      => $configValue,
            'VAR_IS_LINKED'          => $isLinked ? 'true' : 'false',
            'COMPONENT_TEXTBOX_NAME' => $textbox->getHeading(),
            'VIEW_URL'               => $viewUrl,
            'EDIT_URL'               => $editUrl,
        ]);

        if ($isLinked) {
            $page->parse('is_linked_block', 'is_linked');
        } else {
            $page->setVar('is_linked_block', '');
        }
    }
}
