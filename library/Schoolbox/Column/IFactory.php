<?php
/**
 * Base interface for column factories.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Column;

interface IFactory {
	/**
	 * @param $type
	 *
	 * @return IColumnType
	 */
	public function build($type);
}