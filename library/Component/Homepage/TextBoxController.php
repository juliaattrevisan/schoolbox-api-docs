<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_TextBoxController extends Component_Homepage_ControllerAbstract
{

    /** @var \Schoolbox\Resource\Textbox\Model */
    protected $textbox;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission

        if(!$this->isSourceRenderable()){
            if(!$this->hasContextWriteAccess()){
                return false;
            }
        }

        try {
            $this->textbox = \Schoolbox\Resource\Textbox\Mapper::getInstance()->findById($this->getConfig('textbox'));
            if ($this->getTextbox()->isDeleted()) {
                throw new \Schoolbox\Mapper\Exception("Requested textbox has been deleted");
            }
        } catch (\Schoolbox\Mapper\Exception $e) {
            // didn't find it or it's been deleted: create a blank template box instead
            $this->textbox = new \Schoolbox\Resource\Textbox\Model();
            $this->textbox
                ->setHeading('Text Box')
                ->setCategory(\Schoolbox\Resource\Folder\Mapper::getInstance()->createModel()->setId($this->getSource()->getId()));
        }

        if (!$this->getTextbox()->getHeading()) {
            $this->getTextbox()->setHeading('Text Box');
        }
        return true;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'textBox')) {
            return false;
        }

        $this->setBlock($templateHandle, 'source_folder_deleted', 'source_folder_deleted_block');
        $this->setBlock($templateHandle, 'source_folder_exists', 'source_folder_exists_block');
        $this->setBlock('source_folder_exists', 'text_editable', 'text_editable_block');
        $this->setBlock('source_folder_exists', 'text_locked', 'text_locked_block');
        $this->setBlock('text_locked', 'modify_text', 'modify_text_block');
        $this->setBlock('text_locked', 'create_text', 'create_text_block');



        if(!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailable();
        } else {
            $this->setVar('source_folder_deleted_block', '');

            /*
             * Remember where the textBox came from - if you modify a streamed
             * textBox which comes from another homepage, you were able to
             * inadvertently steal ownership of it
             */
            $heading = Scrub::text($this->getTextbox()->getHeading());
            $this->setComponentName($heading);

            $this->setVar(array(
                'TEXTBOX_HEADING'     => $heading,
                'TEXTBOX_BODY'        => Scrub::html($this->getTextbox()->getBody()),
                'TEXTBOX_ID'          => $this->getTextbox()->getId(),
                'ID'                  => $this->getContext()->getId(),
                'TEXTBOX_CATEGORY_ID' => $this->getTextbox()->getCategory()->getId(),
                'text_editable_block' => '',
                'modify_text_block'   => '',
                'create_text_block'   => '',
                'text_locked_block'   => '',
            ));

            /* To edit someone else's textbox, you need write access to both homepages.
             * To just edit one homepage's textbox, you need write access to that homepage.
             */
            $editing = false;
            if ($this->contentPermission >= WRITE_ACCESS && $this->sitepage->arg[2] == $this->instance->getId()) {
                if ($this->sitepage->arg[1] == 'textBoxModify') {
                    $editing = true;
                    $this->setVar(
                        array(
                            'TEXTBOX_BODY' => Scrub::text($this->getTextbox()->getBody()),
                        )
                    );
                } elseif ($this->sitepage->arg[1] == 'textBoxCreate') {
                    $editing = true;
                    $this->setVar(
                        array(
                            'TEXTBOX_HEADING' => '',
                            'TEXTBOX_BODY' => '',
                            'TEXTBOX_ID' => '',
                            'TEXTBOX_CATEGORY_ID' => $this->getSource()->getId(),
                        )
                    );
                }
                $this->parse('text_editable_block', 'text_editable');
            } else {
                // modify links
                if ($this->contentPermission >= WRITE_ACCESS) {
                    if ($this->getTextbox()->getId()) {
                        $this->parse('modify_text_block', 'modify_text');
                    } else {
                        $this->parse('create_text_block', 'create_text');
                    }
                }

                // formatted text
                $this->parse('text_locked_block', 'text_locked');
            }
            $this->parse('source_folder_exists_block', 'source_folder_exists');
        }

        /* Need title injected here
         */
        if ($editing) {
            $this->config['collapse']['value'] = false;
        }
        $this->parseConfig();

        if ($this->contentPermission < WRITE_ACCESS && $this->getConfig('compactMode')) {
            /* Remove headers */
            $this->setVar('CONFIGURATION', '');
            $this->setBlock($templateHandle, 'header', 'header_block');
            $this->setBlock($templateHandle, 'dashboard_header', 'dashboard_header_block');
            $this->setVar('header_block', '');
            $this->setVar('dashboard_header_block', '');
        }


        return true; // everything went according to plan
    }

    /**
     * @return \Schoolbox\Resource\Textbox\Model
     */
    protected function getTextbox()
    {
        return $this->textbox;
    }
}
