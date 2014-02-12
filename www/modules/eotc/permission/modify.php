<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* Edit existing permissions for someone
 */
if (is_numeric($this->arg[0])) {
	$eventPermissionMapper = new \EOTC\EventPermissionMapper();
	$allPermissions = $eventPermissionMapper->findByColumn('user_id', $this->arg[0]);

	new \Alaress_Probe($allPermissions);
}
/* Create new permissions for someone
 */
else {
	echo 'Create new perms';
}

exit();