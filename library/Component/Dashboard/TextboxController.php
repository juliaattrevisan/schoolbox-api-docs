<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_TextboxController extends Component_Dashboard_ControllerAbstract {

    private $content;
    private $hideHeader;

    public function execute() {
        $textBoxID = $this->getConfig('textboxSearch');
        $this->hideHeader = $this->getConfig('compactMode');

        if ($textBoxID > 0) {
            $tboxHandler = new TextBox();
            $tbox = $tboxHandler->getByID($textBoxID);

            $this->content = array(
                'BOX_TITLE' => Scrub::text($tbox['heading']),
                'BOX_CONTENT' => Scrub::HTML($tbox['body'])
            );
            return true;
        } else {
            return false;
        }
    }

    public function renderTemplate($templateHandle) {
        if (!$this->loadTemplate($templateHandle, 'textbox')) {
            return false;
        }

        $this->setBlock($templateHandle, 'head', 'head_block');

        $this->setVar($this->content);

        if ($this->hideHeader) {
            $this->setVar('head_block', '');
        } else {
            $this->parse('head_block', 'head');
        }


        return true;
    }

}