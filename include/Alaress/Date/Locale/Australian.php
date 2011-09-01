<?php
/**
 * Alaress_Date_Locale_Australian
 *
 * Localisation for Australian date formats. Define your own getters/setters or
 * other formats specific to this localisation if you want.
 *
 * Alternatively, subclass this instead e.g.
 *
 * Alaress_Date_Locale_Melbourne extends Alaress_Date_Locale_Australian
 * <define extra class vars, define extra methods, etc>
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Date_Locale_Australian extends Alaress_Date_Locale_Abstract {

	/* Pretty formatting
	 */
	protected $local = 'd-m-Y H:i:s';
	protected $localShort = 'd/m/Y';

	protected $long = 'l F jS Y g:ia';
	protected $short = 'jS M Y g:ia'; // Closest to 'r'

	protected $noTime = 'jS M Y';
	protected $time = 'h:ia';

	/* Database specific formatting
	 */
	protected $database = 'Y-m-d H:i:s';
	protected $databaseDate = 'Y-m-d';
	protected $databaseTime = 'H:i:s';
	protected $timestamp = 'U';
}
