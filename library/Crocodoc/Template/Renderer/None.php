<?php
/**
 * None
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Crocodoc\Template\Renderer;

class None extends Base {

	/**
	 * The template file to inject
	 *
	 * @var string
	 */
	protected $templateFile = 'submission/inline/injectables/none.html';

	/**
	 * Control file, to determine how to return work
	 *
	 * @var string
	 */
	protected $controlFile = 'submission/inline/injectables/redirect-return-control.html';

	/**
	 * Render injectable
	 *
	 * @return mixed|void
	 */
	public function render() {
		$this->getPage()->setFile('injectable', $this->getTemplateFile());
		$this->getPage()->parse('INJECTED_INTERFACE_TEMPLATE', 'injectable');

		/* Control how Return Work is done
		 */
		$this->getPage()->setFile('injectableControl', $this->getControlFile());

		/* Determine next url
		 */
		$navigationElements = $this->getNavigationElements();

		if (is_array($navigationElements['next'])) {
			$nextUrl = $navigationElements['next']['navigation']['value'];
		}
		else {
			$nextUrl = '/submission/viewSubmission/' . $this->getSubmission()->getSubmissionBox()->getId();
		}

		$this->getPage()->setVar(array(
			'INJECTABLE_CONTROL_NEXT_URL' => $nextUrl,
		));

		$this->getPage()->parse('INJECTED_INTERFACE_CONTROL', 'injectableControl');
	}
} 