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
        $textBoxID = $this->getConfig('textbox');
        $this->hideHeader = $this->getConfig('compactMode');

        $tboxHandler = new TextBox();
        $tbox = $tboxHandler->getByID($textBoxID);

        // define the 3 elements here
        $content = array(
            'title' => $tbox['heading'],
            'content' => $tbox['body']
        );

        // namespace the vars for the template
        foreach($content as $k => $v) {
            $this->content['BOX_'.strtoupper($k)] = $v;
        }

        return true;
    }

    public function renderTemplate($templateHandle) {
        if (!$this->loadTemplate($templateHandle, 'textbox')) {
            return false;
        }

        $this->setBlock($templateHandle, 'head', 'head_block');

        $this->setVar($this->content);

        if($this->hideHeader) {
            $this->setVar('head_block', '');
        }
        else {
            $this->parse('head_block', 'head');
        }


        return true;
    }

}