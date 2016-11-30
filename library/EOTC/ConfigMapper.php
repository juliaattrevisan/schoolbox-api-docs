<?php
/**
 * ConfigMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


class ConfigMapper extends \Schoolbox\Mapper {

	/**
	 * Mapping
	 */
	public function mapping() {
		$this->hasTable('eotc_config');
		$this->hasModel(Config::class);
		$this->hasMap(array(
			'column' => 'id',
			'property' => 'id',
			'type' => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column' => 'name',
			'property' => 'name',
			'type' => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column' => 'value',
			'property' => 'value',
			'type' => self::TYPE_STRING,
		));
	}

	/**
	 * Get urgent threshold
	 *
	 * @return Config
	 */
	public function findUrgentThreshold() {
		return $this->findOneByColumn('name', 'urgent_threshold');
	}

	/**
	 * Get first reminder threshold
	 *
	 * @return Config
	 */
	public function findFirstReminderThreshold()
	{
		return $this->findOneByColumn('name', 'first_reminder');
	}

	/**
	 * Get daily reminder threshold
	 *
	 * @return Config
	 */
	public function findDailyReminderThreshold()
	{
		return $this->findOneByColumn('name', 'daily_reminder');
	}

}
