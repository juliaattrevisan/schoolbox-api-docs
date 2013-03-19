<?php
/**
 * Interface for configuration loader classes.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;
interface IConfigLoader {
	public function get($var);
}