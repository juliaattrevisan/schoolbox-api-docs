<?php
/**
 * Template
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Eportfolio;
use Eportfolio;
use Schoolbox;

class TemplateMapper extends Schoolbox\Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('eportfolioTemplate');
		$this->hasModel('\\Eportfolio\\Template');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'name',
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'left_column_width',
			'property'   => 'leftColumnWidth',
			'type'       => self::TYPE_DECIMAL,
		));

		$this->hasMap(array(
			'column'     => 'right_column_width',
			'property'   => 'rightColumnWidth',
			'type'       => self::TYPE_DECIMAL,
		));

		parent::mapping();
	}
}