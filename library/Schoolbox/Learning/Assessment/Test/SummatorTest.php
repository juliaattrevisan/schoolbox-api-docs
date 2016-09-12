<?php
/**
 * Summator
 *
 * This tests the Summator strategies such that their
 * accumulation methods work correctly
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use \Schoolbox\Collection;
use \Schoolbox\User;
use \Schoolbox\Resource\Storage\Model as Storage;

use \Schoolbox\Learning\Assessment\Participant;
use \Schoolbox\Learning\Assessment\Project as Project;

use \Schoolbox\Learning\Assessment\Summator\ISummator;
use \Schoolbox\Learning\Assessment\Summator\Average as SummatorAverage;
use \Schoolbox\Learning\Assessment\Summator\WeightedAverage as SummatorWeightedAverage;
use \Schoolbox\Learning\Assessment\Summator\Maximum as SummatorMaximum;
use \Schoolbox\Learning\Assessment\Summator\Minimum as SummatorMinimum;
use \Schoolbox\Learning\Assessment\Summator\Sum as SummatorSum;

use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as DueWorkSubmissionFile;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan as DueWorkSubmissionFilePlagscan;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\File\Model as DueWorkFeedbackFile;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use \Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use \Schoolbox\Learning\Assessment\DueWork\Type as DueWorkType;

use \Schoolbox\Learning\Assessment\Task\Model as Task;
use \Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;
use \Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;

use \Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use \Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use \Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizReview;

class Summator extends \Schoolbox\Learning\Assessment\Test\Defaults
{

    /**
     * A sample project containing IAssessables
     *
     * @var Project
     */
    protected $project;

    /**
     * For floating point comparisons
     *
     * See assertEquals() in https://phpunit.de/manual/current/en/appendixes.assertions.html
     *
     * @var float
     */
    protected $delta = 0.00000000005;

    /**
     * Test the unweighted average
     * when there is nothing in here at all
     */
    public function testUnweightedAverage0()
    {
        $project = $this->spawnProject(new SummatorAverage());
        $project->setAssessables(new Collection());

        /* Published Average is null
         */
        $this->assertEquals(null, $project->getNormalisedMark($this->hodes, true));

        /* Unpublished Average is null
         */
        $this->assertEquals(null, $project->getNormalisedMark($this->hodes, false));
    }

    /**
     * Test the unweighted average
     * when there is no feedback
     */
    public function testUnweightedAverage1()
    {
        $project = $this->spawnProject(new SummatorAverage());
        $project->setAssessables((new Collection())
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /** @var \Schoolbox\Learning\Assessment\IFeedback $currentFeedback */
        /** @var \Schoolbox\Learning\Assessment\IResponse $currentResponse */
        foreach ($project->getAssessables() as $currentAssessable) {
            $currentAssessable->getParticipant($this->hodes)->setAllFeedbacks(new Collection());

            foreach ($currentAssessable->getParticipant($this->hodes)->getAllResponses() as $currentResponse) {
                $currentResponse->setAllFeedback(new Collection());
            }
        }

        /* Published Average is null (nothing to calculate)
         */
        $this->assertEquals(null, $project->getNormalisedMark($this->hodes, true));

        /* Unpublished Average is null (nothing to calculate)
         */
        $this->assertEquals(null, $project->getNormalisedMark($this->hodes, false));
    }

    /**
     * Test the unweighted average
     * when there is nothing published
     */
    public function testUnweightedAverage2()
    {
        $project = $this->spawnProject(new SummatorAverage());
        $project->setAssessables((new Collection())
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /** @var \Schoolbox\Learning\Assessment\IFeedback $currentFeedback */
        /** @var \Schoolbox\Learning\Assessment\IResponse $currentResponse */
        foreach ($project->getAssessables() as $currentAssessable) {
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllFeedbacks() as $currentFeedback) {
                $currentFeedback->setPublishedAt(null);
            }

            foreach ($currentAssessable->getParticipant($this->hodes)->getAllResponses() as $currentResponse) {
                foreach ($currentResponse->getAllFeedback() as $currentFeedback) {
                    $currentFeedback->setPublishedAt(null);
                }
            }
        }

        /* Published Average is null (nothing to calculate)
         */
        $this->assertEquals(null, $project->getNormalisedMark($this->hodes, true));

        /* Unpublished Average is
         *      = [98 + 77 + 100 + (100 * 5/20)] / 4
         *      = [98 + 77 + 100 + 25] / 4
         *      = 300/4
         *      = 75
         */
        $this->assertEquals(75, $project->getNormalisedMark($this->hodes, false));
    }

    /**
     * Test the unweighted average
     * when there's one of each type with published and unpublished feedback
     */
    public function testUnweightedAverage3()
    {
        /* There are three assessables for Hodes
         *
         * Due Work (100 marks)
         * Task (null marks)
         * Quiz (12 marks)
         */
        $project = $this->spawnProject(new SummatorAverage());
        $project->setAssessables((new Collection())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /* Published Average is
         *      = [100 + (100 * 12/20)] / 2
         *      = [100 + 60] / 2
         *      = 80
         */
        $this->assertEquals(80, $project->getNormalisedMark($this->hodes, true));

        /* Unpublished Average is
         *      = [100 + (100 * 5/20)] / 2
         *      = [100 + 25] / 2
         *      = 62.5
         */
        $this->assertEquals(62.5, $project->getNormalisedMark($this->hodes, false));
    }

    /**
     * Test the unweighted average
     * when there's more than one of a particular type with published and unpublished feedback
     */
    public function testUnweightedAverage4()
    {
        /* There are five assessables for Hodes
         *
         * Due Work 3 (98 marks)
         * Task (null marks)
         * Due Work 2 (77 marks)
         * Due Work (100 marks)
         * Quiz (12 marks)
         */
        $project = $this->spawnProject(new SummatorAverage());
        $project->setAssessables((new Collection())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /* Published Average is
         *      = [77 + 100 + (100 * 12/20)] / 3
         *      = [177 + 60] / 4
         *      = 79
         */
        $this->assertEquals(79, $project->getNormalisedMark($this->hodes, true));

        /* Unpublished Average is
         *      = [77 + 100 + (100 * 5/20)] / 3
         *      = [177 + 25] / 3
         *      = 67.33333333333
         */
        $this->assertEquals(67.33333333333, $project->getNormalisedMark($this->hodes, false));
    }

    /**
     * Test the unweighted average
     * when there's more than one of a particular type with published and unpublished feedback
     */
    public function testUnweightedAverage5()
    {
        /* There are five assessables for Hodes
         *
         * Due Work 3 (98 marks)
         * Task (null marks)
         * Due Work 2 (77 marks)
         * Due Work (100 marks)
         * Quiz (12 marks)
         */
        $project = $this->spawnProject(new SummatorAverage());
        $project->setAssessables((new Collection())
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /* Published Average is
         *      = [98 + 77 + 100 + (100 * 12/20)] / 4
         *      = [275 + 60] / 4
         *      = 83.75
         */
        $this->assertEquals(83.75, $project->getNormalisedMark($this->hodes, true));

        /* Unpublished Average is
         *      = [98 + 77 + 100 + (100 * 5/20)] / 3
         *      = [275 + 25] / 4
         *      = 75
         */
        $this->assertEquals(75, $project->getNormalisedMark($this->hodes, false));
    }

    /**
     * Test the weighted average
     * when there is no feedback
     */
    public function testWeightedAverage1()
    {
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables((new Collection())
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /** @var \Schoolbox\Learning\Assessment\IFeedback $currentFeedback */
        /** @var \Schoolbox\Learning\Assessment\IResponse $currentResponse */
        foreach ($project->getAssessables() as $currentAssessable) {
            $currentAssessable->getParticipant($this->hodes)->setAllFeedbacks(new Collection());

            foreach ($currentAssessable->getParticipant($this->hodes)->getAllResponses() as $currentResponse) {
                $currentResponse->setAllFeedback(new Collection());
            }
        }

        /* Published Weighted Average is null (nothing to calculate)
         */
        $this->assertEquals(null, $project->getNormalisedMark($this->hodes, true));

        /* Unpublished Weighted Average is null (nothing to calculate)
         */
        $this->assertEquals(null, $project->getNormalisedMark($this->hodes, false));
    }

    /**
     * Test the weighted average
     * when there is nothing published
     */
    public function testWeightedAverage2()
    {
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables((new Collection())
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /** @var \Schoolbox\Learning\Assessment\IFeedback $currentFeedback */
        /** @var \Schoolbox\Learning\Assessment\IResponse $currentResponse */
        foreach ($project->getAssessables() as $currentAssessable) {
            foreach ($currentAssessable->getParticipant($this->hodes)->getAllFeedbacks() as $currentFeedback) {
                $currentFeedback->setPublishedAt(null);
            }

            foreach ($currentAssessable->getParticipant($this->hodes)->getAllResponses() as $currentResponse) {
                foreach ($currentResponse->getAllFeedback() as $currentFeedback) {
                    $currentFeedback->setPublishedAt(null);
                }
            }
        }

        /* Published Weighted Average is null (nothing to calculate)
         */
        $this->assertEquals(null, $project->getNormalisedMark($this->hodes, true));

        /* Unpublished Weighted Average is
         *      = [(98*6) + (77*10) + (100*5) + ((100 * 5/20)*8)] / 29
         *      = [588 + 770 + 500 + 200] / 29
         *      = 2058 / 29
         *      = 70.96551724138
         */
        $this->assertEquals(70.96551724138, $project->getNormalisedMark($this->hodes, false), '', $this->delta);
    }

    /**
     * Test the weighted average
     * when there's one of each type with published and unpublished feedback
     */
    public function testWeightedAverage3()
    {
        /* There are three assessables for Hodes
         *
         * Due Work (100 marks)
         * Task (null marks)
         * Quiz (12 marks)
         */
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables((new Collection())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /* Published Weighted Average is
         *      = [(100*5) + ((100 * 12/20)*8)] / 13
         *      = [500 + 480] / 13
         *      = 980 / 13
         *      = 75.38461538462
         */
        $this->assertEquals(75.38461538462, $project->getNormalisedMark($this->hodes, true), '', $this->delta);

        /* Unpublished Weighted Average is
         *      = [(100*5) + ((100 * 5/20)*8)] / 13
         *      = [500 + 200] / 13
         *      = 700 / 13
         *      = 53.84615384615
         */
        $this->assertEquals(53.84615384615, $project->getNormalisedMark($this->hodes, false), '', $this->delta);
    }

    /**
     * Test the weighted average
     * when there's more than one of a particular type with published and unpublished feedback
     */
    public function testWeightedAverage4()
    {
        /* There are five assessables for Hodes
         *
         * Due Work 3 (98 marks)
         * Task (null marks)
         * Due Work 2 (77 marks)
         * Due Work (100 marks)
         * Quiz (12 marks)
         */
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables((new Collection())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /* Published Weighted Average is
         *      = [(77*10) + (100*5) + ((100 * 12/20)*8)] / 23
         *      = [770 + 500 + 480] / 23
         *      = 1750 / 23
         *      = 76.08695652174
         */
        $this->assertEquals(76.08695652174, $project->getNormalisedMark($this->hodes, true));

        /* Unpublished Weighted Average is
         *      = [(77*10) + (100*5) + ((100 * 5/20)*8)] / 23
         *      = [770 + 500 + 200] / 23
         *      = 1470 / 23
         *      = 63.91304347826
         */
        $this->assertEquals(63.91304347826, $project->getNormalisedMark($this->hodes, false));
    }

    /**
     * Test the weighted average
     * when there's more than one of a particular type with published and unpublished feedback
     */
    public function testWeightedAverage5()
    {
        /* There are five assessables for Hodes
         *
         * Due Work 3 (98 marks)
         * Task (null marks)
         * Due Work 2 (77 marks)
         * Due Work (100 marks)
         * Quiz (12 marks)
         */
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables((new Collection())
            ->add($this->spawnDueWork3())
            ->add($this->spawnTask())
            ->add($this->spawnDueWork2())
            ->add($this->spawnDueWork())
            ->add($this->spawnQuiz()));

        /* Published Weighted Average is
         *      = [(98*6) + (77*10) + (100*5) + ((100 * 12/20)*8)] / 29
         *      = [588 + 770 + 500 + 480] / 29
         *      = 2338 / 29
         *      = 80.62068965517
         */
        $this->assertEquals(80.62068965517, $project->getNormalisedMark($this->hodes, true));

        /* Unpublished Weighted Average is
        *      = [(98*6) * (77*10) + (100*5) + ((100 * 5/20)*8)] / 29
        *      = [588 + 770 + 500 + 200] / 29
        *      = 2058 / 29
        *      = 70.96551724138
        */
        $this->assertEquals(70.96551724138, $project->getNormalisedMark($this->hodes, false));
    }

    /** Really go to town */

    /**
     * A real belting
     */
    public function testWeightedAverage10() {
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables($this->massProduce());

       /* Due Work 1: Weight 20, NormalisedMark: P,18 UP,18
        * Due Work 2: Weight 11, NormalisedMark: P,91 UP,91
        * Due Work 3: Weight  1, NormalisedMark: P,89 UP,89
        * Due Work 4: Weight  4, NormalisedMark: P,null UP,null
        * Due Work 5: Weight  4, NormalisedMark: P,null UP,76
        * Due Work 6: Weight  4, NormalisedMark: P,null UP,null
        * Due Work 7: Weight 22, NormalisedMark: P,96 UP,96
        *
        * Total Weight: 66
        *
        * Published Weighted Average is:
        *       = [(18*20/66) + (91*11/66) + (89*1/66) + (96*22/66)]
        *       = [360/66 + 1001/66 + 89/66 + 2112/66]
        *       = 3562/66
        *       = 53.96969696970
        *
        * Unpublished Weighted Average is:
        *       = [(18*20/66) + (91*11/66) + (89*1/66) + (76*4/66) + (96*22/66)]
        *       = [360/66 + 1001/66 + 89/66 + 304/66 + 2112/66]
        *       = 3866/66
        *       = 58.57575757576
        */
        $this->assertEquals(53.96969696970, $project->getNormalisedMark($this->hodes, true));
        $this->assertEquals(58.57575757576, $project->getNormalisedMark($this->hodes, false));
    }

    /**
     * A real belting
     */
    public function testWeightedAverage11() {
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables($this->massProduce());

        /* Reset all the weights to zero
         */
        foreach ($project->getAssessables() as $currentAssessable) {
            $currentAssessable->setWeight(0);
        }

        /* Due Work 1: Weight  0, NormalisedMark: P,18 UP,18
         * Due Work 2: Weight  0, NormalisedMark: P,91 UP,91
         * Due Work 3: Weight  0, NormalisedMark: P,89 UP,89
         * Due Work 4: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  0, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 7: Weight  0, NormalisedMark: P,96 UP,96
         *
         * Total Weight: 66
         *
         * Published Weighted Average is: 0
         * Unpublished Weighted Average is: 0
         */
        $this->assertEquals(0.00000000000, $project->getNormalisedMark($this->hodes, true));
        $this->assertEquals(0.00000000000, $project->getNormalisedMark($this->hodes, false));
    }

    /**
     * A real belting
     */
    public function testWeightedAverage12() {
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables($this->massProduce());

        /* Reset all but one of the weights
         */
        foreach ($project->getAssessables() as $currentAssessable) {
            if ($currentAssessable->getId() != 10005) {
                $currentAssessable->setWeight(0);
            }
        }

        /* Due Work 1: Weight  0, NormalisedMark: P,18 UP,18
         * Due Work 2: Weight  0, NormalisedMark: P,91 UP,91
         * Due Work 3: Weight  0, NormalisedMark: P,89 UP,89
         * Due Work 4: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  4, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  0, NormalisedMark: P,null UP,null
         * Due Work 7: Weight  0, NormalisedMark: P,96 UP,96
         *
         * Total Weight: 66
         *
         * Published Weighted Average is:
         *      = [(18*0/66) + (91*0/66) + (89*0/66) + (96*0/66)]
         *      = [0 + 0 + 0 + 0]
         *      = 0
         *
         * Unpublished Weighted Average is:
         *      = 76*4/4
         *      = 76
         */
        $this->assertEquals(0, $project->getNormalisedMark($this->hodes, true));
        $this->assertEquals(76.00000000000, $project->getNormalisedMark($this->hodes, false));
    }

    /**
     * A real belting
     */
    public function testWeightedAverage13() {
        $project = $this->spawnProject(new SummatorWeightedAverage());
        $project->setAssessables($this->massProduce());

        /* Reset all but one of the weights
         */
        foreach ($project->getAssessables() as $currentAssessable) {
            $currentAssessable->setWeight(null);
        }

        /* Due Work 1: Weight  null, NormalisedMark: P,null UP,18
         * Due Work 2: Weight  null, NormalisedMark: P,null UP,91
         * Due Work 3: Weight  null, NormalisedMark: P,null UP,89
         * Due Work 4: Weight  null, NormalisedMark: P,null UP,null
         * Due Work 5: Weight  null, NormalisedMark: P,null UP,76
         * Due Work 6: Weight  null, NormalisedMark: P,null UP,null
         * Due Work 7: Weight  null, NormalisedMark: P,null UP,96
         *
         * Total Weight: 66
         *
         * Published Weighted Average is: null
         *
         * Unpublished Weighted Average is:
         *      = 76*4/4
         *      = 76
         */
        $this->assertEquals(null, $project->getNormalisedMark($this->hodes, true));
        $this->assertEquals(null, $project->getNormalisedMark($this->hodes, false));
    }

    /**
     * Mass produce a giant Due Work set
     *
     * @return Collection
     */
    public function massProduce()
    {
        $collection = new Collection();

        /**
         * A Due Work with one floating feedback
         */
        $id = 10001;
        $title = 'First Due Work';
        $description = 'The first due work to do';
        $typeName = 'Homework';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setType((new DueWorkType())
                ->setId(2)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setWeight(20)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90011)
            ->setAssessable($dueWork)
            ->setNormalisedMark(18)
            ->setReviewer($this->celia)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-13 13:31:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);
        $collection->add($dueWork);

        /**
         * A Due Work with one submission with one associated feedback
         * and multiple floating feedback
         */
        $id = 10002;
        $title = 'Second Due Work';
        $description = 'The second due work to do';
        $typeName = 'Homework';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setType((new DueWorkType())
                ->setId(2)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setWeight(11)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $submission = new DueWorkSubmission();
        $submission
            ->setId(70021)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(90021)
            ->setAssessable($dueWork)
            ->setNormalisedMark(14)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90022)
            ->setAssessable($dueWork)
            ->setNormalisedMark(54)
            ->setComment('An outdated feedback')
            ->setReviewer($this->celia)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-12 12:21:12', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90023)
            ->setAssessable($dueWork)
            ->setNormalisedMark(91)
            ->setComment('This is the most recent published feedback; also the most recent feedback')
            ->setReviewer($this->celia)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-13 13:31:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);
        $collection->add($dueWork);

        /**
         * A Due Work with one submission with one associated feedback
         * and one floating feedback
         */
        $id = 10003;
        $title = 'Third Due Work';
        $description = 'The third due work to do';
        $typeName = 'Oral Presentation';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setType((new DueWorkType())
                ->setId(3)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setWeight(1)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $submission = new DueWorkSubmission();
        $submission
            ->setId(70031)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(90031)
            ->setAssessable($dueWork)
            ->setNormalisedMark(45)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90002)
            ->setAssessable($dueWork)
            ->setNormalisedMark(89)
            ->setComment('This is the most recent published feedback; also the most recent feedback')
            ->setReviewer($this->celia)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-13 13:31:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);
        $collection->add($dueWork);


        /**
         * A Due Work with one submission
         */
        $id = 10004;
        $title = 'Fourth Due Work';
        $description = 'The fourth due work to do';
        $typeName = 'Oral Presentation';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setType((new DueWorkType())
                ->setId(3)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setWeight(4)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $submission = new DueWorkSubmission();
        $submission
            ->setId(70041)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $collection->add($dueWork);

        /**
         * A Due Work with no submissions nor feedbacks
         */
        $id = 10005;
        $title = 'Fifth Due Work';
        $description = 'The fifth due work to do';
        $typeName = 'Class Presentation';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setType((new DueWorkType())
                ->setId(5)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setWeight(4)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $submission = new DueWorkSubmission();
        $submission
            ->setId(70051)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(90051)
            ->setAssessable($dueWork)
            ->setNormalisedMark(76)
            ->setReviewer($this->celia)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 10:10:10', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 10:10:10', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        $collection->add($dueWork);

        /**
         * A Due Work with no submissions and one unpublished feedback
         */
        $id = 10006;
        $title = 'Sixth Due Work';
        $description = 'The Sixth due work to do';
        $typeName = 'Class Presentation';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setType((new DueWorkType())
                ->setId(5)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setWeight(4)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $collection->add($dueWork);


        /**
         * A Due Work with multiple submissions and associated feedbacks
         * and multiple floating feedbacks
         */
        $id = 10007;
        $title = 'Seventh Due Work';
        $description = 'The seventh due work to do';
        $typeName = 'Homework';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setType((new DueWorkType())
                ->setId(1)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setWeight(22)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90071)
            ->setAssessable($dueWork)
            ->setNormalisedMark(41)
            ->setReviewer($this->celia)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 01:01:01', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 01:01:01', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90072)
            ->setAssessable($dueWork)
            ->setNormalisedMark(34)
            ->setReviewer($this->celia)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-02 02:02:02', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 02:02:02', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(90073)
            ->setAssessable($dueWork)
            ->setNormalisedMark(67)
            ->setReviewer($this->celia)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-03 03:03:03', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-03 03:03:03', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $submission = new DueWorkSubmission();
        $submission
            ->setId(70071)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-04 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-04 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(90074)
            ->setAssessable($dueWork)
            ->setNormalisedMark(99)
            ->setReviewer($this->celia)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-04 04:04:04', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-04 04:04:04', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-04 04:04:04', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        $feedback = (new DueWorkFeedback())
            ->setId(90075)
            ->setAssessable($dueWork)
            ->setNormalisedMark(97)
            ->setReviewer($this->celia)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-05 05:05:05', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-05 05:05:05', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-05 05:05:05', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        $feedback = (new DueWorkFeedback())
            ->setId(90076)
            ->setAssessable($dueWork)
            ->setNormalisedMark(96)
            ->setReviewer($this->celia)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 06:06:06', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 06:06:06', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 06:06:06', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        $collection->add($dueWork);

        /* Finally
         */
        return $collection;
    }

    /**
     * Spawn a Project with stuff in it
     *
     * @param ISummator $summatorStrategy
     * @return Project
     */
    protected function spawnProject(ISummator $summatorStrategy)
    {
        $id = 5567;
        $title = 'Find Diablo';
        $description = 'The things you need to do in order to slay the prime evil';

        $project = new Project($summatorStrategy);
        $project
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setPublishAt($this->tomorrow)
            ->setOpenAt($this->tomorrow)
            ->setDueAt($this->fiftyDaysFromNow)
            ->setCloseAt($this->fiftyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        return $project;
    }

    /**
     * Spawn another Due Work
     *
     * @return DueWork
     */
    protected function spawnDueWork3()
    {
        $id = 13;
        $title = 'Stay Awhile And Listen';
        $description = 'I have a story to tell...';
        $typeName = 'Homework';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setType((new DueWorkType())
                ->setId(2)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setWeight(6)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(9912)
            ->setAssessable($dueWork)
            ->setNormalisedMark(98)
            ->setComment('The greatest story ever told!')
            ->setReviewer($this->celia)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-13 13:31:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        return $dueWork;
    }

    /**
     * Spawn another Due Work
     *
     * @return DueWork
     */
    protected function spawnDueWork2()
    {
        $id = 7;
        $title = 'Collect Horadric Gems';
        $description = 'How many gems did you find?';
        $typeName = 'Class Presentation';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setType((new DueWorkType())
                ->setId(2)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setWeight(10)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(7889)
            ->setAssessable($dueWork)
            ->setNormalisedMark(77)
            ->setComment('cool collection of gems')
            ->setReviewer($this->celia)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-11 11:12:23', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        return $dueWork;
    }

    /**
     * Spawn a Due Work
     *
     * @return DueWork
     */
    protected function spawnDueWork()
    {
        $id = 2;
        $title = 'Enter the Realm of Terroarrrr';
        $description = 'Diablo was found in the land of terroarrrrr! Find him, and whack him!';
        $typeName = 'Essay';

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setType((new DueWorkType())
                ->setId(1)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setWeight(5)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $submission = new DueWorkSubmission();
        $submission
            ->setId(553)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(3385)
            ->setAssessable($dueWork)
            ->setNormalisedMark(-3)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllResponses()->add($submission);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        $submission->getAllFeedback()->add($feedback);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4223)
            ->setAssessable($dueWork)
            ->setNormalisedMark(37)
            ->setReviewer($this->celia)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 22:22:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4236)
            ->setAssessable($dueWork)
            ->setNormalisedMark(100)
            ->setComment('meow - this is the most recently published feedback, despite the ordering in which this appears')
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 23:43:43', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 23:41:41', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 23:41:41', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4225)
            ->setAssessable($dueWork)
            ->setNormalisedMark(10)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 23:23:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 23:23:23', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 23:23:21', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        return $dueWork;
    }

    /**
     * Spawn a Task
     */
    protected function spawnTask()
    {
        $id = 1234;
        $title = 'Read the Horadric Scrolls';
        $description = 'There is a book by Deckard Cain which you must read before searching for Diablo.';

        $task = new Task();
        $task
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->celia)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setWeight(4)
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->thirteenDaysFromNow)
            ->setDueAt($this->fourteenDaysFromNow)
            ->setCloseAt($this->fourteenDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $completion = (new TaskCompletion);
        $completion
            ->setId(883)
            ->setAssessable($task)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = new TaskFeedback();
        $feedback
            ->setId(87)
            ->setAssessable($task)
            ->setComment('This is the most recently published feedback')
            ->setNormalisedMark(999999)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-15 01:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $floatingFeedback = new TaskFeedback();
        $floatingFeedback
            ->setId(89)
            ->setAssessable($task)
            ->setNormalisedMark(8888888)
            ->setReviewer($this->celia)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-07 19:03:20', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-07 19:03:20', \Alaress_Date_Format::DATABASE_FORMAT));

        $completion->getAllFeedback()->add($feedback);

        $task->getParticipant($this->hodes)->getAllResponses()->add($completion);
        $task->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);
        $task->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        return $task;
    }

    /**
     * Spawn a Quiz
     *
     * @return $this
     */
    protected function spawnQuiz()
    {
        $id = 3;
        $title = 'How did you find The Prime Evil?';
        $description = 'A quick pop quiz about how you died your way to Diablo.';

        $quiz = (new Quiz())
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($this->somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($this->hodes))
                ->add(new Participant($this->bsalem))
                ->add(new Participant($this->sberryhill))
                ->add(new Participant($this->asaxon)))
            ->setWeight(8)
            ->setPublishAt($this->twentyDaysFromNow)
            ->setOpenAt($this->twentyDaysFromNow)
            ->setDueAt($this->thirtyDaysFromNow)
            ->setCloseAt($this->fortyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $attempt = (new QuizAttempt);
        $attempt
            ->setId(8655)
            ->setAssessable($quiz)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $feedback = new QuizReview();
        $feedback
            ->setId(99759)
            ->setAssessable($quiz)
            ->setNormalisedMark(98)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-15 03:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $quiz->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        $feedback = new QuizReview();
        $feedback
            ->setId(99760)
            ->setAssessable($quiz)
            ->setNormalisedMark(45)
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-16 05:31:29', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-12 12:12:12', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-09-12 12:12:12', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $quiz->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        $feedback = new QuizReview();
        $feedback
            ->setId(99761)
            ->setAssessable($quiz)
            ->setNormalisedMark(1)
            ->setAllResponses([
                [
                    'mark' => 1, // Question 1
                    'available' => 5
                ],
                [
                    'mark' => 1, // Question 2
                    'available' => 5
                ],
                [
                    'mark' => 1, // Question 3
                    'available' => 5
                ],
                [
                    'mark' => 2, // Question 4
                    'available' => 5
                ]
            ])
            ->setComment('This is the most recent feedback')
            ->setReviewer($this->celia)
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-17 13:23:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-09-17 13:23:13', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $quiz->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        $feedback = new QuizReview();
        $feedback
            ->setId(99762)
            ->setAssessable($quiz)
            ->setNormalisedMark(83)
            ->setAllResponses([
                [
                    'mark' => 3, // Question 1,
                    'available' => 5
                ],
                [
                    'mark' => 2, // Question 2
                    'available' => 5
                ],
                [
                    'mark' => 2, // Question 3
                    'available' => 5
                ],
                [
                    'mark' => 5, // Question 4
                    'available' => 5
                ]
            ])
            ->setComment('This is the most recent published feedback')
            ->setReviewer($this->celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-25 21:22:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-09-10 21:22:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-09-10 21:22:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
        $quiz->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        $quiz->getParticipant($this->hodes)->getAllResponses()->add($attempt);

        return $quiz;
    }
}