<?php
/**
 * Eportfolio_PageMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Eportfolio_PageMapper extends Mapper {

	/**
	 * Database table
	 *
	 * @var string
	 */
	protected $table = 'eportfolio_config';

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasMap(array(
			'column'     => 'page_id',
			'property'   => 'page_id',
			'type'       => Mapper::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'page_title',
			'property'   => 'page_title',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'uid',
			'property'   => 'uid',
			'type'       => Mapper::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'template',
			'property'   => 'template',
			'type'       => Mapper::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'left_column_width',
			'property'   => 'left_column_width',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'left_head_font',
			'property'   => 'left_head_font',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'left_head_bg',
			'property'   => 'left_head_bg',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'left_font',
			'property'   => 'left_font',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'left_bg_primary',
			'property'   => 'left_bg_primary',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'left_bg_secondary',
			'property'   => 'left_bg_secondary',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'left_link',
			'property'   => 'left_link',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'left_hover',
			'property'   => 'left_hover',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'right_column_width',
			'property'   => 'right_column_width',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'right_head_font',
			'property'   => 'right_head_font',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'right_head_bg',
			'property'   => 'right_head_bg',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'right_font',
			'property'   => 'right_font',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'right_bg_primary',
			'property'   => 'right_bg_primary',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'right_bg_secondary',
			'property'   => 'right_bg_secondary',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'right_link',
			'property'   => 'right_link',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'right_hover',
			'property'   => 'right_hover',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'top_head_font',
			'property'   => 'top_head_font',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'top_head_bg',
			'property'   => 'top_head_bg',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'top_font',
			'property'   => 'top_font',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'top_bg_primary',
			'property'   => 'top_bg_primary',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'top_bg_secondary',
			'property'   => 'top_bg_secondary',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'top_link',
			'property'   => 'top_link',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'top_hover',
			'property'   => 'top_hover',
			'type'       => Mapper::TYPE_STRING,
		));

		parent::mapping();
	}
}