<?php
/**
 * Mapper for the Submission model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox;
use Schoolbox\Assessment\Activity;
use Schoolbox\Assessment\ActivityCollection;

class ReturnedWorkMapper extends Schoolbox\Mapper {

	protected function mapping () {
		$this->hasTable('submission_return');
		$this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBox\\ReturnedWork');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'box_id',
			'property'   => 'submissionBox',
			'type'       => self::TYPE_MODEL,
			'join'       => 'submission_box',
		));
		$this->hasMap(array(
			'column'     => 'owner',
			'property'   => 'owner',
			'type'       => self::TYPE_MODEL,
			'join'       => 'owner',
		));
		$this->hasMap(array(
			'column'     => 'comment',
			'property'   => 'comment',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'mark',
			'property'   => 'mark',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'norm_mark',
			'property'   => 'normalizedMark',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'filename',
			'property'   => 'filename',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'submitted',
			'property'   => 'submitted',
			'type'       => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'     => 'returned',
			'property'   => 'returned',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasJoin('submission_box', array(
			'mapper'    => '\\Schoolbox\\Assessment\\Component\\SubmissionBoxMapper',
			'this_key'  => 'box_id',
			'other_key' => 'id',
		));
		$this->hasJoin('owner', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'owner',
			'other_key' => 'id',
		));
	}

	/**
	 * Gets the list of activities related to submission returns.
	 *
	 * An activity is returned for each submission return for this user in the
	 * given categories.
	 *
	 * @param int   $user_id    The user for who activities are fetched for.
	 * @param int   $limit      The max number of activities to be fetched.
	 * @param array $categories The categories to fetch activities for.
	 *
	 * @return ActivityCollection
	 */
	public function getActivities($user_id, $limit, $categories) {
		$collection    = new ActivityCollection();
		$box_join      = $this->getJoinMapper('submission_box');
		$category_join = $box_join->getJoinMapper('category');
		$query = "
			SELECT
				{$this->getColumnString('sr')},
				{$box_join->getColumnString('sb')},
				{$category_join->getColumnString('c')}
			FROM {$this->getTable()} sr
			LEFT JOIN {$this->getJoinSql('submission_box', 'sr', 'sb')}
			LEFT JOIN {$box_join->getJoinSql('category', 'sb', 'c')}
			WHERE sb.category IN {$box_join->getArrayPlaceholders($categories, 'category')}
			AND sr.owner = :user_id
			ORDER BY sr.returned DESC
			LIMIT :limit
		";
		$statement = $this->database->prepare($query);
		$params    = $this->getArrayParameters($categories, 'category');
		$params['limit']   = $limit;
		$params['user_id'] = $user_id;
		foreach ($params as $key => $value) {
			$statement->bindParam($key, intval($value), \PDO::PARAM_INT);
		}
		$statement->execute();
		foreach ($statement as $row) {
			$return   = $this->hydrate($row, 'sr');
			$box      = $box_join->hydrate($row, 'sb');
			$category = $category_join->hydrate($row, 'c');
			$box->setCategory($category);
			$return->setSubmissionBox($box);

			$activity = new Activity();
			$activity
				->setEvent('returned')
				->setSubmissionBox($box)
				->setCategory($box->getCategory())
				->setReturnedWork($return)
				->setDate($return->getReturned());
			$collection->add($activity);
		}
		return $collection;
	}
}
