<?php
require_once('../include/Bootstrap.php');

/* Exception handles to try:

DUPLICATE_USER		Stored in Default/User.php and Wenona/User.php
SPECIFIC			Stored in Wenona/User.php
ASS_BLASTER			Stored in Default/User.php

*/
try {
	throw new Exception_User('SPECIFIC');
}
catch (Exception $e) {
	echo $e->errorMessage();
}

echo "<HR>";