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
	 * The user ID to retrieve events for, defaults to the logged in user.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Constructor
	 *
	 * @param $config
	 */
	public function __construct($config) {
		$this->setCalendarHandler(new Calendar());
		$this->setUserId($_SESSION['id']);
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
		$stack       = new Calendar_Event_Collection();
		$user_mapper = new Schoolbox\UserMapper();
		try {
			$user = $user_mapper->findById($this->getUserId());
		}
		catch (Schoolbox\Mapper\Exception $e) {
			return $stack;
		}

		//
		// Use the mapper classes to get events for this day.
		// Also, need the categories to filter on.
		// Don't like using session here but other event types use them, so meh.
		$cat_mapper = new Schoolbox\Resource\CategoryMapper();
		$cat_mapper->setExternalUser(new External_User());
		$category_id_list = array();
		foreach ($cat_mapper->findForUser($user) as $category) {
			$category_id_list[] = $category->getId();
		}

		$box_mapper    = new Schoolbox\Assessment\Component\SubmissionBoxMapper();
		$assess_mapper = new Schoolbox\Assessment\Component\OnlineAssessmentMapper();
		$activities    = $box_mapper->getDueActivities($category_id_list, $startTimestamp, $endTimestamp);
		$activities->merge($assess_mapper->getDueActivities($category_id_list, $startTimestamp, $endTimestamp));
		foreach ($activities as $activity) {
			$event = new Calendar_Event_Type_Submission();
			$event->setCategoryId($activity->getCategory()->getId());
			$event->setCategoryName($activity->getCategory()->getName());

			$category_codes = array();
			foreach ($activity->getCategory()->getCodes() as $code) {
				$category_codes[] = $code->getCode();
			}
			$event->setCategoryCodes($category_codes);

			$event->setColour($this->calendarHandler->displayColour(4));
			$event->setStartTime($activity->getDate());
			$event->setEndTime($activity->getDate());

			//
			// Will only be all day if the event's date is one second before
			// midnight.
			$event->setAllDay($activity->getDate()->getUsingFormat('H:i:s') === '23:59:59');

			//
			// Populate differently based on whether an assessment or submission
			if ($activity->isAssessment()) {
				$assessment = $activity->getAssessment();
				$owner      = $assessment->getUser();
				$event->setIsAssessment(true);
				$event->setSubmissionBoxId($assessment->getId());
				$event->setSubmissionBoxName($assessment->getTitle());
				$event->setSubmissionBoxDetail($assessment->getDescription());
				$event->setPublishDate($assessment->getStart());
			}
			else {
				$submission_box = $activity->getSubmissionBox();
				$owner          = $submission_box->getOwner();
				$event->setIsAssessment(false);
				$event->setSubmissionBoxId($submission_box->getId());
				$event->setSubmissionBoxName($submission_box->getName());
				$event->setSubmissionBoxDetail($submission_box->getDescription());
				$event->setPublishDate($submission_box->getOpenDate());
			}

			$event->setAuthorId($owner->getId());
			$event->setAuthorName($owner->getFirstName() . ' ' . $owner->getLastName());

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

	/**
	 * @param  int $user_id
	 *
	 * @return $this
	 */
	public function setUserId($user_id) {
		$this->user_id = $user_id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getUserId() {
		return $this->user_id;
	}

}
