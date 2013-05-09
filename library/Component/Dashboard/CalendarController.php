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

		$start = new Alaress_Date_Format($now->getDatabaseDate() . ' 00:00:00', 'y-m-d h:i:s');

		if($this->getConfig('days') > 1) {
			$end = new Alaress_Date_Format($now->getDatabaseDate() . ' 00:00:00', 'y-m-d h:i:s');
			$end->addDays($this->getConfig('days'));
		}
		else {
			$end = new Alaress_Date_Format($now->getDatabaseDate() . ' 00:00:00', 'y-m-d h:i:s');
			$end->addDays(1);
		}

		/* Today's date
		 */
		$this->setVar(array(
			'DATE' => $now->getUsingFormat('l jS F')
		));

		/* A list of submissions
		 */
		$eventFactory = new Calendar_Api_Factory();

		$composite = $eventFactory->produce();

		$this->eventList = $composite->getEvents($start->getUnixTimestamp(), $end->getUnixTimestamp());

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

	    foreach ($this->eventList as $currentEvent) {

		    $currentDay = $currentEvent->getStartTime();
		    if($prevDay != false && $currentDay->getDateOnly() != $prevDay->getDateOnly()){
			    $this->parse('day_block','day', true);
			    $this->setVar('note_listing', '');
		    }

		    $this->setVar('LIST_DATE', $currentDay->getDateOnly());



			if($currentEvent instanceof Calendar_Event_Type_Submission) {



					if ($this->getGroup()->isStudent($_SESSION['id'])) {
					$action = 'View Details';
					$this->setVar(
					'MODIFY',
					'<a class="btn go-to float-left" href="/submission/submissionHistory/' . $currentEvent->getSubmissionBoxId(
					) . '"><span></span>' . $action . '</a>'
					);
					} else {
						if ($this->getGroup()->isStaff($_SESSION['id'])) {
							$this->setVar(
								'MODIFY',
								'<a class="btn go-to float-left" href="/submission/viewSubmission/' . $currentEvent->getSubmissionBoxId(
								) . '"><span></span>View Submissions</a>'
							);
						} else {
							$this->setVar('MODIFY', '');
						}
					}

					$this->setVar(
						array(
							'EVENT_ID' => '',
							'TIME' => $currentEvent->getTime(),
							'FULL_TIME' => $currentEvent->getFullDate(),
							'NOTE' => $currentEvent->getNote(),
							'COLOUR' => $currentEvent->getColour(),
							'LOCATION' => '',
							'HAS_CATEGORY_ID' => $currentEvent->getCategoryId(),
							'HAS_CATEGORY_NAME' => Scrub::text($currentEvent->getCategoryName()),
							'DETAIL' => Scrub::text($currentEvent->getSubmissionBoxDetail()),
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
	                    'EVENT_ID' => $currentEvent->getEventId(),
	                    'TIME' => $currentEvent->getTime(),
	                    'FULL_TIME' => $currentEvent->getFullDate(),
	                    'NOTE' => Scrub::text($currentEvent->getNote()),
	                    'LOCATION' => Scrub::text($currentEvent->getLocation()),
	                    'DETAIL' => Scrub::text($currentEvent->getDetail()),
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
	                    )
	                    ) {

	                        /* Attendance Status
	                         */
	                        if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_PENDING) {
	                            $this->setVar('ATTENDANCE_STATUS', 'Pending');
	                        } else {
	                            if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_APPROVED) {
	                                $this->setVar('ATTENDANCE_STATUS', 'Approved');
	                            } else {
	                                if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_REJECTED) {
	                                    $this->setVar('ATTENDANCE_STATUS', 'Rejected');
	                                }
	                            }
	                        }

	                        $this->parse('attendance_status_block', 'attendance_status');

	                        $this->setVar('ATTENDANCE_REQUEST_ACTION', 'Unattend');
	                    } else {
	                        $this->setVar('ATTENDANCE_REQUEST_ACTION', 'Attend');
	                    }

	                    $this->parse('attendance_request_button_block', 'attendance_request_button');
	                } else {
	                    if ($this->getGroup()->isStaff($_SESSION['id'])) {
	                        $this->parse('attendance_list_button_block', 'attendance_list_button');
	                    }
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
