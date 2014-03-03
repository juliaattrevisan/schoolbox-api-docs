<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\IPC;


use Alaress\WorkerCrew\Pooling\PoolingInterface;

class AlaressIPCProtocol implements ProtocolInterface {

	/**
	 * @return int
	 */
	public function chunkLength() {
		return 2048;
	}

	/**
	 * @param string $command
	 * @param array $args
	 * @return string
	 */
	public function prepareCommand($command, $args = null) {
		return $command . PoolingInterface::STR_RECORD_SEPARATOR . $this->flatten($args);
	}

	/**
	 * Prepare data for transmission. Inverse of ProtocolInterface::rebuild()
	 *
	 * @param array $array
	 * @return string
	 */
	public function flatten($array) {
		// if it's a string (like a serialization or something)
		if(!is_array($array)) {
			$array = array($array);
		}
		foreach($array as $k => $v) {
			$array[$k] = base64_encode(serialize($v));
		}
		return implode(PoolingInterface::STR_UNIT_SEPARATOR, $array) . PoolingInterface::STR_END_OF_TRANSMISSION;
	}

	/**
	 * Reconstitute flattened date. Inverse of ProtocolInterface::flatten()
	 *
	 * @param string $string
	 * @return array
	 */
	public function rebuild($string) {

		// trim the EOT character
		$string = trim($string, PoolingInterface::STR_END_OF_TRANSMISSION);

		// if there's a record separator, separate that first
		if(strpos($string, PoolingInterface::STR_RECORD_SEPARATOR) !== false) {
			$xpl = explode(PoolingInterface::STR_RECORD_SEPARATOR, $string);
			if(count($xpl) == 2) {
				list($command, $string) = $xpl;
			}
		}

		$xpl = explode(PoolingInterface::STR_UNIT_SEPARATOR, $string);

		foreach($xpl as $k => $v) {
			$xpl[$k] = unserialize(base64_decode($v));
		}

		if(isset($command)) {
			return array($command, $xpl);
		}

		return $xpl;
	}

	/**
	 * @return string
	 */
	public function reject() {
		return PoolingInterface::STR_NACKNOWLEDGE;
	}

	/**
	 * @return string
	 */
	public function accept() {
		return PoolingInterface::STR_ACKNOWLEDGE;
	}

	/**
	 * @return string
	 */
	public function cancel() {
		return PoolingInterface::STR_CANCEL;
	}

	/**
	 * @return string
	 */
	public function end() {
		return PoolingInterface::STR_END_OF_TRANSMISSION;
	}

	/**
	 * @param string $data
	 * @return bool
	 */
	public function isCancelled($data) {
		if(strlen($data)) {
			return $data[0] == $this->cancel();
		}
		return false;
	}

	/**
	 * @param string $data
	 * @return bool
	 */
	public function isEnded($data) {
		if(strlen($data)) {
			return strpos($data, $this->end()) !== false;
		}
		return false;
	}

	/**
	 * @param string $data
	 * @return bool
	 */
	public function isRejected($data) {
		if(strlen($data)) {
			return $data[0] == $this->reject();
		}
		return false;
	}

	/**
	 * @param string $data
	 * @return bool
	 */
	public function isAccepted($data) {
		if(strlen($data)) {
			return $data[0] == $this->accept();
		}
		return false;
	}
}