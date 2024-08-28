<?php

/**
 * DueWorkTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Collection;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as DueWorkSubmission;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile\Model as DueWorkFeedbackFile;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as DueWorkSubmissionFile;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Model as DueWorkSubmissionFilePlagscan;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Learning\Assessment\DueWork\Model::class)]
class DueWorkTest extends TestCase
{
    use Defaults;


    /**
     * Test that Due Work initialises into a usable state
     *
     * @throws \PHPUnit\Framework\Exception
     */
    public function testInit()
    {
        $dueWork = new DueWork();

        $this->assertInstanceOf(Collection::class, $dueWork->getAllParticipants());
        $this->assertInstanceOf(Collection::class, $dueWork->getAttachedFiles());
        $this->assertNull($dueWork->getWorkType());
        $this->assertSame(DueWork::ACCEPT_ONLINE_NONE, $dueWork->getAcceptOnline());
    }

    /**
     * Spawn a Due Work
     *
     * @return DueWork
     */
    private function spawnDueWork()
    {
        $id = 2;
        $title = 'Enter the Realm of Terroarrrr';
        $description = 'Diablo was found in the land of terroarrrrr! Find him, and whack him!';
        $typeName = 'Essay';

        $files = [
            [
                'id' => 1,
                'hash' => 'abcdefg12341234',
                'filename' => 'myfile1.jpg'
            ],
            [
                'id' => 2,
                'hash' => 'zxvzxcvzxcv945786947',
                'filename' => 'myfile2.jpg'
            ],
        ];

        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType((new WorkType())
                ->setId(1)
                ->setName($typeName))
            ->setCreator($this->somodio)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAttachedFiles((new Collection())
                ->add((new DueWorkFile())
                    ->setId($files[0]['id'])
                    ->setStorage((new Storage())->setHash($files[0]['hash']))
                    ->setFilename($files[0]['filename']))
                ->add((new DueWorkFile())
                    ->setId($files[1]['id'])
                    ->setStorage((new Storage())->setHash($files[1]['hash']))
                    ->setFilename($files[1]['filename'])))
            ->setPublishAt($this->thirteenDaysFromNow)
            ->setOpenAt($this->fourteenDaysFromNow)
            ->setDueAt($this->twentyDaysFromNow)
            ->setCloseAt($this->thirtyDaysFromNow)
            ->setCreatedAt($this->today)
            ->setUpdatedAt($this->today);

        $plan = $this->getLinker()->createAndLinkPlan($dueWork);
        $this->getLinker()->createAndLinkParticipant($this->hodes, $plan);
        $this->getLinker()->createAndLinkParticipant($this->bsalem, $plan);
        $this->getLinker()->createAndLinkParticipant($this->sberryhill, $plan);
        $this->getLinker()->createAndLinkParticipant($this->asaxon, $plan);

        return $dueWork;
    }


    /**
     * Test that Due Work basic details can be manufactured meaningfully
     *
     * @throws \PHPUnit\Framework\Exception
     * @throws \UnexpectedValueException
     */
    public function testBuild()
    {
        $title = 'Enter the Realm of Terroarrrr';
        $description = 'Diablo was found in the land of terroarrrrr! Find him, and whack him!';
        $typeName = 'Essay';

        $files = [
            [
                'id' => 1,
                'hash' => 'abcdefg12341234',
                'filename' => 'myfile1.jpg'
            ],
            [
                'id' => 2,
                'hash' => 'zxvzxcvzxcv945786947',
                'filename' => 'myfile2.jpg'
            ],
        ];

        $dueWork = $this->spawnDueWork();

        // This accepts multiple submissions for the purpose of testing
        $dueWork->setAcceptsMultiple();

        /* Details
         */
        $this->assertEquals($title, $dueWork->getTitle());
        $this->assertEquals($description, $dueWork->getDescription());
        $this->assertEquals($typeName, $dueWork->getTypeName());

        /* Authored correctly?
         */
        $this->assertEquals($this->somodio->getId(), $dueWork->getCreator()->getId());

        /* Everybody on board?
         */
        $this->assertCount(4, $dueWork->getAllParticipants());

        /** @var User $currentUser */
        foreach ([$this->hodes, $this->bsalem, $this->sberryhill, $this->asaxon] as $currentUser) {
            $this->assertEquals($currentUser->getId(), $dueWork->getParticipant($currentUser)->getUser()->getId());
        }

        /* Files
         */
        foreach ($files as $i => $currentFile) {
            $this->assertEquals($currentFile['id'], $dueWork->getAttachedFiles()->getOffset($i)->getId());
            $this->assertEquals($currentFile['hash'], $dueWork->getAttachedFiles()->getOffset($i)->getHash());
            $this->assertEquals($currentFile['filename'], $dueWork->getAttachedFiles()->getOffset($i)->getFilename());
        }

        /* Dates
         */
        $this->assertEquals($this->thirteenDaysFromNow->getDatabase(), $dueWork->getPublishAt()->getDatabase());
        $this->assertEquals($this->fourteenDaysFromNow->getDatabase(), $dueWork->getOpenAt()->getDatabase());
        $this->assertEquals($this->twentyDaysFromNow->getDatabase(), $dueWork->getDueAt()->getDatabase());
        $this->assertEquals($this->thirtyDaysFromNow->getDatabase(), $dueWork->getCloseAt()->getDatabase());

        $this->assertEquals($this->today->getDatabase(), $dueWork->getCreatedAt()->getDatabase());
        $this->assertEquals($this->today->getDatabase(), $dueWork->getUpdatedAt()->getDatabase());
        $this->assertEquals(null, $dueWork->getDeletedAt());
    }

    /**
     * Test that Due Work basic details can be manufactured meaningfully
     */
    public function testBuildOfflineSubmission()
    {
        $dueWork = $this->spawnDueWork();

        // This doesn't accept submissions
        $dueWork
            ->setAcceptsNone()
            ->setPublishAt($this->thirtyDaysFromNow)
            ->setOpenAt($this->fortyDaysFromNow)
            ->setDueAt($this->fiftyDaysFromNow)
            ->setCloseAt($this->sixtyDaysFromNow);

        // Open is the same as publish date
        $this->assertEquals($this->thirtyDaysFromNow->getDatabase(), $dueWork->getPublishAt()->getDatabase());
        $this->assertEquals($this->thirtyDaysFromNow->getDatabase(), $dueWork->getOpenAt()->getDatabase());

        // Close is the same as due date
        $this->assertEquals($this->fiftyDaysFromNow->getDatabase(), $dueWork->getDueAt()->getDatabase());
        $this->assertEquals($this->fiftyDaysFromNow->getDatabase(), $dueWork->getCloseAt()->getDatabase());

        $dueWork->setAcceptsSingle();
        // Now dates are separate
        $this->assertEquals($this->thirtyDaysFromNow->getDatabase(), $dueWork->getPublishAt()->getDatabase());
        $this->assertEquals($this->fortyDaysFromNow->getDatabase(), $dueWork->getOpenAt()->getDatabase());
        $this->assertEquals($this->fiftyDaysFromNow->getDatabase(), $dueWork->getDueAt()->getDatabase());
        $this->assertEquals($this->sixtyDaysFromNow->getDatabase(), $dueWork->getCloseAt()->getDatabase());

        $dueWork->setAcceptsMultiple();
        // Dates are still separate
        $this->assertEquals($this->thirtyDaysFromNow->getDatabase(), $dueWork->getPublishAt()->getDatabase());
        $this->assertEquals($this->fortyDaysFromNow->getDatabase(), $dueWork->getOpenAt()->getDatabase());
        $this->assertEquals($this->fiftyDaysFromNow->getDatabase(), $dueWork->getDueAt()->getDatabase());
        $this->assertEquals($this->sixtyDaysFromNow->getDatabase(), $dueWork->getCloseAt()->getDatabase());
    }

    /**
     * Test that Due Work Responses can be manufactured meaningfully
     */
    public function testResponses()
    {
        $submissionFiles = [
            [
                'id' => 11,
                'hash' => 's89789d9hw3thsug8923u5',
                'filename' => 'mark this Sir.xlsx',
                'plagscan' => [
                    'id' => 1234,
                    'status' => 1,
                    'score' => '93% plagiariased',
                    'html' => '<div><strong>YOU CHEATED</strong></div>'
                ]
            ],
            [
                'id' => 23,
                'hash' => 'lk5j34kl5jousdio',
                'filename' => 'and this too.docx'
            ],
        ];

        $dueWork = $this->spawnDueWork();

        $submission = new DueWorkSubmission();
        $submission
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setAllFiles((new Collection())
                ->add((new DueWorkSubmissionFile())
                    ->setId($submissionFiles[0]['id'])
                    ->setStorage((new Storage())
                        ->setHash($submissionFiles[0]['hash']))
                    ->setFilename($submissionFiles[0]['filename'])
                    ->setPlagscan((new DueWorkSubmissionFilePlagscan())
                        ->setId($submissionFiles[0]['plagscan']['id'])
                        ->setStatus($submissionFiles[0]['plagscan']['status'])
                        ->setScore($submissionFiles[0]['plagscan']['score'])))
                ->add((new DueWorkSubmissionFile())
                    ->setId($submissionFiles[1]['id'])
                    ->setStorage((new Storage())
                        ->setHash($submissionFiles[1]['hash']))
                    ->setFilename($submissionFiles[1]['filename'])))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        /* Check that it linked to the Due Work correctly
         */
        $this->assertEquals(2, $submission->getAssessable()->getId());

        /* Authors
         */
        $this->assertEquals($this->hodes->getId(), $submission->getSubmittedBy()->getId());
        $this->assertEquals($this->hodes->getId(), $submission->getSubmittedOnBehalfOf()->getId());

        /* Files
         */
        foreach ($submissionFiles as $i => $currentFile) {
            $this->assertEquals($currentFile['id'], $submission->getAllFiles()->getOffset($i)->getId());
            $this->assertEquals($currentFile['hash'], $submission->getAllFiles()->getOffset($i)->getStorage()->getHash());
            $this->assertEquals($currentFile['filename'], $submission->getAllFiles()->getOffset($i)->getFilename());
        }

        /* One of them has a Plagscan
         */
        $this->assertEquals($submissionFiles[0]['plagscan']['id'], $submission->getAllFiles()->getOffset(0)->getPlagscan()->getId());
        $this->assertEquals($submissionFiles[0]['plagscan']['status'], $submission->getAllFiles()->getOffset(0)->getPlagscan()->getStatus());
        $this->assertEquals($submissionFiles[0]['plagscan']['score'], $submission->getAllFiles()->getOffset(0)->getPlagscan()->getScore());

        /* Dates
         */
        $this->assertEquals('2016-08-01 09:00:00', $submission->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-08-01 09:00:00', $submission->getUpdatedAt()->getDatabase());
        $this->assertEquals(null, $submission->getDeletedAt());
    }

    /**
     * Test that Due Work Feedback can be manufactured meaningfully
     */
    public function testFeedback()
    {
        $feedbackFiles = [
            [
                'id' => 112,
                'hash' => 'lj23lj2llj5l2jsdkjf90',
                'filename' => 'This is the correct response.txt',
            ],
        ];

        $dueWork = $this->spawnDueWork();

        $feedback = (new DueWorkFeedback())
            ->setId(3385)
            ->setAssessable($dueWork)
            ->setNormalisedMark(-3)
            ->setComment('This is a flawed piece of work.')
            ->setReviewer($this->celia)
            ->setAllFiles((new Collection())
                ->add((new DueWorkFeedbackFile())
                    ->setId($feedbackFiles[0]['id'])
                    ->setStorage((new Storage())
                        ->setHash($feedbackFiles[0]['hash']))
                    ->setFilename($feedbackFiles[0]['filename'])))
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT));

        /* Check that it linked to the Due Work correctly
         */
        $this->assertEquals(2, $feedback->getAssessable()->getId());
        $this->assertEquals(-3, $feedback->getNormalisedMark());
        $this->assertEquals('This is a flawed piece of work.', $feedback->getComment());

        /* Authors
         */
        $this->assertEquals($this->celia->getId(), $feedback->getReviewer()->getId());

        /* Files
         */
        foreach ($feedbackFiles as $i => $currentFile) {
            $this->assertEquals($currentFile['id'], $feedback->getAllFiles()->getOffset($i)->getId());
            $this->assertEquals($currentFile['hash'], $feedback->getAllFiles()->getOffset($i)->getStorage()->getHash());
            $this->assertEquals($currentFile['filename'], $feedback->getAllFiles()->getOffset($i)->getFilename());
        }

        /* Dates
         */
        $this->assertEquals('2016-08-03 14:15:16', $feedback->getPublishedAt()->getDatabase());
        $this->assertEquals('2016-08-01 13:14:15', $feedback->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-08-02 14:14:12', $feedback->getUpdatedAt()->getDatabase());
        $this->assertEquals(null, $feedback->getDeletedAt());
    }


    /**
     * Test that Due Work can contain Responses and Feedback meaningfully
     * including the shortcut that Feedbacks have to their Due Work
     *
     * @throws \UnexpectedValueException
     * @throws \PHPUnit\Framework\Exception
     */
    public function testResponseWithFeedback()
    {
        $submissionFiles = [
            [
                'id' => 11,
                'hash' => 's89789d9hw3thsug8923u5',
                'filename' => 'mark this Sir.xlsx',
                'plagscan' => [
                    'id' => 1234,
                    'status' => 1,
                    'score' => '93% plagiariased',
                    'html' => '<div><strong>YOU CHEATED</strong></div>'
                ]
            ],
            [
                'id' => 23,
                'hash' => 'lk5j34kl5jousdio',
                'filename' => 'and this too.docx'
            ],
        ];

        $feedbackFiles = [
            [
                'id' => 112,
                'hash' => 'lj23lj2llj5l2jsdkjf90',
                'filename' => 'This is the correct response.txt',
            ],
        ];

        $dueWork = $this->spawnDueWork();

        $submission = new DueWorkSubmission();
        $submission
            ->setId(553)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setAllFiles((new Collection())
                ->add((new DueWorkSubmissionFile())
                    ->setId($submissionFiles[0]['id'])
                    ->setStorage((new Storage())
                        ->setHash($submissionFiles[0]['hash']))
                    ->setFilename($submissionFiles[0]['filename'])
                    ->setPlagscan((new DueWorkSubmissionFilePlagscan())
                        ->setId($submissionFiles[0]['plagscan']['id'])
                        ->setStatus($submissionFiles[0]['plagscan']['status'])
                        ->setScore($submissionFiles[0]['plagscan']['score'])))
                ->add((new DueWorkSubmissionFile())
                    ->setId($submissionFiles[1]['id'])
                    ->setStorage((new Storage())
                        ->setHash($submissionFiles[1]['hash']))
                    ->setFilename($submissionFiles[1]['filename'])))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(3385)
            ->setAssessable($dueWork)
            ->setNormalisedMark(-3)
            ->setComment('This is a flawed piece of work.')
            ->setReviewer($this->celia)
            ->setAllFiles((new Collection())
                ->add((new DueWorkFeedbackFile())
                    ->setId($feedbackFiles[0]['id'])
                    ->setStorage((new Storage())
                        ->setHash($feedbackFiles[0]['hash']))
                    ->setFilename($feedbackFiles[0]['filename'])))
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);
        $dueWork->getParticipant($this->hodes)->getAllResponses()->add($submission);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        /* Freefloating Feedback
         *
         * This feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4223)
            ->setAssessable($dueWork)
            ->setNormalisedMark(37)
            ->setComment('Actually it is ok')
            ->setReviewer($this->celia)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        /* Check everything connects up
         */
        $this->assertEquals(2, $submission->getAssessable()->getId());
        $this->assertEquals(2, $feedback->getAssessable()->getId());
        $this->assertCount(1, $submission->getAllFeedback());

        /* Total number of Responses
         */
        $this->assertCount(1, $dueWork->getParticipant($this->hodes)->getAllResponses());
        $this->assertCount(2, $dueWork->getParticipant($this->hodes)->getAllFeedbacks());

        /* Feedback setup
         * The Freefloater isn't published
         */
        $this->assertEquals(3385, $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->getOffset(0)->getId());
        $this->assertEquals(true, $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->getOffset(0)->isPublished());
        $this->assertEquals(false, $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->getOffset(1)->isPublished());

        /* Total number of Feedbacks
         */
        $this->assertCount(2, $dueWork->getParticipant($this->hodes)->getAllFeedbacks());

        /* Feedbacks directly associated with Assessable
         * and not the Submission itself
         */
        $this->assertEquals(4223, $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->getOffset(1)->getId());
    }


    /**
     * Test that Due Work Feedback Marks are stored correctly
     *
     * @throws \UnexpectedValueException
     */
    public function testMostRecentFeedbacks()
    {
        $submissionFiles = [
            [
                'id'         => 11,
                'hash'       => 's89789d9hw3thsug8923u5',
                'filename'   => 'mark this Sir.xlsx',
                'plagscan'   => [
                    'id'     => 1234,
                    'status' => 1,
                    'score'  => '93% plagiariased',
                    'html'   => '<div><strong>YOU CHEATED</strong></div>'
                ]
            ],
            [
                'id'         => 23,
                'hash'       => 'lk5j34kl5jousdio',
                'filename'   => 'and this too.docx'
            ],
        ];

        $feedbackFiles = [
            [
                'id'         => 112,
                'hash'       => 'lj23lj2llj5l2jsdkjf90',
                'filename'   => 'This is the correct response.txt',
            ],
        ];

        $dueWork = $this->spawnDueWork();

        $submission = new DueWorkSubmission();
        $submission
            ->setId(553)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setAllFiles(
                (new Collection())
                    ->add(
                        (new DueWorkSubmissionFile())
                            ->setId($submissionFiles[0]['id'])
                            ->setStorage(
                                (new Storage())
                                    ->setHash($submissionFiles[0]['hash'])
                            )
                            ->setFilename($submissionFiles[0]['filename'])
                            ->setPlagscan(
                                (new DueWorkSubmissionFilePlagscan())
                                    ->setId($submissionFiles[0]['plagscan']['id'])
                                    ->setStatus($submissionFiles[0]['plagscan']['status'])
                                    ->setScore($submissionFiles[0]['plagscan']['score'])
                            )
                    )
                    ->add(
                        (new DueWorkSubmissionFile())
                            ->setId($submissionFiles[1]['id'])
                            ->setStorage(
                                (new Storage())
                                    ->setHash($submissionFiles[1]['hash'])
                            )
                            ->setFilename($submissionFiles[1]['filename'])
                    )
            )
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(3385)
            ->setAssessable($dueWork)
            ->setNormalisedMark(-3)
            ->setComment('This is a flawed piece of work.')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(
                (new Collection())
                    ->add(
                        (new DueWorkFeedbackFile())
                            ->setId($feedbackFiles[0]['id'])
                            ->setStorage(
                                (new Storage())
                                    ->setHash($feedbackFiles[0]['hash'])
                            )
                            ->setFilename($feedbackFiles[0]['filename'])
                    )
            )
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT));

        $submission->getAllFeedback()->add($feedback);

        $dueWork->getParticipant($this->hodes)->getAllResponses()->add($submission);
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4223)
            ->setAssessable($dueWork)
            ->setNormalisedMark(37)
            ->setComment('Actually it is ok')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-12 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        /* The most recent feedback is the unpublished one
         * This is 3385 and its currently drafted normalised mark of -3
         */
        $this->assertEquals(4223, $dueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenFeedback()->getId());
        $this->assertEquals(37, $dueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenFeedback()->getNormalisedMark());

        /* Published Marks exist in 3385, which was published 2016-08-03 14:15:16 (which is
         * also after $submission was spawned on 2016-08-01 09:00:00)
         */
        $this->assertEquals(3385, $dueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenPublishedFeedback()->getId());


        // Test self-assessment

        // This has no self-assessment yet
        $this->assertNull($dueWork->getParticipant($this->hodes)->findMostRecentSelfAssessedFeedback());
        $this->assertNull($dueWork->getParticipant($this->hodes)->findMostRecentSelfAssessedPublishedFeedback());

        // So add one (not published yet)
        $floatingSelfAssessedFeedback = (new DueWorkFeedback())
            ->setId(4224)
            ->setAssessable($dueWork)
            ->setNormalisedMark(99)
            ->setComment('I am awesome')
            ->setReviewer($this->hodes)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-12 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingSelfAssessedFeedback);

        // It exists on participant but not a feedback yet
        $this->assertSame($floatingSelfAssessedFeedback, $dueWork->getParticipant($this->hodes)->findMostRecentSelfAssessedFeedback());
        $this->assertNull($dueWork->getParticipant($this->hodes)->findMostRecentSelfAssessedPublishedFeedback());
        $this->assertNull($submission->findMostRecentSelfAssessedFeedback());
        $this->assertNull($submission->findMostRecentSelfAssessedPublishedFeedback());

        // It must not affect the authoritative feedback, or its mark
        $this->assertSame($floatingFeedback, $dueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenFeedback());
        $this->assertEquals(-3, $dueWork->getParticipant($this->hodes)->getNormalisedMark(true));
        $this->assertEquals(37, $dueWork->getParticipant($this->hodes)->getNormalisedMark(false));

        // Publish it
        $floatingSelfAssessedFeedback
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-12 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));
        $this->assertSame($floatingSelfAssessedFeedback, $dueWork->getParticipant($this->hodes)->findMostRecentSelfAssessedFeedback());
        $this->assertSame($floatingSelfAssessedFeedback, $dueWork->getParticipant($this->hodes)->findMostRecentSelfAssessedPublishedFeedback());
        $this->assertNull($submission->findMostRecentSelfAssessedFeedback());
        $this->assertNull($submission->findMostRecentSelfAssessedPublishedFeedback());

        // It still must not affect the authoritative feedback, or its mark
        $this->assertSame($floatingFeedback, $dueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenFeedback());
        $this->assertEquals(-3, $dueWork->getParticipant($this->hodes)->getNormalisedMark(true));
        $this->assertEquals(37, $dueWork->getParticipant($this->hodes)->getNormalisedMark(false));


        // Test with a self-assess attached to a response
        // (Less recent than most recent self-assess for now)
        $selfAssessedFeedbackForSubmission = (new DueWorkFeedback())
            ->setId(4225)
            ->setAssessable($dueWork)
            ->setNormalisedMark(99)
            ->setComment('I am awesome')
            ->setReviewer($this->hodes)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setAllFiles(new Collection())
            ->setCreatedAt(new \Alaress_Date_Format('2016-07-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-07-12 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));
        $submission->getAllFeedback()->add($selfAssessedFeedbackForSubmission);
        // Must be added to both participant and response?
        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($selfAssessedFeedbackForSubmission);

        $this->assertSame($floatingSelfAssessedFeedback, $dueWork->getParticipant($this->hodes)->findMostRecentSelfAssessedFeedback());
        $this->assertSame($floatingSelfAssessedFeedback, $dueWork->getParticipant($this->hodes)->findMostRecentSelfAssessedPublishedFeedback());
        $this->assertEquals($selfAssessedFeedbackForSubmission, $submission->findMostRecentSelfAssessedFeedback());
        $this->assertNull($submission->findMostRecentSelfAssessedPublishedFeedback());

        // It must not affect the authoritative feedback, or its mark
        $this->assertSame($floatingFeedback, $dueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenFeedback());
        $this->assertEquals(-3, $dueWork->getParticipant($this->hodes)->getNormalisedMark(true));
        $this->assertEquals(37, $dueWork->getParticipant($this->hodes)->getNormalisedMark(false));

        // Make it more recent
        $selfAssessedFeedbackForSubmission
            ->setCreatedAt($selfAssessedFeedbackForSubmission->getCreatedAt()->addMonths(2))
            ->setUpdatedAt($selfAssessedFeedbackForSubmission->getUpdatedAt()->addMonths(2));

        $this->assertSame($selfAssessedFeedbackForSubmission, $dueWork->getParticipant($this->hodes)->findMostRecentSelfAssessedFeedback());
        $this->assertSame($floatingSelfAssessedFeedback, $dueWork->getParticipant($this->hodes)->findMostRecentSelfAssessedPublishedFeedback());
        $this->assertEquals($selfAssessedFeedbackForSubmission, $submission->findMostRecentSelfAssessedFeedback());
        $this->assertNull($submission->findMostRecentSelfAssessedPublishedFeedback());

        // Still must not affect the authoritative feedback, or its mark
        $this->assertSame($floatingFeedback, $dueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenFeedback());
        $this->assertEquals(-3, $dueWork->getParticipant($this->hodes)->getNormalisedMark(true));
        $this->assertEquals(37, $dueWork->getParticipant($this->hodes)->getNormalisedMark(false));


        // Publish the feedback for response
        // (Less recent than most recent self-assess for now)
        $selfAssessedFeedbackForSubmission
            ->setUpdatedAt($selfAssessedFeedbackForSubmission->getUpdatedAt()->addMonths(-4))
            ->setPublishedAt(new \Alaress_Date_Format('2016-07-12 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $this->assertSame($floatingSelfAssessedFeedback, $dueWork->getParticipant($this->hodes)->findMostRecentSelfAssessedFeedback());
        $this->assertSame($floatingSelfAssessedFeedback, $dueWork->getParticipant($this->hodes)->findMostRecentSelfAssessedPublishedFeedback());
        $this->assertEquals($selfAssessedFeedbackForSubmission, $submission->findMostRecentSelfAssessedFeedback());
        $this->assertEquals($selfAssessedFeedbackForSubmission, $submission->findMostRecentSelfAssessedFeedback());

        // Still must not affect the authoritative feedback, or its mark
        $this->assertSame($floatingFeedback, $dueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenFeedback());
        $this->assertEquals(-3, $dueWork->getParticipant($this->hodes)->getNormalisedMark(true));
        $this->assertEquals(37, $dueWork->getParticipant($this->hodes)->getNormalisedMark(false));

        // Make it more recent
        $selfAssessedFeedbackForSubmission
            ->setUpdatedAt($selfAssessedFeedbackForSubmission->getUpdatedAt()->addMonths(4));

        $this->assertSame($selfAssessedFeedbackForSubmission, $dueWork->getParticipant($this->hodes)->findMostRecentSelfAssessedFeedback());
        $this->assertSame($selfAssessedFeedbackForSubmission, $dueWork->getParticipant($this->hodes)->findMostRecentSelfAssessedPublishedFeedback());
        $this->assertEquals($selfAssessedFeedbackForSubmission, $submission->findMostRecentSelfAssessedFeedback());
        $this->assertEquals($selfAssessedFeedbackForSubmission, $submission->findMostRecentSelfAssessedFeedback());

        // Still must not affect the authoritative feedback, or its mark
        $this->assertSame($floatingFeedback, $dueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenFeedback());
        $this->assertEquals(-3, $dueWork->getParticipant($this->hodes)->getNormalisedMark(true));
        $this->assertEquals(37, $dueWork->getParticipant($this->hodes)->getNormalisedMark(false));
    }


    /**
     * Test most recent response retrieval, when responses are published on the
     * same date.
     */
    public function testFindMostRecentResponseWhenDatesAreIdentical(): void
    {
        // findMostRecentResponse
        // findMostRecentDraftResponse
        // findMostRecentSubmittedResponse
        $time      = \Alaress_Date_Format::newDatabaseDate('2020-07-01 00:00:00');
        $dueWork   = $this->spawnDueWork();
        $publishedResponse1 = new DueWorkSubmission();
        $publishedResponse1
            ->setId(1)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setSubmittedAt($time)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);

        $publishedResponse2 = new DueWorkSubmission();
        $publishedResponse2
            ->setId(2)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setSubmittedAt($time)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);

        $unpublishedResponse1 = new DueWorkSubmission();
        $unpublishedResponse1
            ->setId(3)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setSubmittedAt(null)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);

        $unpublishedResponse2 = new DueWorkSubmission();
        $unpublishedResponse2
            ->setId(4)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setSubmittedAt(null)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);


        // Test feedback belonging to a participant
        $participant = $dueWork->getParticipant($this->hodes);
        $participant->setAllResponses(
            $participant->getAllResponses()->emptyClone()
                        ->add($publishedResponse1)
                        ->add($publishedResponse2)
                        ->add($unpublishedResponse1)
                        ->add($unpublishedResponse2)
        );

        self::assertSame($publishedResponse2, $participant->findMostRecentSubmittedResponse());
        self::assertSame($unpublishedResponse2, $participant->findMostRecentResponse());
        self::assertSame($unpublishedResponse2, $participant->findMostRecentDraftResponse());
    }


    /**
     * Test most recent feedback retrieval, when feedbacks are published on the
     * same date.
     */
    public function testFindMostRecentFeedbackWhenDatesAreIdentical(): void
    {
        $time      = \Alaress_Date_Format::newDatabaseDate('2020-07-01 00:00:00');
        $dueWork  = $this->spawnDueWork();
        $response = new DueWorkSubmission();
        $response
            ->setId(1)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setSubmittedAt($time)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);

        // Feedback given on the same date:
        // disambiguate by ID (highest ID is most recent)
        $publishedFeedback1 = (new DueWorkFeedback())
            ->setId(1)
            ->setAssessable($dueWork)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt($time)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);
        $publishedFeedback2 = (new DueWorkFeedback())
            ->setId(2)
            ->setAssessable($dueWork)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt($time)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);

        $unpublishedFeedback1 = (new DueWorkFeedback())
            ->setId(3)
            ->setAssessable($dueWork)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(null)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);
        $unpublishedFeedback2 = (new DueWorkFeedback())
            ->setId(4)
            ->setAssessable($dueWork)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(null)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);

        $selfAssessedPublishedFeedback1 = (new DueWorkFeedback())
            ->setId(5)
            ->setAssessable($dueWork)
            ->setReviewer($this->hodes)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setPublishedAt($time)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);
        $selfAssessedPublishedFeedback2 = (new DueWorkFeedback())
            ->setId(6)
            ->setAssessable($dueWork)
            ->setReviewer($this->hodes)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setPublishedAt($time)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);

        $selfAssessedUnpublishedFeedback1 = (new DueWorkFeedback())
            ->setId(7)
            ->setAssessable($dueWork)
            ->setReviewer($this->hodes)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setPublishedAt(null)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);
        $selfAssessedUnpublishedFeedback2 = (new DueWorkFeedback())
            ->setId(8)
            ->setAssessable($dueWork)
            ->setReviewer($this->hodes)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setPublishedAt(null)
            ->setCreatedAt($time)
            ->setUpdatedAt($time);

        // Test feedback belonging to a response
        $response->setAllFeedback(
            $response->getAllFeedback()->emptyClone()
                       ->add($publishedFeedback1)
                       ->add($publishedFeedback2)
                       ->add($unpublishedFeedback1)
                       ->add($unpublishedFeedback2)
                       ->add($selfAssessedPublishedFeedback1)
                       ->add($selfAssessedPublishedFeedback2)
                       ->add($selfAssessedUnpublishedFeedback1)
                       ->add($selfAssessedUnpublishedFeedback2)
        );

        self::assertSame($publishedFeedback2, $response->findMostRecentInstructorGivenPublishedFeedback());
        self::assertSame($unpublishedFeedback2, $response->findMostRecentInstructorGivenFeedback());
        self::assertSame($selfAssessedPublishedFeedback2, $response->findMostRecentSelfAssessedPublishedFeedback());
        self::assertSame($selfAssessedUnpublishedFeedback2, $response->findMostRecentSelfAssessedFeedback());

        // Test feedback belonging to a participant
        $participant = $dueWork->getParticipant($this->hodes);
        $participant->setAllFeedbacks(
            $participant->getAllFeedbacks()->emptyClone()
                        ->add($publishedFeedback1)
                        ->add($publishedFeedback2)
                        ->add($unpublishedFeedback1)
                        ->add($unpublishedFeedback2)
                        ->add($selfAssessedPublishedFeedback1)
                        ->add($selfAssessedPublishedFeedback2)
                        ->add($selfAssessedUnpublishedFeedback1)
                        ->add($selfAssessedUnpublishedFeedback2)
        );

        self::assertSame($publishedFeedback2, $participant->findMostRecentInstructorGivenPublishedFeedback());
        self::assertSame($unpublishedFeedback2, $participant->findMostRecentInstructorGivenFeedback());
        self::assertSame($selfAssessedPublishedFeedback2, $participant->findMostRecentSelfAssessedPublishedFeedback());
        self::assertSame($selfAssessedUnpublishedFeedback2, $participant->findMostRecentSelfAssessedFeedback());
    }


    /**
     * Test that Due Work Feedback Marks are stored correctly
     *
     * @throws \UnexpectedValueException
     */
    public function testMostRecentPublishedFeedbacks()
    {
        $submissionFiles = [
            [
                'id' => 11,
                'hash' => 's89789d9hw3thsug8923u5',
                'filename' => 'mark this Sir.xlsx',
                'plagscan' => [
                    'id' => 1234,
                    'status' => 1,
                    'score' => '93% plagiariased',
                    'html' => '<div><strong>YOU CHEATED</strong></div>'
                ]
            ],
            [
                'id' => 23,
                'hash' => 'lk5j34kl5jousdio',
                'filename' => 'and this too.docx'
            ],
        ];

        $feedbackFiles = [
            [
                'id' => 112,
                'hash' => 'lj23lj2llj5l2jsdkjf90',
                'filename' => 'This is the correct response.txt',
            ],
        ];

        $dueWork = $this->spawnDueWork();

        $submission = new DueWorkSubmission();
        $submission
            ->setId(553)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setAllFiles((new Collection())
                ->add((new DueWorkSubmissionFile())
                    ->setId($submissionFiles[0]['id'])
                    ->setStorage((new Storage())
                        ->setHash($submissionFiles[0]['hash']))
                    ->setFilename($submissionFiles[0]['filename'])
                    ->setPlagscan((new DueWorkSubmissionFilePlagscan())
                        ->setId($submissionFiles[0]['plagscan']['id'])
                        ->setStatus($submissionFiles[0]['plagscan']['status'])
                        ->setScore($submissionFiles[0]['plagscan']['score'])))
                ->add((new DueWorkSubmissionFile())
                    ->setId($submissionFiles[1]['id'])
                    ->setStorage((new Storage())
                        ->setHash($submissionFiles[1]['hash']))
                    ->setFilename($submissionFiles[1]['filename'])))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(3385)
            ->setAssessable($dueWork)
            ->setNormalisedMark(-3)
            ->setComment('This is a flawed piece of work.')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles((new Collection())
                ->add((new DueWorkFeedbackFile())
                    ->setId($feedbackFiles[0]['id'])
                    ->setStorage((new Storage())
                        ->setHash($feedbackFiles[0]['hash']))
                    ->setFilename($feedbackFiles[0]['filename'])))
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
            ->setComment('the oldest published stuff')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
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
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 23:43:43', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 23:41:41', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 23:41:41', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4225)
            ->setAssessable($dueWork)
            ->setNormalisedMark(10)
            ->setComment('this is the second most recent')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 23:23:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 23:23:23', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 23:23:21', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        /* The most recently published feedback is 4236
         */
        $this->assertEquals(4236, $dueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenPublishedFeedback()->getId());

        /* The most recent feedback is also 4236
         */
        $this->assertEquals(4236, $dueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenFeedback()->getId());

        /* The mark for hodes should be 100
         */
        $this->assertEquals(100, $dueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenPublishedFeedback()->getNormalisedMark());
    }


    /**
     * What happens if you update an already-published feedback?
     *
     * @throws \UnexpectedValueException
     */
    public function testUpdateFeedbackAfterPublish()
    {
        $dueWork = $this->spawnDueWork();

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
            ->setComment('This is a flawed piece of work.')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
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
            ->setComment('The most recently published, but updated after publishing')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-10-06 22:22:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2017-08-12 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4236)
            ->setAssessable($dueWork)
            ->setNormalisedMark(100)
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 23:43:43', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 23:41:41', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 23:41:41', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4225)
            ->setAssessable($dueWork)
            ->setNormalisedMark(10)
            ->setComment('this is the second most recent')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 23:23:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 23:23:23', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 23:23:21', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($floatingFeedback);

        /* The most recently published feedback is 4223
         */
        $this->assertEquals(4223, $dueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenPublishedFeedback()->getId());

        /* The most recent feedback is also 4223
         */
        $this->assertEquals(4223, $dueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenFeedback()->getId());

        /* The mark for hodes should be 37
         */
        $this->assertEquals(37, $dueWork->getParticipant($this->hodes)->findMostRecentInstructorGivenPublishedFeedback()->getNormalisedMark());
    }


    /**
     * Test whether a learner can create a response when feedback is marked as
     * requiring resubmission.
     *
     * Only the due work accepts single case is tested:
     * * due work accepts none: learner can never respond
     * * due work accepts multiple: learner can always respond, as long as due
     *   work is open
     */
    public function testCanLearnerCreateResponseWhenFeedbackRequiresResubmission(): void
    {
        $dueWorkOpenDate  = \Alaress_Date_Format::now()->addDays(-1)->startOfDay();
        $dueWorkCloseDate = \Alaress_Date_Format::now()->addDays(1)->endOfDay();
        $dueWork          = $this->spawnDueWork()
                                 ->setAcceptsSingle()
                                 ->setContext(new FolderModel())
                                 ->setPublishAt($dueWorkOpenDate)
                                 ->setOpenAt($dueWorkOpenDate)
                                 ->setDueAt($dueWorkCloseDate)
                                 ->setCloseAt($dueWorkCloseDate);
        $feedbackDate = (clone $dueWorkOpenDate)->addHours(6);
        $feedback     = (new DueWorkFeedback())
            ->setId(3385)
            ->setAssessable($dueWork)
            ->setNormalisedMark(-3)
            ->setResubmissionRequired(true)
            ->setComment('This does not require resubmission')
            ->setReviewer($this->celia)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt($feedbackDate)
            ->setCreatedAt($feedbackDate)
            ->setUpdatedAt($feedbackDate);

        $dueWork->getParticipant($this->hodes)->getAllFeedbacks()->add($feedback);


        // Student hasn't submitted at all yet: learner must create response
        $this->assertTrue($dueWork->canLearnerCreateResponse($this->hodes));


        // Add a submission _before_ the feedback
        $submission1Date = (clone $feedbackDate)->addHours(-1);
        $submission1     = (new DueWorkSubmission())
            ->setId(553)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setSubmittedAt($submission1Date)
            ->setCreatedAt($submission1Date)
            ->setUpdatedAt($submission1Date);
        $dueWork->getParticipant($this->hodes)->getAllResponses()->add($submission1);


        // Submission, then feedback: learner must create response
        $this->assertTrue($dueWork->canLearnerCreateResponse($this->hodes));


        // Add a submission _after_ the feedback
        $submission2Date = (clone $feedbackDate)->addHours(1);
        $submission2 = (new DueWorkSubmission())
            ->setId(553)
            ->setAssessable($dueWork)
            ->setSubmittedBy($this->hodes)
            ->setSubmittedOnBehalfOf($this->hodes)
            ->setSubmittedAt($submission2Date)
            ->setCreatedAt($submission2Date)
            ->setUpdatedAt($submission2Date);
        $dueWork->getParticipant($this->hodes)->getAllResponses()->add($submission2);


        // Feedback, then submission: learner cannot create another response
        $this->assertFalse($dueWork->canLearnerCreateResponse($this->hodes));
    }
}
