<?php
/**
 * Survey import. Uses handlers to import their question types from Moodle format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

SitePage::extendExecutionTime(300);

if (!is_numeric($this->arg[0])) {
    error(18);
}

$data = null;
if ($_FILES['importXML']['tmp_name']) {
    $data = file_get_contents($_FILES['importXML']['tmp_name']);
} else {
    error(134);
}

$import = new Survey_Import_Xml($this->arg[0], new Survey_Main(), new Survey_Section(), new Survey_Factory(), $data, $this);
$import->import();

SitePage::localRedir('/survey/summary/' . $import->getAssessId());
