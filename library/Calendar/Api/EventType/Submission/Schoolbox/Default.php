<?php
/**
 * Calendar_Api_EventType_Submission_Schoolbox_Default
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_EventType_Submission_Schoolbox_Default extends Calendar_Api_EventType_Abstract {
	private $calendarHandler = null;

	/**
	 * Constructor
	 *
	 * @param $config
	 */
	public function __construct($config) {
		$this->setCalendarHandler(new Calendar());
	}

	/**
	 * Get day events from this data source
	 *
	 * @param $startTimestamp
	 * @param $endTimestamp
	 * @internal param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getEvents($startTimestamp, $endTimestamp) {
		//
		// Use the mapper classes to get events for this day.
		// Also, need the categories to filter on.
		// Don't like using session here but other event types use them, so meh.
		$cat_mapper = new Schoolbox\Resource\CategoryMapper();
		$cat_mapper->setExternalUser(new External_User());
		$user_mapper = new Schoolbox\UserMapper();
		$category_id_list = array();
		foreach ($cat_mapper->findForUser($user_mapper->findById($_SESSION['id'])) as $category) {
			$category_id_list[] = $category->getId();
		}

		$stack      = new Calendar_Event_Collection();
		$box_mapper = new Schoolbox\Assessment\Component\SubmissionBoxMapper();
		foreach ($box_mapper->getDueActivities($category_id_list, $startTimestamp, $endTimestamp) as $activity) {
			$event = new Calendar_Event_Type_Submission();
			$submission_box = $activity->getSubmissionBox();
			$event->setCategoryId($activity->getCategory()->getId());
			$event->setCategoryName($activity->getCategory()->getName());
//			$event->setCategoryCodes($submission['synergyCode']);

			$owner = $submission_box->getOwner();
			$event->setAuthorId($owner->getId());
			$event->setAuthorName($owner->getFirstName() . ' ' . $owner->getLastName());
			$event->setSubmissionBoxId($submission_box->getId());
			$event->setSubmissionBoxName($submission_box->getName());
			$event->setSubmissionBoxDetail($submission_box->getDescription());
			$event->setColour($this->calendarHandler->displayColour(4));
			$event->setStartTime($activity->getDate());
			$stack->add($event);
		}

		$assess_mapper = new Schoolbox\Assessment\Component\OnlineAssessmentMapper();
		foreach ($assess_mapper->getDueActivities($category_id_list, $startTimestamp, $endTimestamp) as $activity) {
			$event = new Calendar_Event_Type_Submission();
			$assessment = $activity->getAssessment();
			$event->setCategoryId($activity->getCategory()->getId());
			$event->setCategoryName($activity->getCategory()->getName());
//			$event->setCategoryCodes($submission['synergyCode']);

			$owner = $assessment->getUser();
			$event->setAuthorId($owner->getId());
			$event->setAuthorName($owner->getFirstName() . ' ' . $owner->getLastName());
			$event->setSubmissionBoxId($assessment->getId());
			$event->setSubmissionBoxName($assessment->getTitle());
			$event->setSubmissionBoxDetail($assessment->getDescription());
			$event->setColour($this->calendarHandler->displayColour(4));
			$event->setStartTime($activity->getDate());
			$stack->add($event);
		}
		return $stack;
	}

	/**
	 * Find a particular event from this source
	 *
	 * @param $eventId
	 * @return Calendar_Event_Collection
	 */
	public function getEvent($eventId) {
		$collection = new Calendar_Event_Collection();

		/* Fill in your query result set here and populate $collection
		 */

		return $collection;
	}

	/* Getters/Setters
	 */

	/**
	 * Set calendar handler
	 *
	 * @param $calendarHandler
	 */
	public function setCalendarHandler($calendarHandler) {
		$this->calendarHandler = $calendarHandler;
	}

	/**
	 * Get calendar handler
	 *
	 * @return null
	 */
	public function getCalendarHandler() {
		return $this->calendarHandler;
	}
}
