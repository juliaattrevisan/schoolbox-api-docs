<?php
/**
 * Factory for Schoolbox\Column\IColumnType objects.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Column;
use Schoolbox\Mapper;

class Factory {
	/**
	 * @param $type
	 *
	 * @return IColumnType
	 */
	public function build($type) {
		$mapper = null;
		switch ($type) {
			case Mapper::TYPE_BOOLEAN:
				return new Boolean();
				break;

			case Mapper::TYPE_STRING:
			case Mapper::TYPE_LOB:
				return new Base();
				break;

			case Mapper::TYPE_INTEGER:
				return new Integer();
				break;

			case Mapper::TYPE_DATE:
				return new Date();
				break;

			case Mapper::TYPE_DATETIME:
				return new Datetime();
				break;

			case Mapper::TYPE_TIME:
				return new Time();
				break;

			case Mapper::TYPE_TIMESTAMP:
				return new Timestamp();
				break;

			case Mapper::TYPE_SERIALIZED:
				return new Serialized();
				break;

			case Mapper::TYPE_DECIMAL:
				return new Integer();
				break;

			default:
				break;
		}
		return $mapper;
	}
}