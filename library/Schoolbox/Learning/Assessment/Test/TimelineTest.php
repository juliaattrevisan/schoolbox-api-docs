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

//    /**
//     * No Responses
//     * No Feedbacks
//     */
//    public function testEmptyStacks()
//    {
//        $assessment = new DueWork();
//
//        $this->assertCount(0, (new Timeline($assessment))->getTimeline());
//    }
//
//    /**
//     * One Response
//     * No Feedback
//     */
//    public function testOneResponse()
//    {
//        $assessment = new DueWork();
//
//        $response1 = new Submission();
//        $response1
//            ->setId(1)
//            ->setAssessable($assessment)
//            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
//            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 10:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
//            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-04 10:45:00', \Alaress_Date_Format::DATABASE_FORMAT));
//
//        $assessment->setResponses((new Collection())->add($response1));
//
//        /* Compare these
//         */
//        $expected = (new Collection())->add($response1);
//
//        $timeline = (new Timeline($assessment))->getTimeline();
//
//        $this->assertEquals($expected, $timeline);
//    }
//
//
//    /**
//     * One Response
//     * One Feedback
//     */
//    public function testOneResponseOneFeedback()
//    {
//        $assessment = new DueWork();
//
//        $response1 = new Submission();
//        $response1
//            ->setId(1)
//            ->setAssessable($assessment)
//            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-01 10:45:00', \Alaress_Date_Format::DATABASE_FORMAT));
//
//        $feedback1 = new Feedback();
//        $feedback1
//            ->setId(11)
//            ->setAssessable($assessment)
//            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT));
//
//        $response1->getAllFeedback()->add($feedback1);
//
//        $assessment->setResponses((new Collection())
//            ->add($response1)
//        );
//
//        /* Compare these
//         */
//        $expected = (new Collection())
//            ->add($response1)
//        ;
//
//        $timeline = (new Timeline($assessment))->getTimeline();
//
//        $this->assertEquals($expected, $timeline);
//    }
//
//    /**
//     * One Response
//     * One Feedback
//     */
//    public function testOneResponseMultipleFeedback()
//    {
//        $assessment = new DueWork();
//
//        $response1 = new Submission();
//        $response1
//            ->setId(1)
//            ->setAssessable($assessment)
//            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-01 10:45:00', \Alaress_Date_Format::DATABASE_FORMAT));
//
//        $feedback1 = new Feedback();
//        $feedback1
//            ->setId(11)
//            ->setAssessable($assessment)
//            ->setPublishedAt(new \Alaress_Date_Format('2016-08-01 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT));
//
//        $feedback2 = new Feedback();
//        $feedback2
//            ->setId(22)
//            ->setAssessable($assessment)
//            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT));
//
//        $response1->setAllFeedback((new Collection())
//            ->add($feedback1)
//            ->add($feedback2)
//        );
//
//        $assessment->setResponses((new Collection())
//            ->add($response1)
//        );
//
//        /* Compare these
//         */
//        $expected = (new Collection())
//            ->add($response1)
//        ;
//
//        $timeline = (new Timeline($assessment))->getTimeline();
//
//        $this->assertEquals($expected, $timeline);
//    }
//
//    /**
//     * Multiple Responses
//     * One Feedback
//     */
//    public function testMultipleResponsesMultipleFeedback()
//    {
//        $assessment = new DueWork();
//
//        $response1 = new Submission();
//        $response1
//            ->setId(1)
//            ->setAssessable($assessment)
//            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-01 10:45:00', \Alaress_Date_Format::DATABASE_FORMAT));
//
//        $feedback1 = new Feedback();
//        $feedback1
//            ->setId(11)
//            ->setAssessable($assessment)
//            ->setPublishedAt(new \Alaress_Date_Format('2016-08-01 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT));
//
//        $response1->getAllFeedback()->add($feedback1);
//
//        $response2 = new Submission();
//        $response2
//            ->setId(2)
//            ->setAssessable($assessment)
//            ->setSubmittedAt(new \Alaress_Date_Format('2016-11-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));
//
//        $feedback2 = new Feedback();
//        $feedback2
//            ->setId(22)
//            ->setAssessable($assessment)
//            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT));
//
//        $response1->getAllFeedback()->add($feedback2);
//
//        $response3 = new Submission();
//        $response3
//            ->setId(3)
//            ->setAssessable($assessment)
//            ->setSubmittedAt(new \Alaress_Date_Format('2016-10-31 04:34:34', \Alaress_Date_Format::DATABASE_FORMAT));
//
//        $feedback3 = new Feedback();
//        $feedback3
//            ->setId(33)
//            ->setAssessable($assessment)
//            ->setPublishedAt(new \Alaress_Date_Format('2016-08-23 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT));
//
//        $response1->getAllFeedback()->add($feedback3);
//
//        $assessment->setResponses((new Collection())
//            ->add($response1)
//            ->add($response2)
//            ->add($response3)
//        );
//
//        /* Compare these
//         */
//        $expected = (new Collection())
//            ->add($response2)
//            ->add($response1)
//            ->add($response3);
//
//        $timeline = (new Timeline($assessment))->getTimeline();
//
//        $this->assertEquals($expected, $timeline);
//    }

    /**
     * Multiple Responses
     * One Feedback
     * Multiple Floaters
     */
    public function testMultipleResponsesMultipleFeedbackWithFloaters()
    {
        $assessment = new DueWork();

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

        $response1->getAllFeedback()->add($feedback2);

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

        $response1->getAllFeedback()->add($feedback3);

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

        /* Compare these
         *
         * $response2 2016-11-02 00:00:00
         * $response1 2016-11-01 10:45:00
         * $floating2 2016-11-01 01:01:01
         * $floating1 2016-11-01 00:00:01
         * $floating3 2016-11-01 00:00:00
         * $response3 2016-10-31 04:34:34
         */
        $expected = (new Collection())
            ->add($response2) // 2
            ->add($response1) // 1
            ->add($floating2) // 222
            ->add($floating1) // 111
            ->add($floating3) // 333
            ->add($response3); // 3

        $timeline = (new Timeline($assessment))->getTimeline();

        $this->assertEquals($expected, $timeline);
    }

    /**
     * Null dates should burn your soul with an exception
     *
     * @expectedException \InvalidArgumentException
     */
    public function testNulls()
    {
        $assessment = new DueWork();

        $response1 = new Submission();
        $response1
            ->setId(1)
            ->setAssessable($assessment)
            ->setSubmittedAt(null);

        $feedback1 = new Feedback();
        $feedback1
            ->setId(11)
            ->setAssessable($assessment)
            ->setPublishedAt(null);

        $response1->getAllFeedback()->add($feedback1);

        $response2 = new Submission();
        $response2
            ->setId(2)
            ->setAssessable($assessment)
            ->setSubmittedAt(null);

        $feedback2 = new Feedback();
        $feedback2
            ->setId(22)
            ->setAssessable($assessment)
            ->setPublishedAt(null);

        $response1->getAllFeedback()->add($feedback2);

        $response3 = new Submission();
        $response3
            ->setId(3)
            ->setAssessable($assessment)
            ->setSubmittedAt(null);

        $feedback3 = new Feedback();
        $feedback3
            ->setId(33)
            ->setAssessable($assessment)
            ->setPublishedAt(null);

        $response1->getAllFeedback()->add($feedback3);

        /* Floaters
         */
        $floating1 = new Feedback();
        $floating1
            ->setId(111)
            ->setAssessable($assessment)
            ->setPublishedAt(null);

        $floating2 = new Feedback();
        $floating2
            ->setId(222)
            ->setAssessable($assessment)
            ->setPublishedAt(null);

        $floating3 = new Feedback();
        $floating3
            ->setId(333)
            ->setAssessable($assessment)
            ->setPublishedAt(null);

        $assessment->setResponses((new Collection())
            ->add($response1)
            ->add($response2)
            ->add($response3)
        )->setFeedbacks((new Collection())
            ->add($floating1)
            ->add($floating2)
            ->add($floating3)
        );

        /* Compare these
         *
         * $response2 2016-11-02 00:00:00
         * $response1 2016-11-01 10:45:00
         * $floating2 2016-11-01 01:01:01
         * $floating1 2016-11-01 00:00:01
         * $floating3 2016-11-01 00:00:00
         * $response3 2016-10-31 04:34:34
         */
        $expected = (new Collection())
            ->add($response2) // 2
            ->add($response1) // 1
            ->add($floating2) // 222
            ->add($floating1) // 111
            ->add($floating3) // 333
            ->add($response3); // 3

        /* Throws exception due to null values
         */
        $timeline = (new Timeline($assessment))->getTimeline();
    }
}