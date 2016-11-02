<?php
/**
 * TemporaryDueWorkDecorator
 *
 * A temporary decorator for the submission history screen
 * because this is so huge and convoluted
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use \Schoolbox\Collection;
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;

class TemporaryDueWorkDecorator
{

    /**
     * TemporaryDueWorkDecorator constructor.
     * @param DueWork $assessment
     */
    public function __construct(DueWork $assessment)
    {
        $response1 = new Submission();
        $response1
            ->setId(1)
            ->setAssessable($assessment)
            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-01 10:45:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback1 = new Feedback();
        $feedback1
            ->setId(11)
            ->setAssessable($assessment)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-01 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT));

        $response1->getAllFeedback()->add($feedback1);

        $response2 = new Submission();
        $response2
            ->setId(2)
            ->setAssessable($assessment)
            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback2 = new Feedback();
        $feedback2
            ->setId(22)
            ->setAssessable($assessment)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT));

        $response2->getAllFeedback()->add($feedback2);

        $response3 = new Submission();
        $response3
            ->setId(3)
            ->setAssessable($assessment)
            ->setSubmittedAt(new \Alaress_Date_Format('2016-10-31 04:34:34', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback3 = new Feedback();
        $feedback3
            ->setId(33)
            ->setAssessable($assessment)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-23 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT));

        $response3->getAllFeedback()->add($feedback3);

        /* Floaters
         */
        $floating1 = new Feedback();
        $floating1
            ->setId(111)
            ->setAssessable($assessment)
            ->setPublishedAt(new \Alaress_Date_Format('2016-11-01 00:00:01', \Alaress_Date_Format::DATABASE_FORMAT));

        $floating2 = new Feedback();
        $floating2
            ->setId(222)
            ->setAssessable($assessment)
            ->setPublishedAt(new \Alaress_Date_Format('2016-11-01 01:01:01', \Alaress_Date_Format::DATABASE_FORMAT));

        $floating3 = new Feedback();
        $floating3
            ->setId(333)
            ->setAssessable($assessment)
            ->setPublishedAt(new \Alaress_Date_Format('2016-11-01 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $assessment->setResponses((new Collection())
            ->add($response1)
            ->add($response2)
            ->add($response3)
        )->setFeedbacks((new Collection())
            ->add($floating1)
            ->add($floating2)
            ->add($floating3)
        );
    }
}