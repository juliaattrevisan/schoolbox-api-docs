<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\StudentReportCard;

use Mailer;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Collection;
use Schoolbox\ConfigLoader;
use Schoolbox\External\SubjectMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Swift_Mailer;
use Swift_Message;
use Swift_SwiftException;

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
		$roleMapper = $commenterMapper->getJoinMapper('role');

		$query = "
			SELECT
				{$this->getColumnString('com')},
				{$categoryMapper->getColumnString('cat')},
				{$studentMapper->getColumnString('su')},
				{$roleMapper->getColumnString('r')},
				{$commenterMapper->getColumnString('cu')}
			FROM {$this->getTable()} as `com`
			LEFT JOIN {$this->getJoinSql('category','com','cat')}
			LEFT JOIN {$this->getJoinSql('student','com','su')}
			LEFT JOIN {$this->getJoinSql('commenter','com','cu')}
			LEFT JOIN {$commenterMapper->getJoinSql('role','cu','r')}
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
			$commentObject->getCommenter()->setRole($roleMapper->hydrate($record, 'r'));
			$commentCollection->add($commentObject);
		}

		return $commentCollection;

	}


	/**
	 * @deprecated why is this here?
	 * @param $comment
	 */
	public function preSave($comment) {
		// for now, nothing
	}

	/**
	 * Post-save callback. Send emails.
	 *
	 * @deprecated in favour of CommentNotifier
	 * @param Comment $comment
	 */
	public function postSave($comment) {

		// Do nothing if mail is disabled
		$cfg = $this->getConfigLoader();
		if (!$cfg->get('mail_enable')) {
			return;
		}

		// retrieve as it exists in the DB (possible body truncation, auto-generated timestamps, etc.)
		/** @var $comment Comment */
		$comment = CommentMapper::getInstance()->findById($comment->getId());

		// extract elements we'll be using
		$commenter = $comment->getCommenter();
		$student = $comment->getStudent();
		$category = $comment->getCategory();

		$subjectFormat = 'Comment on %s grades in %s';

		$href = sprintf("http://%s.%s/submission/allWork/%s/%s/%s/%s",
			$cfg->get('site_hostname'), $cfg->get('site_domain'),
			$student->getId(), date('Y'), date('m') > 6 ? 2 : 1, $category->getId()
		);

		// create emailzes
		$mail = Swift_Message::newInstance();

		// set sender
		try {
			$mail->setFrom($commenter->getEmail(), $commenter->formatName());
		}
		catch(Swift_SwiftException $e) {
			$mail->setFrom($cfg->get('mail_from_address'), $commenter->formatName());
		}

		$plainBody = sprintf('%s commented in %s.'."<br/>\n",
			$commenter->formatName(),
			$category->getName()
		);

		$plainBody .= sprintf('%s wrote: "%s"'."<br/>\n",
			$commenter->formatName('{firstname}'),
			$comment->getBody()
		);

		// they're the same up to this point
		$htmlBody = nl2br($plainBody);

		// and now they diverge
		$plainBody .= sprintf("Visit %s to view the conversation.\n", $href);
		$htmlBody  .= sprintf('<a href="%s">View the conversation</a>'."<br/>\n", $href);

		// add bodyparts
		$mail->setBody($plainBody, 'text/plain', 'utf-8');
		$mail->addPart($htmlBody,  'text/html',  'utf-8');

		/* Decide who should be notified via email
		 */
		$recipients = $this->determineEmailRecipients($comment);

		foreach($recipients as $user) {
			/** @var $user User  */
			if($user->getId() == $commenter->getId()) {
				// exclude the sender!
				continue;
			}

			// if this email is being sent to the student
			if($user->getId() == $student->getId()) {
				$pronoun = 'your';
			}
			// otherwise it's being sent to a parent or teacher
			else {
				$pronoun = $student->formatName("{firstname} {lastname}'s");
			}

			$mail->setSubject(sprintf($subjectFormat, $pronoun, $category->getName()));

			try {
				// add recipient
				$toName = $user->formatName();
				// only include a name if it's actually known (may have been sent a dummy user)
				if($toName == 'Unknown User' || !$toName) {
					$mail->setTo($user->getEmail());
				}
				else {
					$mail->setTo($user->getEmail(), $toName);
				}
				Swift_Mailer::newInstance(Mailer::getSwiftTransport())->send($mail);
			}
			catch (Swift_SwiftException $e) {
				// if we had somewhere to log this, we might log this
			}
		}
	}

	/**
	 * Determine all the users who should be notified via email
	 *
	 * @param Comment $comment
	 * @return Collection
	 */
	private function determineEmailRecipients($comment) {
		$userCollection = new Collection();

		// merge in teachers
		$comment->getCategory()->loadTeachers();
		$userCollection->merge($comment->getCategory()->getTeachers());

		/* If the comment is set to be Visible To Parents, notify
		 * all the specified people who should be emailed about the
		 * student. These recipients are not necessarily Parents of
		 * the student.
		 */
		if ($comment->isVisibleToParent()) {
			$comment->getStudent()->loadEmailRecipients();
			$userCollection->merge($comment->getStudent()->getEmailRecipients());
		}

		// if visible to student, find the student of the comment
		if($comment->isVisibleToStudent()) {
			$userCollection->add($comment->getStudent());
		}

		return $userCollection;
	}
}