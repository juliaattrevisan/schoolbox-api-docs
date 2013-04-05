<?php
/**
 * Non-static configuration loader for Schoolbox models and objects.
 *
 * Uses old static config under the hood, but is not a singleton so it is
 * able to be mocked in unit tests.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;
use Schoolbox;
use Cfg;

class ConfigLoader implements IConfigLoader {
	public function get($var) {
		return Cfg::get($var);
	}
}