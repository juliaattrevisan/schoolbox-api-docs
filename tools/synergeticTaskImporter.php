<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once __DIR__ . '/../conf/core/require-cli.php';

$allImporters = array(
	new \External_Synergetic_Import_Task(),
	new \External_Synergetic_Import_Marks()
);

foreach($allImporters as $currentImporter) {
	$currentImporter->import();
}
