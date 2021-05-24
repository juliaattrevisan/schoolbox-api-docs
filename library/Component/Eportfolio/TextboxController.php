<?php

/**
 * Component_Eportfolio_TextboxController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

class Component_Eportfolio_TextboxController extends Component_Eportfolio_ControllerAbstract
{
    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        $this->setMapper($this->getMapperFactory()->get(\Component\Eportfolio\TextboxMapper::class));
        return true;
    }

    /**
     * Outputs HTML template defined by config variable 'template'
     *
     * Note that textboxes have a 1:1 relationship with their component instance
     *
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, $this->getConfig('template'))) {
            return false;
        }

        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_modify_form', 'has_modify_form_block');
        $this->setBlock($templateHandle, 'output_text', 'output_text_block');
        $this->setBlock($templateHandle, 'modify_text', 'modify_text_block');
        $this->setBlock($templateHandle, 'create_text', 'create_text_block');

        $this->setVar(array(
            'text_editable_block' => '',
            'output_text_block' => '',
            'modify_text_block' => '',
            'create_text_block' => '',
            'text_locked_block' => '',
            'has_modify_form_block' => '',
        ));

        /* Gather everything
         */
        $instance_id = $this->getInstance()->getId();

        $this->setVar('TEXTBOX_ITEM_ID', $instance_id);

        try {
            $textBox = $this->getMapper()->findOneByColumn('component_instance_id', $instance_id);
            $this->setVar('TEXTBOX_CONTENT', Scrub::html($textBox->getContent()));


            /* If modifying this textbox */

            if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                if (($this->getSitePage()->arg[2] == 'textbox') && ($this->getSitePage()->arg[4] == $instance_id)) {
                    $this->setVar('TEXTBOX_CONTENT', Scrub::text($textBox->getContent()));
                    $this->parse('has_modify_form_block', 'has_modify_form');
                } else {
                    $this->parse('output_text_block', 'output_text');
                    $this->parse('modify_text_block', 'modify_text');
                }
            } else {
                $this->parse('output_text_block', 'output_text');
            }
        } catch (Exception $e) {
            $this->setVar('TEXTBOX_CONTENT', '');

            if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                if (($this->getSitePage()->arg[2] == 'textbox') && ($this->getSitePage()->arg[4] == $instance_id)) {
                    $this->parse('has_modify_form_block', 'has_modify_form');
                } else {
                    $this->parse('output_text_block', 'output_text');
                    $this->parse('modify_text_block', 'modify_text');
                }
            } else {
                $this->parse('output_text_block', 'output_text');
            }
        }



        return true;
    }
}
