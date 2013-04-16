<?php
/**
 * Base mapper specific to objects stored outside of the Schoolbox database,
 * such as timetables, subjects and class lists.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\External;
use Schoolbox;

class Mapper extends Schoolbox\Mapper {

	/**
	 * Interface for connecting to external database.
	 *
	 * @var \External_User()
	 */
	protected $external_user;

	/**
	 * Creates mapper, and sets up external database interface.
	 */
	public function __construct() {
		parent::__construct();
		$this->external_user = new \External_User();
	}
}
