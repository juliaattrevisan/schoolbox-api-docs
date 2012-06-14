<?php
/**
 * Review completion. Marks when an assessment review is finished.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

$assessHandler = new Assess_Main();
$attemptHandler = new Assess_Attempt();
$groupHandler = new Group();

if (!is_numeric($this->arg[0])) {
    error(112);
}

if (!is_numeric($this->arg[1])) {
    error(118);
}

$assess = $assessHandler->getByID($this->arg[0]);
$attempt = $attemptHandler->getByID($this->arg[1]);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

if (!$attempt) {
    error(118);
}

if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
    error(127);
}

$attemptHandler->reviewed($this->arg[1]);

SitePage::localRedir('/assess/results/' . $this->arg[0]);

die();

