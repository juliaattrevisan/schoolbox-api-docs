<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$returnHandler = new SubmissionReturn();

$downloader = new Crocodoc\Download($this->arg[0], $returnHandler);
$downloader->start();