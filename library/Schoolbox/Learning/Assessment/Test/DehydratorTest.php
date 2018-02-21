<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Learning\Assessment\Dehydrator;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

/**
 * Class DehydratorTest
 */
class DehydratorTest extends TestCase
{
    /**
     * @var Dehydrator
     */
    private $dehydrator;

    /**
     * @var ParticipantLinker
     */
    private $linker;

    public function setUp()
    {
        $this->dehydrator = new Dehydrator();
        $this->linker     = new ParticipantLinker();
    }

    /**
     * Test dehydration of due-work specific fields
     */
    public function testDehydrateDueWork(): void
    {
        // Create everything
        $dueWork = (new DueWork())
            ->setId(3)
            ->setMaxMark(20)
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setTitle('Test Due Work')
            ->setMarkVisibility(DueWork::MARK_VISIBILITY_PARENT_STUDENT)
            // TODO move/copy this const to DueWork
            ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_NONE);

        // Don't bother testing the default fields, as they all match
        // I'm more interested in testing participants
        $expected = [
            'id'                => $dueWork->getId(),
            'type'              => $dueWork->getFamilyName(),
            'title'             => $dueWork->getTitle(),
            'weight'            => $dueWork->getWeight(),
            'markVisibility'    => $dueWork->getMarkVisibility(),
            'workType'          => null,
            'acceptOnline'      => $dueWork->getAcceptOnline(),
            'selfReviewability' => $dueWork->getSelfReviewability(),
            'participants'      => [],
        ];

        $actual = $this->dehydrator->dehydrate($dueWork);
        $this->assertEquals($expected, $actual);
    }


    /**
     * Test dehydration of participant-specific fields
     */
    public function testDehydrateParticipants(): void
    {
        $dueWork = (new DueWork())
            ->setDescription('This is a description of a test due work')
            ->setPublishAt(new \Alaress_Date_Format('2018-01-01 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new \Alaress_Date_Format('2018-01-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new \Alaress_Date_Format('2018-01-03 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new \Alaress_Date_Format('2018-01-04 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $dynamicPlan = $this->linker->createAndLinkPlan($dueWork, true)
                                    ->setName('Dynamic Plan');
        $participantA = $this->linker->createAndLinkParticipant(
            (new User())
                ->setId(1)
                ->setFirstname('Aaron')
                ->setLastname('Aaronson'),
            $dynamicPlan
        );
        $participantB = $this->linker->createAndLinkParticipant(
            (new User())
                ->setId(2)
                ->setFirstname('Barry')
                ->setLastname('Barryson'),
            $dynamicPlan
        );

        $customPlan = $this->linker->createAndLinkPlan($dueWork, false)
                                   ->setName('Custom Plan')
                                   ->setDescription('This is a custom description')
                                   ->setPublishDate(new \Alaress_Date_Format('2018-02-01 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
                                   ->setOpenDate(new \Alaress_Date_Format('2018-02-02 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
                                   ->setDueDate(new \Alaress_Date_Format('2018-02-03 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
                                   ->setCloseDate(new \Alaress_Date_Format('2018-02-04 00:00:00', \Alaress_Date_Format::DATABASE_FORMAT));
        $participantC = $this->linker->createAndLinkParticipant(
            (new User())
                ->setId(3)
                ->setFirstname('Custom')
                ->setLastname('Customson'),
            $customPlan
        );

        // Only care about participants here
        $expected = [
            'participants' => [
                $participantA->getUser()->getId() => [
                    'description' => $participantA->getDescription(),
                    'publishDate' => $participantA->getPublishDate()->getDatabase(),
                    'openDate'    => $participantA->getOpenDate()->getDatabase(),
                    'dueDate'     => $participantA->getDueDate()->getDatabase(),
                    'closeDate'   => $participantA->getCloseDate()->getDatabase(),
                    'excluded'    => $participantA->isExcluded(),
                    'plan'        => [
                        'id'      => $dynamicPlan->getId(),
                        'name'    => $dynamicPlan->getName(),
                        'dynamic' => $dynamicPlan->getIsDynamic(),
                    ],
                    'user'          => [
                        'id'        => $participantA->getUser()->getId(),
                        'title'     => $participantA->getUser()->getTitle(),
                        'firstname' => $participantA->getUser()->getFirstname(),
                        'lastname'  => $participantA->getUser()->getLastname(),
                    ]
                ],
                $participantB->getUser()->getId() => [
                    'description' => $participantB->getDescription(),
                    'publishDate' => $participantB->getPublishDate()->getDatabase(),
                    'openDate'    => $participantB->getOpenDate()->getDatabase(),
                    'dueDate'     => $participantB->getDueDate()->getDatabase(),
                    'closeDate'   => $participantB->getCloseDate()->getDatabase(),
                    'excluded'    => $participantB->isExcluded(),
                    'plan'        => [
                        'id'      => $dynamicPlan->getId(),
                        'name'    => $dynamicPlan->getName(),
                        'dynamic' => $dynamicPlan->getIsDynamic(),
                    ],
                    'user'          => [
                        'id'        => $participantB->getUser()->getId(),
                        'title'     => $participantB->getUser()->getTitle(),
                        'firstname' => $participantB->getUser()->getFirstname(),
                        'lastname'  => $participantB->getUser()->getLastname(),
                    ]
                ],
                $participantC->getUser()->getId() => [
                    'description' => $participantC->getDescription(),
                    'publishDate' => $participantC->getPublishDate()->getDatabase(),
                    'openDate'    => $participantC->getOpenDate()->getDatabase(),
                    'dueDate'     => $participantC->getDueDate()->getDatabase(),
                    'closeDate'   => $participantC->getCloseDate()->getDatabase(),
                    'excluded'    => $participantC->isExcluded(),
                    'plan'        => [
                        'id'      => $customPlan->getId(),
                        'name'    => $customPlan->getName(),
                        'dynamic' => $customPlan->getIsDynamic(),
                    ],
                    'user'          => [
                        'id'        => $participantC->getUser()->getId(),
                        'title'     => $participantC->getUser()->getTitle(),
                        'firstname' => $participantC->getUser()->getFirstname(),
                        'lastname'  => $participantC->getUser()->getLastname(),
                    ]
                ],
            ],
        ];

        $actual = $this->dehydrator->dehydrate($dueWork);
        $this->assertEquals(
            $expected['participants'][$participantA->getUser()->getId()],
            $actual['participants'][$participantA->getUser()->getId()]
        );
        $this->assertEquals(
            $expected['participants'][$participantB->getUser()->getId()],
            $actual['participants'][$participantB->getUser()->getId()]
        );
        $this->assertEquals(
            $expected['participants'][$participantC->getUser()->getId()],
            $actual['participants'][$participantC->getUser()->getId()]
        );
    }
}
