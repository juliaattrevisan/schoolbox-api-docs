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


    /**
     * Returns whether the specified column type hydrate() method returns a scalar
     *
     * @param IColumnType $columnType
     * @return bool
     */
    public function isScalar(IColumnType $columnType): bool;
}
