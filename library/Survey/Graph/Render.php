<?php
/**
 * Survey_Graph_Render
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Survey_Graph_Render {
	protected $currentQuestion = array();
	protected $templateFile = null;
	protected $page = null;
    private   $componentHandle;

    /**
	 * Constructor
	 *
	 * @param  $page
	 * @param  $currentQuestion
	 * @param  $templateFile
	 */
	public function __construct($page, $currentQuestion, $templateFile) {
		$this->setPage($page);
		$this->setTemplateFile($templateFile);
		$this->setCurrentQuestion($currentQuestion);
		$this->setComponentHandle('temp' . $currentQuestion['question_id']);
	}

	/**
	 * Graph a barchart
	 *
	 * @return void
	 */
	public function graph() {
		$currentQuestion = $this->getCurrentQuestion();

		$this->getPage()->setFile($this->getComponentHandle(), $this->getTemplateFile());
		$this->getPage()->setBlock($this->getComponentHandle(), 'series', 'series_block');
		$this->getPage()->setBlock('series', 'series_separator', 'series_separator_block');

		/* Render out data set
		 */
		$i = 0;

		if (is_array($currentQuestion['summary']) && count($currentQuestion['summary'])) {

			foreach($currentQuestion['summary'] as $key => $value) {

				/* If the option hasn't been responded to
				 */
				if ($value['text'] == '') {
					$value['text'] = 'Unanswered';
				}

				$this->getPage()->setVar(array(
					'SERIES_LABEL' => $value['text'],
					'SERIES_DATA' => $value['values'],
					'SERIES_INDEX' => $i
				));

				if ($i < (count($currentQuestion['summary'])-1)) {
					$this->getPage()->parse('series_separator_block', 'series_separator');
				}
				else {
					$this->getPage()->setVar('series_separator_block', '');
				}

				$this->getPage()->parse('series_block', 'series', true);

				/* This index is used to determine whether or not to parse out a
				 * separator comma between object literals in data set
				 */
				$i++;
			}

			$this->getPage()->setVar(array(
				'GRAPH_ID' => 'surveyQuestion_'
					. intval($currentQuestion['question_id'])
					. '_'
					. md5($currentQuestion['question']),
			));
		}
		else {
			$this->getPage()->setVar('series_block', '');
		}

		$this->getPage()->parse('graph_block', $this->getComponentHandle());
	}

	/**
	 * Set current question
	 *
	 * @param  $val
	 * @return void
	 */
	protected function setCurrentQuestion($val) {
		$this->currentQuestion = $val;
	}

	/**
	 * Get current question
	 *
	 * @return array
	 */
	protected function getCurrentQuestion() {
		return $this->currentQuestion;
	}

	/**
	 * Set template file
	 *
	 * @param  $val
	 * @return void
	 */
	protected function setTemplateFile($val) {
		$this->templateFile = $val;
	}

	/**
	 * Get template file
	 *
	 * @return null
	 */
	protected function getTemplateFile() {
		return $this->templateFile;
	}

	/**
	 * Set page
	 *
	 * @param  $val
	 * @return void
	 */
	protected function setPage($val) {
		$this->page = $val;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	protected function getPage() {
		return $this->page;
	}

	/**
	 * Set component handle
	 *
	 * @param  $val
	 * @return void
	 */
	protected function setComponentHandle($val) {
		$this->componentHandle = $val;
	}

	/**
	 * Get component handle
	 *
	 * @return
	 */
	protected function getComponentHandle() {
		return $this->componentHandle;
	}
}
