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

		$this->hasMap(array(
			'column'     => 'created_at',
			'property'   => 'createdAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'updated_at',
			'property'   => 'updatedAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'deleted_at',
			'property'   => 'deletedAt',
			'type'       => self::TYPE_DATETIME,
		));
	}

	/**
	 * Get all the showcased submission boxes for a particular user,
	 * ordered alphabetically by their parent category name
	 *
	 * @param $userId
	 * @return bool|\Model
	 */
	public function getShowcasedSubmissionBoxes($userId) {
		$statement = $this->database->prepare("
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
            LEFT JOIN submission_box ON `{$this->table}`.submission_box_id = submission_box.id
            LEFT JOIN category ON submission_box.category = category.id
            WHERE eportfolio_showcase_item.uid = :userId
            ORDER BY category.name
        ");

		$statement->bindParam(":userId", $userId);
		$statement->execute();

		$rowset = $statement->fetchAll();

		return $this->hydrateList($rowset);
	}
}