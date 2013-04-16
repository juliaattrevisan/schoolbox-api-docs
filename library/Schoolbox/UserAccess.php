<?php
/**
 * User access for Schoolbox.
 *
 * Currently just a namespace, but may be fleshed out to a whole class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox;

class UserAccess {

	const SERVICE_HOMEPAGE    = 1;
	const SERVICE_CALENDAR    = 2;
	const SERVICE_NEWS        = 3;
	const SERVICE_TUTORIAL    = 4;
	const SERVICE_CMS         = 5;
	const SERVICE_HOUSEMASTER = 6;

	const ACCESS_DENY  = 0;
	const ACCESS_READ  = 1;
	const ACCESS_WRITE = 2;
	const ACCESS_FULL  = 3;

	const GROUP_TYPE_RESTRICTED = 1;
	const GROUP_TYPE_MODERATED  = 2;
	const GROUP_TYPE_FREE       = 3;

}
