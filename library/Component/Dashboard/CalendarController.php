<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_CalendarController extends Component_ControllerAbstract {

    protected $calendar;

    public function __construct (Component_Instance $instance, User $user, Template $template, Calendar $calendar) {
        parent::__construct($instance, $user, $template);

        $this->calendar = $calendar;
    }

    public function execute () {
        $currDay = time();

        $this->setVar("DATE", date("l jS F"));

        $this->submissionList = $this->calendar->getSubmissionEvents($currDay);

        $this->eventList = $this->calendar->getDayEvents($currDay);

        return true;
    }

    public function render ($templateHandle) {
        $this->setBlock($templateHandle, "note_block", "note_listing");

        if ( is_array($this->submissionList) ) {
            foreach ( $this->submissionList as $submission ) {

                $output = array(
                    'TIME' => $submission['dueTime'],
                    'NOTE' => 'WORK DUE ' . $submission['category'] . ( !empty( $submission['synergyCode'] )
                            ? ' (' . $submission['synergyCode'] . ')' : '' ) . ' - ' . $submission['name'],
                    'COLOUR' => $this->calendar->displayColour(4), 'MODIFY' => '', 'LOCATION' => '', 'DETAIL' => '',
                    'EVENT_TYPE' => 'submission', 'TYPE' => 'Due Work',
                    'AUTHOR' => $this->user->formatName($submission), 'AUTHOR_ID' => $submission['owner'],
                    'EVENT_LEVEL' => '',
                );

                $this->setVar($output);
                $this->parse("note_listing", "note_block", true);
                $subout = 1;
            }
        }

        if ( !is_array($this->eventList) ) {
            if ( !$subout ) {
                $this->setVar("note_listing", "");
            }
        }
        else {
            $userHandler = $this->user;

            foreach ( $this->eventList as $event ) {
                if ( $event['startTime'] == "0:00" || strtolower($event['startTime']) == "12:00am" || strtolower($event['startTime']) == "12:00 am" || $event['startTime'] == '' ) {
                    $this->setVar("TIME", "");
                }
                else {
                    if ( $event['endTime'] == "0:00" || strtolower($event['endTime']) == "12:00am" || strtolower($event['endTime']) == "12:00 am" || $event['endTime'] == '' ) {
                        $this->setVar("TIME", $event['startTime']);
                    }
                    else {
                        $this->setVar("TIME", $event['startTime'] . " - " . $event['endTime']);
                    }
                }

                $output = array(
                    'TIME' => rtrim(( $event['startTime'] != "12:00AM" ? $event['startTime']
                                                                    : "" ) . " - " . ( $event['endTime'] != "12:00AM"
                                  ? $event['endTime'] : "" ), "- "), 'NOTE' => scrubTEXT($event['note']),
                    'LOCATION' => scrubTEXT($event['location']), 'DETAIL' => scrubTEXT($event['detail']),
                    'COLOUR' => '#' . $event['eventColour'],
                    'MODIFY' => $this->calendar->getPerm($event['id']) > 1
                            ? "<a href=\"/calendar/modify.php?eventID=" . $event['id'] . "&returnTo=month\" title=\"Modify\">Modify</a>"
                            : '', 'EVENT_TYPE' => 'type' . $event['type'], 'TYPE' => $event['eventType'],
                    'AUTHOR' => $userHandler->formatName($event), 'AUTHOR_ID' => $event['owner'],
                );

                $this->setVar($output);
                $this->parse("note_listing", "note_block", true);

            }
        }

        return true;
    }

}