<?php
/**
 * Survey_Graph_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Survey_Graph_Abstract {
	protected $currentQuestion = array();
	protected $templateFile = null;
	protected $page = null;

	public function __construct($page, $currentQuestion, $templateFile) {
		$this->setPage($page);
		$this->setTemplateFile($templateFile);
		$this->setCurrentQuestion($currentQuestion);
		$this->setComponentHandle('temp' . $currentQuestion['question_id']);
	}

	protected function getCurrentQuestion() {
		return $this->currentQuestion;
	}

	protected function setCurrentQuestion($val) {
		$this->currentQuestion = $val;
	}

	protected function getTemplateFile() {
		return $this->templateFile;
	}

	protected function setTemplateFile($val) {
		$this->templateFile = $val;
	}

	protected function setPage($val) {
		$this->page = $val;
	}

	protected function getPage() {
		return $this->page;
	}

	protected function setComponentHandle($val) {
		$this->componentHandle = $val;
	}

	protected function getComponentHandle() {
		return $this->componentHandle;
	}
}
