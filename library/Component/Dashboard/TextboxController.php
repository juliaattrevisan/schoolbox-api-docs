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
			$mapper = new \Schoolbox\Textbox\Mapper();

			try {
				$textbox = $mapper->findById($textBoxID);
                $this->setComponentName($textbox->getHeading());
				$this->content = array(
					'BOX_TITLE' => Scrub::text($textbox->getHeading()),
					'BOX_CONTENT' => Scrub::HTML($textbox->getBody())
				);
			}
			catch(\Exception $e) {
				$this->content = array(
					'BOX_TITLE' => '',
					'BOX_CONTENT' => ''
				);
			}

			return true;
		}

		return false;
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


        $this->parseConfig();
		return true;
	}

}