<?php

use Schoolbox\Config\ConfigMapper;

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class Component_Dashboard_CanvasAssignmentsController extends Component_Dashboard_ControllerAbstract
{
    /**
     * A new component will be added to the dashboard called. “Canvas Assignments”.
     * The component will query the REST API for ‘assignments’ and get events between the start of today and 30 days into the future. Note: that you can provide the external_id to the API by prepending it with “sis_user_id”
     * For each assignment the following information will be displayed
     * * Context Code
     * * Title
     * * End date
     * The title should link to the html_url provided in the response
     *
     * Calendar events for a user: https://canvas.instructure.com/doc/api/calendar_events.html#method.calendar_events_api.user_index filtered by type of assignment.
     */

    /**
     */
    public function execute()
    {
        return true;
    }

    /**
     */
    public function renderTemplate($templateHandle)
    {
        /**
         * display template and get the template to do an async request to get the assignments
         */
        return $this->renderTwigTemplate(
            $templateHandle,
            'component/dashboard/canvasAssignments.twig',
            [
                'componentInstanceId' => $this->getInstance()->getId(),
            ],
            true,
            true,
            false,
            true
        );
    }
}
