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
use Schoolbox\Assessment\ActivityFactory;
use Schoolbox\Assessment\ActivityCollection;

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
			'column'     => 'submission_return_id',
			'property'   => 'submissionReturn',
			'type'       => self::TYPE_MODEL,
			'join'       => 'submission_return',
		));

		$this->hasJoin('submission_return', array(
			'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\ReturnedWorkMapper',
			'this_key'  => 'submission_return_id',
			'other_key' => 'id',
		));

		$this->hasMap(array(
			'column'     => 'submission_id',
			'property'   => 'submission',
			'type'       => self::TYPE_MODEL,
			'join'       => 'student_submission',
		));

		$this->hasJoin('student_submission', array(
			'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBox\\SubmissionMapper',
			'this_key'  => 'submission_id',
			'other_key' => 'id',
		));
	}

	/**
	 * Retrieves all showcased work for a particular user
	 *
	 * @param $user_id
	 *
	 * @return ActivityCollection
	 */
	public function findShowcasedWork($user_id) {
		$return_join = $this->getJoinMapper('submission_return');
		$box_join = $return_join->getJoinMapper('submission_box');
		$submission_join = $this->getJoinMapper('student_submission');
		$category_join = $box_join->getJoinMapper('category');

		// Have hydrateFromRow() in ActivityFactory
		$query = "
			SELECT
				{$this->getColumnString('showcase')},
				{$return_join->getColumnString('sr')},
				{$box_join->getColumnString('sb')},
				{$submission_join->getColumnString('ss')},
				{$category_join->getColumnString('c')}
			FROM {$this->getTable()} showcase
			LEFT JOIN {$this->getJoinSql('submission_return', 'showcase', 'sr')}
			LEFT JOIN {$return_join->getJoinSql('submission_box', 'sr', 'sb')}
			LEFT JOIN {$this->getJoinSql('student_submission', 'showcase', 'ss')}
			LEFT JOIN {$box_join->getJoinSql('category', 'sb', 'c')}
			WHERE showcase.uid = :user_id
			ORDER BY c.name, sr.id DESC
		";

		$statement = $this->database->prepare($query);
		$params['user_id'] = $user_id;

		foreach ($params as $key => $value) {
			$statement->bindParam($key, intval($value), \PDO::PARAM_INT);
		}

		$statement->execute();

		return $this->hydrateList($statement, 'showcase');
	}
}