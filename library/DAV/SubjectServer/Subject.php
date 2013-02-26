<?php
/**
 * A DAV collection representing a single subject that a user has.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class DAV_SubjectServer_Subject extends Sabre_DAV_SimpleCollection
{
	/**
	 * @var \Schoolbox\External\Subject
	 */
	protected $subject;


	/**
	 * Overrides parent constructor to accept a subject instead of a name, and
	 * derives the name of this collection from that subject.
	 *
	 * @param Schoolbox\External\Subject $subject
	 * @param array                      $children
	 */
	public function __construct(\Schoolbox\External\Subject $subject, $children = array()) {
		$this->setSubject($subject);
		parent::__construct($subject->getName(), $children);
	}


	/**
	 * Retrieves the collection of assessments for this subject.
	 *
	 * @return array
	 */
	public function getChildren() {
		return array();
	}


	/**
	 * @param \Schoolbox\External\Subject $subject
	 */
	public function setSubject($subject) {
		$this->subject = $subject;
	}


	/**
	 * @return \Schoolbox\External\Subject
	 */
	public function getSubject() {
		return $this->subject;
	}
}
