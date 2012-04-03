<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
$assessHandler = new Survey_Main();

if (!is_numeric($this->arg[0])) {
    error(112);
}

$assess = $assessHandler->getByID($this->arg[0]);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($assess['assess_id'])) {
	error(112);
}

$exporter = new Survey_Export_CSV($this->arg[0], new Survey_Factory(), new Survey_Attempt(), new Survey_Question());

if (is_numeric($this->arg[1])) {
	$exporter->setQuestionFilter($this->arg[1]);
}

$exporter->generate();

exit(1);