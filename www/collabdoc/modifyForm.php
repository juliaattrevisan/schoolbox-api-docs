<?php
/**
 * Create a new group pad
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$homepageId = $_POST['homepageId'];
$name = $_POST['name'];

$spawn = new \Etherpad\Lovecraft($this);
$newPad = $spawn->spawn($name, $homepageId);

/* Go to edit screen for now
 */
$this->localRedir("/collabdoc/modify/{$newPad->getId()}/{$homepageId}");