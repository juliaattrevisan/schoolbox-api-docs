<?php
/**
 * Eportfolio_ShowcaseMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Eportfolio;
use Eportfolio;
use Schoolbox;

class ShowcaseMapper extends Schoolbox\Mapper {

	protected function mapping () {
		$this->hasTable('eportfolio_showcase_item');
		$this->hasModel('\\Eportfolio\\Showcase');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));

		$this->hasMap(array(
			'column'     => 'submission_box_id',
			'property'   => 'submissionBoxId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'uid',
			'property'   => 'uid',
			'type'       => self::TYPE_INTEGER,
		));
	}
}