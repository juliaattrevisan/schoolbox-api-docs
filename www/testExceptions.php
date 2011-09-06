<?php
/* testExceptions

Exception handles to try:

DUPLICATE_USER            Stored in Default/User.php and Wenona/User.php
WENONA_SPECIFIC           Stored in Wenona/User.php
PASSWORDS_DO_NOT_MATCH    Stored in Default/User.php
*/
require_once('../include/Bootstrap.php');

try {
	$exception = new Exception_User('DUPLICATE_USER');

	/* setLocalisation()
	 * This is also done in the constructor, this is for demonstration purposes only
	 */
	$exception->setLocalisation('Wenona');

	throw $exception;
}
catch (Exception $e) {
	echo $e->errorMessage();
}

echo "<HR>";