<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\Permission\Access\UserAccess;

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
                ->setCategory(FolderMapper::getInstance()->createModel()->setId($this->getSource()->getId()));
            $this->setComponentName('Text Box');
        }

        $textBoxHeading = Scrub::text($this->getTextbox()->getHeading());

        /**
         * If there's content and the heading isn't empty - use that as the component name
         * Otherwise use the one set in config
         */
        if (strlen(trim($textBoxHeading))) {
            $this->setComponentName($textBoxHeading);
        } elseif (strlen($this->getConfig('textBoxComponentName'))) {
            $this->setComponentName(Scrub::text($this->getConfig('textBoxComponentName')));
        } else {
            $this->getComponentName();
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
        if(!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailableTwig($templateHandle);
        }

        $this->setBlock($templateHandle, 'textbox_form', 'textbox_form_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        $textBoxContent = Scrub::text($this->getTextbox()->getBody());

        if ($this->getSitePage()->arg[2] != $this->getInstance()->getId() && (empty($this->getTextbox()->getId()) || empty($textBoxContent))) {

            $this->setBlock('empty_state', 'empty_custom', 'empty_custom_block');
            $this->setBlock('empty_state', 'empty_read_only', 'empty_read_only_block');
            $this->setBlock('empty_state', 'empty_id', 'empty_id_block');
            $this->setBlock('empty_state', 'empty_content', 'empty_content_block');

            $this->setVar([
                'textbox_form_block' => '',
                'empty_id_block' => '',
                'empty_content_block' => '',
                'empty_read_only_block' => '',
                'empty_custom_block' => '',
            ]);

            $emptyStateMessage = trim($this->getConfig('emptyStateMessage'));

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                /**
                 * Read only gets a generic message
                 */
                $this->parse('empty_read_only_block', 'empty_read_only');

            } elseif ($emptyStateMessage != '') {
                /**
                 * There's a custom empty state message from the config to show
                 */
                $this->setVar('EMPTY_STATE_MESSAGE', Scrub::text($emptyStateMessage));
                $this->parse('empty_custom_block', 'empty_custom');

            } elseif ($this->getTextbox()->getId()) {
                /**
                 * They submitted the form with nothing or just a heading
                 */
                $this->parse('empty_content_block', 'empty_content');

            } else {
                /**
                 * There's nothing
                 */
                $this->parse('empty_id_block', 'empty_id');
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            $this->setVar('empty_state_block', '');

            $this->setBlock('textbox_form', 'source_folder_deleted', 'source_folder_deleted_block');
            $this->setBlock('textbox_form', 'source_folder_exists', 'source_folder_exists_block');
            $this->setBlock('source_folder_exists', 'text_editable', 'text_editable_block');
            $this->setBlock('source_folder_exists', 'text_locked', 'text_locked_block');
            $this->setBlock('text_locked', 'modify_text', 'modify_text_block');

            if (!$this->isSourceRenderable()) {
                return $this->renderSourceUnavailable();
            } else {
                $this->setVar('source_folder_deleted_block', '');

                /*
                 * Remember where the textBox came from - if you modify a streamed
                 * textBox which comes from another homepage, you were able to
                 * inadvertently steal ownership of it
                 */
                $heading = Scrub::text($this->getTextbox()->getHeading());
                $body = Scrub::html($this->getTextbox()->getBody());

                $this->setVar(array(
                    'TEXTBOX_HEADING' => $heading,
                    'TEXTBOX_BODY' => $body,
                    'TEXTBOX_ID' => $this->getTextbox()->getId(),
                    'ID' => $this->getContext()->getId(),
                    'TEXTBOX_CATEGORY_ID' => $this->getTextbox()->getCategory()->getId(),
                    'text_editable_block' => '',
                    'modify_text_block' => '',
                    'text_locked_block' => '',
                ));

                /* To edit someone else's textbox, you need write access to both homepages.
                 * To just edit one homepage's textbox, you need write access to that homepage.
                 */
                $editing = false;

                if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE && $this->getSitePage()->arg[2] == $this->getInstance()->getId()) {
                    if ($this->getSitePage()->arg[1] == 'textBoxModify') {
                        $editing = true;
                        $this->setVar([
                            'TEXTBOX_BODY' => $body,
                        ]);
                    } elseif ($this->getSitePage()->arg[1] == 'textBoxCreate') {
                        $editing = true;
                        $this->setVar([
                            'TEXTBOX_HEADING' => '',
                            'TEXTBOX_BODY' => '',
                            'TEXTBOX_ID' => '',
                            'TEXTBOX_CATEGORY_ID' => $this->getSource()->getId(),
                        ]);
                    }

                    if ($editing) {
                        // Force content to show (when component is set to collapsed by default)
                        $this->setConfigValue('collapse', 0);
                    }

                    $this->parse('text_editable_block', 'text_editable');
                } else {
                    // modify links
                    if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                        $this->parse('modify_text_block', 'modify_text');
                    }

                    // formatted text
                    $this->parse('text_locked_block', 'text_locked');
                }
                $this->parse('source_folder_exists_block', 'source_folder_exists');
            }


            $this->parse('textbox_form_block', 'textbox_form');
        }

        $this->parseConfig();

        /* Hide Headings From Readonly Users
         */
        if ($this->getContentPermission() < UserAccess::ACCESS_WRITE && $this->getConfig('compactMode')) {
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
