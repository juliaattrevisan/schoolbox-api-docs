<?php
/**
 * Evaluation_Prototype_Type_Cloze_Response
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Evaluation_Prototype_Type_Cloze_Response extends Evaluation_Prototype_Response_Abstract {

	/**
	 * Key to use in summary
	 *
	 * @return string
	 */
	public function getKey() {
		if ($this->response != '') {
			return array(implode(',', unserialize($this->response)));
		}

		return array('');
	}
}
