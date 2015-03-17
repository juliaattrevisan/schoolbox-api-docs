<?php
/**
 * Alaress_Date_Locale_Static
 *
 * Localisation for static mock testing in Australian formats.
 *
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Date_Locale_Static extends Alaress_Date_Locale_Abstract {

	/* Pretty formatting
	 */
	protected $local = 'd/m/Y g:ia';
	protected $localShort = 'd/m/Y';

	protected $long = 'l F jS Y g:ia';
	protected $longDateOnly = 'l F jS Y';
	protected $short = 'jS M Y g:ia'; // Closest to 'r'

	protected $dateOnly = 'jS M Y';
	protected $timeOnly = 'g:ia';


	/* Calendar date
	 */
	protected $calendarDate = 'jS M';
	protected $calendarDateTime = 'jS M g:ia';
}
