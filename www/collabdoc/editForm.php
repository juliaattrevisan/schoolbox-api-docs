<?php

/**
 * Create a new group pad
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

$homepageId = $_POST['homepageId'];
$name = $_POST['name'];

$spawn = new \Etherpad\Spawnpoint();
$newPad = $spawn->spawn($name, $homepageId);

/* Go to edit screen for now
 */
$this->localRedir("/collabdoc/edit/{$newPad->getId()}/{$homepageId}");
