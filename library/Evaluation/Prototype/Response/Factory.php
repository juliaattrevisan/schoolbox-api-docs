<?php
/**
 * Evaluation_Prototype_Response_Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Evaluation_Prototype_Response_Factory extends DeprecatedModel {
	private $type_id = null;
	private $preloadedData = null;

	public function setPreloadedData($val) {
		$this->preloadedData = $val;
	}

	public function getPreloadedData() {
		return $this->preloadedData;
	}

	public function clearPreloadedData() {
		$this->setPreloadedData(null);
	}

	public function load($type_id) {
		$this->setTypeId($type_id);

		$responseType = $this->getResponseType();

		switch($responseType['classname']) {
			case 'Short':
				$object = new Evaluation_Prototype_Type_Short_Response($this->getPreloadedData());
				break;

			case 'Multi':
				$object = new Evaluation_Prototype_Type_Multi_Response($this->getPreloadedData());
				break;

			case 'Match':
				$object = new Evaluation_Prototype_Type_Match_Response($this->getPreloadedData());
				break;

			case 'Fillgap':
				$object = new Evaluation_Prototype_Type_Cloze_Response($this->getPreloadedData());
				break;

			default:
				$object = null;
				break;
		}

		return $object;
	}

	/**
	 * @return array|bool
	 */
	private function getResponseType() {
		$query = "SELECT *
					FROM assess_luQuestionType
					WHERE type_id = '{$this->getTypeId()}'";

		return $this->db->getRow($query);
	}

	/**
	 * @param  $val
	 * @return void
	 */
	private function setTypeId($val) {
		$this->type_id = $val;
	}

	/**
	 * @return null
	 */
	private function getTypeId() {
		return $this->type_id;
	}
}
