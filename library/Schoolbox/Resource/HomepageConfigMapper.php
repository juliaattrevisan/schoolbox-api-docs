<?php
/**
 * Homepage Config mapper, to create HomepageConfig objects
 * It contains things such as which template was initially used to create the
 * homepage, the column widths and colours
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 

namespace Schoolbox\Resource;
use Schoolbox\Mapper;
use Schoolbox\Collection;


class HomepageConfigMapper extends Mapper {

	protected function mapping() {
		$this->hasModel('\\Schoolbox\\Resource\\HomepageConfig');
		$this->hasTable('homepage_config');
		$this->hasMap(array(
			'column'     => 'cid',
			'property'   => 'category',
			'type'       => self::TYPE_MODEL,
			'join'       => 'category',
		));
		$this->hasMap(array(
			'column'     => 'template',
			'property'   => 'template',
			'type'       => self::TYPE_MODEL,
			'join'       => 'template',
		));

		/**
		 * Column widths
		 */

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

		/**
		 * Left Colours
		 */

		$this->hasMap(array(
			              'column'     => 'left_head_font',
			              'property'   => 'leftHeadFont',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'left_head_bg',
			              'property'   => 'leftHeadBg',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'left_font',
			              'property'   => 'leftFont',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'left_bg_primary',
			              'property'   => 'leftBgPrimary',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'left_bg_secondary',
			              'property'   => 'leftBgSecondary',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'left_link',
			              'property'   => 'leftLink',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'left_hover',
			              'property'   => 'leftHover',
			              'type'       => self::TYPE_STRING,
		              ));

		/**
		 * Right Colours
		 */

		$this->hasMap(array(
			              'column'     => 'right_head_font',
			              'property'   => 'rightHeadFont',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'right_head_bg',
			              'property'   => 'rightHeadBg',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'right_font',
			              'property'   => 'rightFont',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'right_bg_primary',
			              'property'   => 'rightBgPrimary',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'right_bg_secondary',
			              'property'   => 'rightBgSecondary',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'right_link',
			              'property'   => 'rightLink',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'right_hover',
			              'property'   => 'rightHover',
			              'type'       => self::TYPE_STRING,
		              ));

		/**
		 * Top colours
		 */

		$this->hasMap(array(
			              'column'     => 'top_head_font',
			              'property'   => 'topHeadFont',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'top_head_bg',
			              'property'   => 'topHeadBg',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'top_font',
			              'property'   => 'topFont',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'top_bg_primary',
			              'property'   => 'topBgPrimary',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'top_bg_secondary',
			              'property'   => 'topBgSecondary',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'top_link',
			              'property'   => 'topLink',
			              'type'       => self::TYPE_STRING,
		              ));

		$this->hasMap(array(
			              'column'     => 'top_hover',
			              'property'   => 'topHover',
			              'type'       => self::TYPE_STRING,
		              ));

		/**
		 * Joins
		 */
		$this->hasJoin('category', array(
			'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
			'this_key'  => 'category',
			'other_key' => 'id',
		));

        $this->hasJoin('template', array(
            'mapper'    => '\\Schoolbox\\Resource\\HomepageTemplateMapper',
            'this_key'  => 'template',
            'other_key' => 'id',
        ));


	}

}