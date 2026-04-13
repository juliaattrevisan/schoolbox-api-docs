<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Reporting\Feedback;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Reporting;
use Schoolbox\Learning\Assessment\Reporting\Model;
use Schoolbox\Mapper\Exception as MapperException;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct() {
    }


    /**
     * A redirect to the first participant who had a feedback
     *
     * @param Reporting\Model $assessment
     */
    public function getFirstParticipantFeedback(Reporting\Model $assessment): void
    {

        // TODO for $reporting, find the first participant with a feedback id

        // TODO redirect to getParticularFeedback() for that person.
    }


    /**
     * A redirect to the first participant who had a feedback
     *
     * @param Model $assessment
     * @throws Error
     */
    public function getParticularFeedback(Reporting\Model $assessment): void
    {
        $this->response = $this->responseFromTemplate('@learningReportingFeedback/index.twig', [
            'reporting' => $assessment
        ], 'Example');

    }


    /**
     * Process the form vars
     */
    public function postExample(): void
    {
        try {
            $user = $this->userMapper->findById($this->requestPost['id']);
        } catch (MapperException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        $this->response = $this->responseFromTemplate(
            '@learningReportingFeedback/index.twig',
            [
                'user' => $user,
                'var1' => $this->requestPost['var1'],
            ],
            'Example Form Output',
        );
    }
}
