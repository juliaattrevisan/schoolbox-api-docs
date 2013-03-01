<?php
/**
 * Calendar_Api_Cache
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Api_Cache extends Alaress_Cache_Locker_Abstract {

	/**
	 * The url of the stream you just got
	 *
	 * @var
	 */
	protected $url = '';

	/**
	 * A prefix for cached filenames
	 *
	 * @var string
	 */
	protected $cacheFilePrefix = 'calendar';

	/**
	 * The duration of a cached version before it becomes expired
	 * This is a duration measured in seconds
	 *
	 * @var null
	 */
	protected $cachedTime = 3600;

	/**
	 * The duration of a lock file before it becomes expired
	 * This is a duration measured in seconds
	 *
	 * @var int
	 */
	protected $lockTime = 7200;

	/**
	 * Show plain debugging messages
	 *
	 * @var bool
	 */
	protected $verbose = true;
}
