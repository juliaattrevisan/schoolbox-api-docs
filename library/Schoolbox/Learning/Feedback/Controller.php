<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Feedback;

use Alaress\Web\Controller\Base;
use Schoolbox\Learning\Assessment\Participant;

class Controller extends Base
{

    /**
     * Post into the feedback collection for a participant. This will create "floating feedback"
     *
     * @api   POST /learning/assessments/{assessmentId}/{studentId}/feedback
     * @level 165
     *
     * @param Participant $participant
     */
    public function postParticipantFeedback(Participant $participant)
    {
        // obtain a reference to the most recent floating feedback

        // if none, create new draft

        // elseif published, create new draft

        // endif

        // mutate model found, and save


        // if CREATE, return 201 Created
        // if UPDATE, return 204 No Content
        $this->getResponse()->status->set(201);


        $template = '';
        $data = [];

        $this->getResponse()->content->set($this->buildContent($template, $data));
    }


    /**
     * @param string $template
     * @param array  $data
     * @return \Alaress\Web\Output\Base
     */
    protected function buildContent($template, array $data)
    {
        if (strpos($this->getRequest()->headers->get('accept'), 'application/json') !== false) {
            // prefer JSON if specified... this isn't exactly correct, but it's close enough for now
            return $this->buildJsonOutput($data);

        } elseif ($this->getRequest()->isXhr()) {
            // if the request is AJAX, don't return the chrome
            return $this->buildUnthemedHtmlOutput($template, $data);

        } else {
            // full synchronous page
            return $this->buildHtmlOutput($template, $data);
        }
    }

}
