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
	}

//	/**
//	 * Retrieves all showcased work for a particular user
//	 *
//	 * @param $user_id
//	 *
//	 * @return ActivityCollection
//	 */
//	public function findShowcasedWork($user_id) {
//
//	}
//
//
//	/**
//	 * Retrieves all showcased work for a particular user
//	 *
//	 * @param $user_id
//	 *
//	 * @return ActivityCollection
//	 */
//	public function findShowcasedWork_old($user_id) {
//		$return_join = $this->getJoinMapper('submission_return');
//		$box_join = $return_join->getJoinMapper('submission_box');
//		$submission_join = $this->getJoinMapper('student_submission');
//		$category_join = $box_join->getJoinMapper('category');
//
//		// Have hydrateFromRow() in ActivityFactory
//		$query = "
//			SELECT
//				{$this->getColumnString('showcase')},
//				{$return_join->getColumnString('sr')},
//				{$box_join->getColumnString('sb')},
//				{$submission_join->getColumnString('ss')},
//				{$category_join->getColumnString('c')}
//			FROM {$this->getTable()} showcase
//			LEFT JOIN {$this->getJoinSql('submission_return', 'showcase', 'sr')}
//			LEFT JOIN {$return_join->getJoinSql('submission_box', 'sr', 'sb')}
//			LEFT JOIN {$this->getJoinSql('student_submission', 'showcase', 'ss')}
//			LEFT JOIN {$box_join->getJoinSql('category', 'sb', 'c')}
//			WHERE showcase.uid = :user_id
//			ORDER BY c.name, sr.id DESC
//		";
//
//		$statement = $this->database->prepare($query);
//		$params['user_id'] = $user_id;
//
//		foreach ($params as $key => $value) {
//			$statement->bindParam($key, intval($value), \PDO::PARAM_INT);
//		}
//
//		$statement->execute();
//
//		return $this->hydrateList($statement, 'showcase');
//	}
}