<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_CalendarController extends Component_Dashboard_ControllerAbstract {

	protected $calendar;

	protected $group;

	/**
	 * @var Alaress_Date_Format
	 */
	protected $start;

	/**
	 * @var Alaress_Date_Format
	 */
	protected $end;

	/**
	 * @var Calendar_Event_Collection
	 */
	protected $eventList;

	public function __construct(Component_Instance $instance, User $user, SitePage $sitepage, Calendar $calendar, Group $group) {
		parent::__construct($instance, $user, $sitepage);

		$this->setCalendar($calendar);

		$this->setGroup($group);
	}

	/**
	 * Get all the events you need to pump into the page
	 *
	 * @return bool|void
	 */
	public function execute() {
		$now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');
		$this->start = new Alaress_Date_Format($now->getDatabaseDate() . ' 00:00:00', 'y-m-d h:i:s');
		$this->end   = new Alaress_Date_Format($now->getDatabaseDate() . ' 00:00:00', 'y-m-d h:i:s');
		$days  = $this->getConfig('days');
		if ($days <= 1) {
			$days = 1;
		}
		$this->end->addDays($days);

		/* A list of submissions
		 */
		$eventFactory = new Calendar_Api_Factory($this->user);

		$composite = $eventFactory->produce();

		$this->eventList = $composite->getEvents($this->start->getUnixTimestamp(), $this->end->getUnixTimestamp());

		/* Today's date (default set so no {VARIABLES} show when there's no events)
		 */
		$this->setVar(array(
			'DATE' => $now->getUsingFormat('l jS F')
		));

		return true;
	}

	public function renderTemplate($templateHandle) {
		$this->setVar("note_listing", "");

		if (!$this->loadTemplate($templateHandle, 'calendar')) {
			return false;
		}

		$this->setBlock($templateHandle, 'day', 'day_block');
		$this->setBlock('day', "note_block", "note_listing");

		$this->setBlock("note_block", "has_category", "has_category_block");
		$this->setBlock("note_block", "attendance_icon", "attendance_icon_block");
		$this->setBlock("note_block", "attendance_status", "attendance_status_block");
		$this->setBlock("note_block", "attendance_request_button", "attendance_request_button_block");
		$this->setBlock("note_block", "attendance_list_button", "attendance_list_button_block");


		/* Initialise
		 */
		$this->setVar(
			array(
				'attendance_icon_block' => '',
				'attendance_status_block' => '',
				'attendance_request_button_block' => '',
				'attendance_list_button_block' => '',
				'has_category_block' => '',
			)
		);

		$prevDay = false;

		$j = 0;
		foreach ($this->eventList as $currentEvent) {
			$this->setVar('has_category_block', '');

			//
			// Current day: will never be before the start time
			$currentDay = $currentEvent->getStartTime();
			if ($this->start->compare($currentDay) > 0) {
				$currentDay = $this->start;
			}
			if($prevDay != false && $currentDay->getDateOnly() != $prevDay->getDateOnly()){
				$this->parse('day_block','day', true);
				$this->setVar('note_listing', '');
			}

			$this->setVar('LIST_DATE', $currentDay->getDateOnly());



			if ($currentEvent instanceof Calendar_Event_Type_Submission) {

				if ($this->getGroup()->isStudent($_SESSION['id'])) {
					$action = 'View Details';
					$this->setVar(
						'MODIFY',
						'<a class="btn go-to float-left" href="/submission/submissionHistory/' .
						$currentEvent->getSubmissionBoxId() . '/' . $_SESSION['id'] .
						'"><span></span>' . $action . '</a>'
					);
				}
				elseif ($this->getGroup()->isStaff($_SESSION['id'])) {
					$this->setVar(
						'MODIFY',
						'<a class="btn go-to float-left" href="/submission/viewSubmission/' .
						$currentEvent->getSubmissionBoxId() .
						'"><span></span>View Submissions</a>'
					);
				}
				else {
					$this->setVar('MODIFY', '');
				}

				$this->setVar(
					array(
						'EVENT_ID' => '',
						'DATE' => $currentDay->getUsingFormat('l jS F'),
						'TIME' => $currentEvent->getTime(),
						'FULL_TIME' => $currentEvent->getFullDate(),
						'NOTE' => $currentEvent->getNote(),
						'COLOUR' => $currentEvent->getColour(),
						'LOCATION' => '',
						'HAS_CATEGORY_ID' => $currentEvent->getCategoryId(),
						'HAS_CATEGORY_NAME' => Scrub::text($currentEvent->getCategoryName()),
						'DETAIL' => Scrub::html($currentEvent->getSubmissionBoxDetail()),
						/* Wtf - EVENT_TYPE => 'type' . $event['type']; and TYPE => $event['eventType']
						 */
						'EVENT_TYPE' => 'submission',
						'TYPE' => $currentEvent->getEventType(),
						'AUTHOR' => Scrub::text($currentEvent->getAuthorName()),
						'AUTHOR_ID' => $currentEvent->getAuthorId(),
						'EVENT_VISIBLE' => '',
						'EVENT_LEVEL' => '',
					)
				);
				$this->parse('has_category_block', 'has_category');
			}
			else {
				$this->setVar(
					array(
						'DATE' => $currentDay->getUsingFormat('l jS F'),
						'EVENT_ID' => $currentEvent->getEventId(),
						'TIME' => $currentEvent->getTime(),
						'FULL_TIME' => $currentEvent->getFullDate(),
						'NOTE' => Scrub::text($currentEvent->getNote()),
						'LOCATION' => Scrub::text($currentEvent->getLocation()),
						'DETAIL' => Scrub::html($currentEvent->getDetail()),
						'COLOUR' => $currentEvent->getColour(),
						'EVENT_TYPE' => 'type' . $currentEvent->getType(),
						'TYPE' => $currentEvent->getEventType(),
						'AUTHOR' => Scrub::text($currentEvent->getAuthorName()),
						'AUTHOR_ID' => $currentEvent->getAuthorId(),
						'ON_OFF' => ++$j % 2 ? 'dark' : '',
					)
				);

				/* Modify link. Should be templated...
					*/
				if ($currentEvent->getIsEditable()) {
					$this->setVar(
						'MODIFY',
						'<a class="btn modify float-left" href="/calendar/modify.php?eventID=' . $currentEvent->getEventId(
						) . '&returnTo=month"><span></span>Modify</a>'
					);
				} else {
					$this->setVar('MODIFY', '');
				}

				/* Event attendance
				 */
				if ($currentEvent->canAttend()) {
					$this->parse('attendance_icon_block', 'attendance_icon');

					if ($this->getGroup()->isStudent($_SESSION['id'])) {
						if ($attendance = $this->getCalendar()->getAttendanceByEventAndUser(
							$currentEvent->getEventId(), $_SESSION['id']
						)) {

							/* Attendance Status
							 */
							if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_PENDING) {
								$this->setVar('ATTENDANCE_STATUS', 'Pending');
							}
							elseif ($attendance['status'] == Calendar::ATTENDANCE_STATUS_APPROVED) {
								$this->setVar('ATTENDANCE_STATUS', 'Approved');
							}
							elseif ($attendance['status'] == Calendar::ATTENDANCE_STATUS_REJECTED) {
								$this->setVar('ATTENDANCE_STATUS', 'Rejected');
							}
							else {
								$this->setVar('ATTENDANCE_STATUS', '');
							}

							$this->parse('attendance_status_block', 'attendance_status');

							$this->setVar('ATTENDANCE_REQUEST_ACTION', 'Unattend');
						}
						else {
							$this->setVar('ATTENDANCE_REQUEST_ACTION', 'Attend');
						}

						$this->parse('attendance_request_button_block', 'attendance_request_button');
					}
					elseif ($this->getGroup()->isStaff($_SESSION['id'])) {
						$this->parse('attendance_list_button_block', 'attendance_list_button');
					}
				}

				/* Show a homepage if appropriate
				 */
				if ($currentEvent->getCategoryId() > 0) {
					$this->setVar(
						array(
							'HAS_CATEGORY_ID' => $currentEvent->getCategoryId(),
							'HAS_CATEGORY_NAME' => $currentEvent->getCategoryName(),
						)
					);

					$this->parse('has_category_block', 'has_category');
				}

			}


			$this->parse("note_listing", "note_block", true);

			$prevDay = $currentDay;
		}


		$this->parse('day_block','day',true);

		return true;
	}

	public function setCalendar($calendar) {
		$this->calendar = $calendar;
	}

	public function getCalendar() {
		return $this->calendar;
	}

	public function setGroup($group) {
		$this->group = $group;
	}

	public function getGroup() {
		return $this->group;
	}

}
