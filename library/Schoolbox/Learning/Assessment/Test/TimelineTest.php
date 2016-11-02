<?php
/**
 * TimelineTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Test;

use \Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;
use Schoolbox\Learning\Assessment\DueWork\Timeline;
use \Schoolbox\User;

class TimelineTest extends \PHPUnit_Framework_TestCase
{

    /**
     * No Responses
     * No Feedbacks
     */
    public function testEmptyStacks()
    {
        $assessment = new DueWork();

        $this->assertCount(0, (new Timeline($assessment))->getTimeline());
    }

    /**
     * One Response
     * No Feedback
     */
    public function testOneResponse()
    {
        $assessment = new DueWork();

        $response1 = new Submission();
        $response1
            ->setId(1)
            ->setAssessable($assessment)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 10:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-04 10:45:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $assessment->setResponses((new Collection())->add($response1));

        /* Compare these
         */
        $expected = (new Collection())->add($response1);

        $timeline = (new Timeline($assessment))->getTimeline();

        $this->assertEquals($expected, $timeline);
    }

    /**
     * Multiple Responses
     * No Feedback
     */
    public function testMultipleResponses()
    {
        $assessment = new DueWork();

        $response1 = new Submission();
        $response1
            ->setId(1)
            ->setAssessable($assessment)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 10:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-01 10:45:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $response2 = new Submission();
        $response2
            ->setId(2)
            ->setAssessable($assessment)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 10:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $response3 = new Submission();
        $response3
            ->setId(3)
            ->setAssessable($assessment)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 10:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setSubmittedAt(new \Alaress_Date_Format('2016-10-31 04:34:34', \Alaress_Date_Format::DATABASE_FORMAT));

        $assessment->setResponses((new Collection())
            ->add($response1)
            ->add($response2)
            ->add($response3)
        );

        /* Compare these
         */
        $expected = (new Collection())
            ->add($response2)
            ->add($response1)
            ->add($response3);

        $timeline = (new Timeline($assessment))->getTimeline();

        $this->assertEquals($expected, $timeline);
    }

    /**
     * One Response
     * One Feedback
     */
    public function testOneResponseOneFeedback()
    {
        $assessment = new DueWork();

        $response1 = new Submission();
        $response1
            ->setId(1)
            ->setAssessable($assessment)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 10:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-01 10:45:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback1 = new Feedback();
        $feedback1
            ->setId(11)
            ->setAssessable($assessment)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT));

        $response1->getAllFeedback()->add($feedback1);

        $assessment->setResponses((new Collection())
            ->add($response1)
        );

        /* Compare these
         */
        $expected = (new Collection())
            ->add($response1)
        ;

        $timeline = (new Timeline($assessment))->getTimeline();

        $this->assertEquals($expected, $timeline);
    }
}