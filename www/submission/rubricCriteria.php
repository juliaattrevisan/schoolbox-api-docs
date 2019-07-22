<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */


// Redirect to new rubric manager form
$this->localRedir('/rubric/manager/' . $this->arg[0], null, 301);
exit;
