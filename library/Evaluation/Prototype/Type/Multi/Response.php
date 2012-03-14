<?php
/**
 * Evaluation_Prototype_Type_Multi_Response
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Evaluation_Prototype_Type_Multi_Response extends Evaluation_Prototype_Response_Abstract {

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
