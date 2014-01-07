<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$document = new \Crocodoc\Document(Cfg::get('submission_dir') . '/18.dat');
$result = $document->upload();

/* TODO: Add error check here, if $result->error exists
 */
if (isset($result->error)) {
	throw new Exception($result->error);
}

if (isset($result->uuid)) {
	$crocodoc = new \Crocodoc\Loader($result->uuid, $_SESSION['user']);
	$crocodoc->render();
}

exit();