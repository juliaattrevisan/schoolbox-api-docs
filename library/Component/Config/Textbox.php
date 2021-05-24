<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_Textbox extends Component_Config_Abstract
{
    /**
     * Parse
     *
     * @param SitePage $page
     * @param string $key
     * @param string $keyblock
     * @param mixed $configValue
     * @param Component_ControllerAbstract $component
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $textboxMapper = new \Schoolbox\Resource\Textbox\Mapper();

        // has an internal block for the select element

        /* Select a textbox from some other homepage
         */
        $altSource = $component instanceof Component_ControllerAbstract ? $component->getConfig('src') : null;

        if (is_numeric($altSource) && $altSource) {
            $allTextboxes = $textboxMapper->findByColumn('categoryID', $altSource);
        } else {
            $allTextboxes = $this->getContext()
                ? $textboxMapper->findByColumn('categoryID', $this->getContext()->getId())
                : new \Schoolbox\Collection();
        }

        $page->setBlock($keyblock, 'textbox_select', 'textbox_select_block');

        $page->setVar([
            'textbox_select_block' => '',
            'COMPONENT_CONFIG_BOXSELECT_NAME'       => Scrub::text(' (Create New) '),
            'COMPONENT_CONFIG_BOXSELECT_VALUE'      => '0',
            'COMPONENT_CONFIG_BOXSELECT_SELECTED'   => '',
        ]);

        $page->parse("textbox_select_block", "textbox_select", true);

        foreach ($allTextboxes as $currentTextbox) {

            /**
             * It's possible to create a text box without any heading so for the select box-
             * check heading and if that's a no-go then use the first part of the content
             */
            $textBoxHeading = $currentTextbox->getHeading();

            if (strlen($textBoxHeading)) {
                $selectText = $textBoxHeading;
            } else {
                $selectText = substr(Scrub::html2text($currentTextbox->getBody()), 0, 75) . '...';
            }

            $page->setVar([
                'COMPONENT_CONFIG_BOXSELECT_NAME'       => Scrub::text($selectText),
                'COMPONENT_CONFIG_BOXSELECT_VALUE'      => Scrub::text($currentTextbox->getId()),
                'COMPONENT_CONFIG_BOXSELECT_SELECTED'   => $currentTextbox->getId() == $configValue ? 'selected="selected"' : '',
            ]);

            $page->parse("textbox_select_block", "textbox_select", true);
        }
    }
}
