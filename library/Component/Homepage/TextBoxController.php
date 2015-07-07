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

    /** @var \Schoolbox\Textbox\Model */
    protected $textbox;

    /** @var Group */
    protected $groupHandler;

    /**
     * Prepares data for the render function. Business logic.
     * @return void
     */
    public function execute()
    {
        $this->groupHandler = new Group();

        try {
            $this->textbox = \Schoolbox\Textbox\Mapper::getInstance()->findById($this->getConfig('textbox'));
            if ($this->getTextbox()->isDeleted()) {
                throw new \Schoolbox\Mapper\Exception("Requested textbox has been deleted");
            }
        } catch (\Schoolbox\Mapper\Exception $e) {
            // didn't find it or it's been deleted: create a blank template box instead
            $this->textbox = new \Schoolbox\Textbox\Model();
            $this->textbox
                ->setHeading('Text Box')
                ->setCategory(\Schoolbox\Resource\CategoryMapper::getInstance()->createModel()->setId($this->getContext()->getId()));
        }

        if (!$this->getTextbox()->getHeading()) {
            $this->getTextbox()->setHeading('Text Box');
        }
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

        $this->setBlock($templateHandle, 'text_editable', 'text_editable_block');
        $this->setBlock($templateHandle, 'text_locked', 'text_locked_block');
        $this->setBlock('text_locked', 'modify_text', 'modify_text_block');
        $this->setBlock('text_locked', 'create_text', 'create_text_block');

        if ($this->textbox instanceof \Schoolbox\Textbox\Model) {
            $sourceCategory = $this->textbox->getCategory()->getId();
        } else {
            $sourceCategory = $this->getContext()->getId();
        }

        /* If user does not have access to the source, do not output anything */
        if ($this->permission < READ_ACCESS) {
            return false;
        }

        /* Remember where the texbox came from - if you modify a streamed
         * textbox which comes from another homepage, you were able to
         * inadvertently steal ownership of it
         */
        $heading = Scrub::text($this->getTextbox()->getHeading());
        $this->setComponentName($heading);

        $output = array(
            'TEXTBOX_HEADING'     => $heading,
            'TEXTBOX_BODY'        => Scrub::html($this->getTextbox()->getBody()),
            'TEXTBOX_ID'          => $this->getTextbox()->getId(),
            'TEXTBOX_CATEGORY_ID' => $this->getTextbox()->getCategory()->getId(),
            'text_editable_block' => '',
            'modify_text_block'   => '',
            'create_text_block'   => '',
            'text_locked_block'   => '',
        );

        $this->setVar($output);

        /* To edit someone else's textbox, you need write access to both homepages.
         * To just edit one homepage's textbox, you need write access to that homepage.
         */
        if ($this->permission >= WRITE_ACCESS && $this->sitepage->arg[2] == $this->instance->getId()) {
            if ($this->sitepage->arg[1] == 'textBoxModify') {
                $this->setVar(
                    array(
                        'TEXTBOX_BODY' => Scrub::text($this->getTextbox()->getBody()),
                    )
                );
            } elseif ($this->sitepage->arg[1] == 'textBoxCreate') {
                $this->setVar(
                    array(
                        'TEXTBOX_HEADING'     => '',
                        'TEXTBOX_BODY'        => '',
                        'TEXTBOX_ID'          => '',
                        'TEXTBOX_CATEGORY_ID' => $sourceCategory,
                    )
                );
            }
            $this->parse('text_editable_block', 'text_editable');
        } else {
            // modify links
            if ($this->permission >= WRITE_ACCESS) {
                if ($this->getTextbox()->getId()) {
                    $this->parse('modify_text_block', 'modify_text');
                } else {
                    $this->parse('create_text_block', 'create_text');
                }
            }

            // formatted text
            $this->parse('text_locked_block', 'text_locked');
        }

        /* Need title injected here
         */
        $this->parseConfig();

        return true; // everything went according to plan
    }

    /**
     * @return \Schoolbox\Textbox\Model
     */
    protected function getTextbox()
    {
        return $this->textbox;
    }
}
