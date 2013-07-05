<?php
/**
 * Calendar_Event_Type_Submission_Renderer_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Event_Type_Submission_Renderer_Default extends Calendar_Event_Type_Renderer_Abstract {

    /**
     * Render this event
     *
     * @param $currentEvent
     */
    public function visit($currentEvent) {

        /* Clear attendance blocks
         */
        $this->getPage()->setVar(
            array(
                'attendance_icon_block' => '',
                'attendance_status_block' => '',
                'attendance_request_button_block' => '',
                'attendance_list_button_block' => '',
                'has_category_block' => '',
            )
        );

        /* Get status to set action
         */
        if ($this->getGroupHandler()->isStudent($_SESSION['id'])) {
            $action = 'View Details';
            $this->getPage()->setVar(
                'MODIFY',
                '<a class="btn go-to float-left" href="/submission/submissionHistory/' . $currentEvent->getSubmissionBoxId(
                ) . '"><span></span>' . $action . '</a>'
            );
        } else {
            if ($this->getGroupHandler()->isStaff($_SESSION['id'])) {
                $this->getPage()->setVar(
                    'MODIFY',
                    '<a class="btn go-to float-left" href="/submission/viewSubmission/' . $currentEvent->getSubmissionBoxId(
                    ) . '"><span></span>View Submissions</a>'
                );
            } else {
                $this->getPage()->setVar('MODIFY', '');
            }
        }

        $this->getPage()->setVar(
            array(
                'EVENT_ID' => '',
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
        $this->getPage()->parse('has_category_block', 'has_category');
        $this->getPage()->parse('note_block', 'note', true);
    }
}
