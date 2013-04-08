<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

new Alaress_Probe($_POST);

/* Check create/update permissions
 */

/* Update page
 */
if (isset($_POST['page_id']) && is_numeric($_POST['page_id'])) {
}
/* Create page
 */
else if (isset($_POST['page_title']) && strlen(trim($_POST['page_title']))) {
	/* Create page
	 */

	/* Apply components?
	 */

	/* Delete all previous permissions
	 */

	/* Add new permissions
	 */

	/* Redirect to newly created page
	 */
}
else {
	error(2);
}

