<?php
/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Schoolbox\Collection;
use Schoolbox\Resource\Textbox\Model as Textbox;
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
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $mapper = new TextboxMapper();

        $page->setBlock($key, 'is_linked', 'is_linked_block');




        /* Find the textbox specified by $configValue
         *
         * This gives you a categoryID (a folder id) which you can use to construct the View url
         */

        /* Using that folder id, find the first usage of a textbox component with $configValue
         * as its currently selected textbox. This gives you a $componentId which you can use to
         * construct the View url
         */

        /* These two pieces let you construct an Edit url too
         */







        try {
            /** @var Textbox $textbox */
            $textbox   = $mapper->findById($configValue);
            $allUsages = $mapper->findComponentUsage($textbox);
        } catch (\Exception $e) {
            $allUsages = new Collection();
        }
        if (count($allUsages) === 0) {
            $textbox = new Textbox();
            $allUsages->add(new \Component_Instance());
        }

        /* Because you can have multiple textbox components displaying the
         * same textbox, just pick the first one that uses the textbox
         */
        $componentId = $allUsages->first()->getId();





        // Is this component linked to a TextBox yet?
        $isLinked = 0 != $configValue;

        $page->setVar(
            array(
                'COMPONENT_INSTANCE_ID'  => $component->getInstance()->getId(),
                'LINKED_TEXTBOX_ID'      => $configValue,
                'VAR_IS_LINKED'          => $isLinked ? 'true' : 'false',
                'COMPONENT_TEXTBOX_NAME' => $textbox->getHeading(),
                'VIEW_URL'               => "/homepage/{$textbox->getCategory()->getId()}#component{$componentId}",
                'EDIT_URL'               => "/homepage/{$textbox->getCategory()->getId()}/textBoxModify/{$componentId}#component{$componentId}",
            )
        );

        if($isLinked) {
            $page->parse('is_linked_block', 'is_linked');
        } else {
            $page->setVar('is_linked_block', '');
        }
    }
}
