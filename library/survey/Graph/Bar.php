<?php
/**
 * Survey_Graph_Barchart
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/survey/Graph/Abstract.php');

class Survey_Graph_Barchart extends Survey_Graph_Abstract {

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
				'GRAPH_ID' => 'surveyQuestion_' . md5($currentQuestion['question']),
			));
		}
		else {
			$this->getPage()->setVar('series_block', '');
		}

		$this->getPage()->parse('graph_block', $this->getComponentHandle());
	}

}
