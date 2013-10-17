<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\StudentReportCard;

use Schoolbox\Collection;
use Schoolbox\ConfigLoader;
use Schoolbox\External\SubjectMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class CommentMapper extends Mapper {

	/** @var IConfigLoader */
	protected $config_loader;

	/**
	 * @param  IConfigLoader $config_loader
	 *
	 * @return $this
	 */
	public function setConfigLoader($config_loader) {
		$this->config_loader = $config_loader;
		return $this;
	}

	/**
	 * @return IConfigLoader
	 */
	public function getConfigLoader() {
		return $this->config_loader;
	}

	/**
	 * Sets up the config loader in this object.
	 */
	public function setup() {
		parent::setup();
		$this->setConfigLoader(new ConfigLoader());
	}

	protected function mapping() {

		// register table and model
		$this->hasTable('reportcard_comment');
		$this->hasModel('\\Schoolbox\\Assessment\\StudentReportCard\\Comment');

		// register mapping
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'category_id',
			'property'   => 'category',
			'type'       => self::TYPE_MODEL,
			'join'       => 'category',
		));
		$this->hasMap(array(
			'column'     => 'commenter_id',
			'property'   => 'commenter',
			'type'       => self::TYPE_MODEL,
			'join'       => 'commenter',
		));
		$this->hasMap(array(
			'column'     => 'student_id',
			'property'   => 'student',
			'type'       => self::TYPE_MODEL,
			'join'       => 'student',
		));
		$this->hasMap(array(
			'column'     => 'body',
			'property'   => 'body',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'created',
			'property'   => 'created',
			'type'       => self::TYPE_DATETIME,
			'created_timestamp' => true,
		));
		$this->hasMap(array(
			'column'     => 'modified',
			'property'   => 'modified',
			'type'       => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'     => 'deleted',
			'property'   => 'deleted',
			'type'       => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'     => 'student_visibility',
			'property'   => 'studentVisibility',
			'type'       => self::TYPE_BOOLEAN,
		));
		$this->hasMap(array(
			'column'     => 'parent_visibility',
			'property'   => 'parentVisibility',
			'type'       => self::TYPE_BOOLEAN,
		));

		// register joins
		$this->hasJoin('category', array(
			'mapper'            => '\\Schoolbox\\Resource\\CategoryMapper',
			'this_key'          => 'category_id',
			'other_key'         => 'id',
			'this_property'     => 'category',
			'other_property'    => 'id'
		));
		$this->hasJoin('commenter', array(
			'mapper'            => '\\Schoolbox\\UserMapper',
			'this_key'          => 'commenter_id',
			'other_key'         => 'id',
			'this_property'     => 'commenter',
			'other_property'    => 'id'
		));
		$this->hasJoin('student', array(
			'mapper'            => '\\Schoolbox\\UserMapper',
			'this_key'          => 'student_id',
			'other_key'         => 'id',
			'this_property'     => 'student',
			'other_property'    => 'id'
		));

		$this->hasSoftDeleteDateField('deleted');
		$this->registerCallback('preSave', array($this, 'preSave'));
		$this->registerCallback('postSave', array($this, 'postSave'));

	}

	public function findByColumn($columnName, $columnValue, $options = array()) {
		$commentCollection = parent::findByColumn($columnName, $columnValue, $options);

		$categoryMapper = $this->getJoinMapper('category');
		$studentMapper = $this->getJoinMapper('student');
		$commenterMapper = $this->getJoinMapper('commenter');

		foreach($commentCollection as &$comment) {
			/** @var Comment $comment */
			$comment->setCategory($categoryMapper->findById($comment->getCategory()->getId()));
			$comment->setStudent($studentMapper->findById($comment->getStudent()->getId()));
			$comment->setCommenter($commenterMapper->findById($comment->getCommenter()->getId()));
		}

		return $commentCollection;
	}

	public function findById($identifierValue) {
		return $this->findByColumn('id', $identifierValue)->first();
	}


	public function findByStudentAndCategory($studentId, $categoryId, $includeDeletions = false) {

		$categoryMapper = $this->getJoinMapper('category');
		$studentMapper = $this->getJoinMapper('student');
		$commenterMapper = $this->getJoinMapper('commenter');

		$query = "
			SELECT
				{$this->getColumnString('com')},
				{$categoryMapper->getColumnString('cat')},
				{$studentMapper->getColumnString('su')},
				{$commenterMapper->getColumnString('cu')}
			FROM {$this->getTable()} as `com`
			LEFT JOIN {$this->getJoinSql('category','com','cat')}
			LEFT JOIN {$this->getJoinSql('student','com','su')}
			LEFT JOIN {$this->getJoinSql('commenter','com','cu')}
			WHERE com.student_id = ?
			AND com.category_id = ?
		";

		if(!$includeDeletions) {
			$query .= 'AND com.deleted IS NULL';
		}

		$query .= ' ORDER BY created DESC';

		$stmt = $this->database->prepare($query);
		$stmt->execute(array($studentId, $categoryId));

		$commentCollection = new Collection();

		while($record = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			/** @var $commentObject Comment */
			$commentObject = $this->hydrate($record, 'com');
			$commentObject->setCategory($categoryMapper->hydrate($record, 'cat'));
			$commentObject->setStudent($studentMapper->hydrate($record, 'su'));
			$commentObject->setCommenter($commenterMapper->hydrate($record, 'cu'));
			$commentCollection->add($commentObject);
		}

		return $commentCollection;

	}

	public function preSave($comment) {
		// for now, nothing
	}

	/**
	 * Post-save callback. Send emails.
	 *
	 * @param Comment $comment
	 */
	public function postSave($comment) {

		// retrieve as it exists in the DB (possible body truncation, auto-generated timestamps, etc.)
		/** @var $comment Comment */
		$comment = CommentMapper::getInstance()->findById($comment->getId());


		// right now, this method just logs the emails that WOULD be sent, until we're confident it works as expected
		if($fp = fopen('/tmp/mails.log', 'a')) {

			$userCollection = $this->findAllUsersWhoCanView($comment);

			fputs($fp, sprintf("On %s, %s commented in %s: '%s'\n",
				$comment->getRelevantDate()->getDateTimeObject()->format('r'),
				$comment->getCommenter()->formatName(),
				$comment->getCategory()->getName(),
				$comment->getBody()
			));

			foreach($userCollection as $user) {
				/** @var $user User  */
				// exclude the sender!
				if($user->getId() == $comment->getCommenter()->getId()) {
					continue;
				}
				fputs($fp, sprintf(" + Sent to '%s' <%s>\n", $user->formatName(), $user->getEmail()));
			}
			fputs($fp, "\n");
			fclose($fp);
		}
	}

	/**
	 * Find all the users who can view the given comment
	 *
	 * @param Comment $comment
	 * @return Collection
	 */
	public function findAllUsersWhoCanView($comment) {
		$userCollection = new Collection();

		// merge in teachers
		$comment->getCategory()->loadTeachers();
		$userCollection->merge($comment->getCategory()->getTeachers());

		// if visible to parents, find the parents of the student of the comment
		if($comment->isVisibleToParent()) {
			$comment->getStudent()->loadParents();
			$userCollection->merge($comment->getStudent()->getParents());
		}

		// if visible to student, find the student of the comment
		if($comment->isVisibleToStudent()) {
			$userCollection->add($comment->getStudent());
		}

		return $userCollection;
	}


}