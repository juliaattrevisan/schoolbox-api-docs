<?php
/**
 * Exception_Locale_Wenona_User
 *
 * Wenona-specific error messages for Users.
 * This extends the default definitions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Exception_Locale_Wenona_User extends Exception_Locale_Default_User {
	const DUPLICATE_USER = 'Shit!';
	const SPECIFIC = 'This is a Wenona specific one';
}