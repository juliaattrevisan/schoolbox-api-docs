<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_TextBoxController extends Component_Homepage_ControllerAbstract {

    /**
     * Prepares data for the render function. Business logic.
     * @return void
     */
    public function execute() {

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'textBox')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'text_editable', 'text_editable_block');
        $this->setBlock($templateHandle, 'text_locked', 'text_locked_block');
        $this->setBlock('text_locked', 'modify_text', 'modify_text_block');
        $this->setBlock('text_locked', 'create_text', 'create_text_block');

        $textBoxHandler = new TextBox;
        $textBox = $textBoxHandler->getByID((int)$this->getConfig('textbox'));

        // Don't show deleted items
        if ($textBox['deleted'] == 'true') {
            unset($textBox);
        }

		/* Remember where the texbox came from - if you modify a streamed
		 * textbox which comes from another homepage, you were able to
		 * inadvertently steal ownership of it
		 */
        $output = array(
			'TEXTBOX_HEADING' => !empty($textBox['heading']) ? Scrub::text($textBox['heading']) : 'Text Box',
			'TEXTBOX_BODY' => Scrub::html($textBox['body']),
			'TEXTBOX_ID' => $textBox['textBoxID'],
			'TEXTBOX_CATEGORY_ID' => $textBox['categoryID'],

            'text_editable_block' => '',
            'modify_text_block'   => '',
            'create_text_block'   => '',
            'text_locked_block'   => '',
        );

        $this->setVar($output);

		/* To edit someone else's textbox, you need write access to both homepages.
		 * To just edit one homepage's textbox, you need write access to that homepage.
		 */
        if ($this->permission >= WRITE_ACCESS) {

			/* If you are editing a textbox which belongs to this homepage
			 * (you need write access for this)
			 */
			if ($this->sitepage->arg[2] == $this->instance->getId()) {
				if ($this->sitepage->arg[1] == 'textBoxModify') {
					$this->setVar(array(
						'TEXTBOX_BODY' => $textBox['body'],
					));
				}
				else if($this->sitepage->arg[1] == 'textBoxCreate') {
					$this->setVar(array(
						'TEXTBOX_HEADING' => '',
						'TEXTBOX_BODY' => '',
						'TEXTBOX_ID' => '',
						'TEXTBOX_CATEGORY_ID' => $this->sitepage->arg[0],
					));
				}

				$this->parse('text_editable_block', 'text_editable');
			}
			/* If you are editing a textbox which belongs to someone else's
			 * homepage (you need write access for this)
			 */
			else {
				$groupHandler = new Group();

				if ($textBox['textBoxID'] && ($groupHandler->getPermission($textBox['categoryID']) >= WRITE_ACCESS)) {
					$this->parse('modify_text_block', 'modify_text');
				}

				$this->parse('text_locked_block', 'text_locked');
			}
        }
        else {
            // modify links
            if ($this->permission >= WRITE_ACCESS) {
                if($textBox['textBoxID']) {
                    $this->parse('modify_text_block', 'modify_text');
                }
                else {
                    $this->parse('create_text_block', 'create_text');
                }
            }

            // formatted text
            $this->parse('text_locked_block', 'text_locked');
        }

        return true; // everything went according to plan
    }
}
