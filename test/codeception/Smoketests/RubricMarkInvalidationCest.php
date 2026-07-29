<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Smoketests;

use JsonException;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Learning\Assessment\AssessmentContextType;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Resource\Folder;
use Tests\Support\IntegrationTester;
use Tests\Support\Page\Duework\AssessmentForm;
use Tests\Support\Payload;

class RubricMarkInvalidationCest
{
    /**
     * Logs in as an administrator before each test.
     *
     * @param IntegrationTester $i
     * @return void
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    public function _before(IntegrationTester $i): void
    {
        $i->loginAdmin();
        $i->stopFollowingRedirects();
    }

    /**
     * Tests that changing a descriptor value invalidates marks only for the changed rubric.
     *
     * @param IntegrationTester $i
     * @return void
     * @throws JsonException
     */
    public function invalidateDueWorkMarksAfterDescriptorValueChange(IntegrationTester $i): void
    {
        $timestamp = time();
        $folder = $this->createHomepage($i, "Testing rubric mark invalidation at $timestamp");
        $assessment = $this->createAssessment(
            $i,
            $folder,
            "Testing rubric instance mark invalidation at $timestamp",
        );
        $admin = $i->grabUser('alaress');
        $student = $i->grabUser('jreed');
        $submissionBoxId = (int)$i->grabFromDatabase(
            'submission_box',
            'id',
            ['assessment_id' => $assessment->getId()],
        );
        $date = date('Y-m-d H:i:s');
        $feedbackId = $i->haveInDatabase('submission_return', [
            'box_id' => $submissionBoxId,
            'marker' => $admin->getId(),
            'owner' => $student->getId(),
            'review_route' => 'instructor',
            'created_at' => $date,
            'published_by' => $admin->getId(),
            'published_at' => $date,
            'updated_at' => $date,
        ]);
        $changedRubric = $this->createMarkedRubric(
            $i,
            $folder,
            $assessment,
            $feedbackId,
            'Changed rubric',
        );
        $unchangedRubric = $this->createMarkedRubric(
            $i,
            $folder,
            $assessment,
            $feedbackId,
            'Unchanged rubric',
        );
        $changedPayload = $changedRubric['payload'];
        $changedPayload['descriptor'][$changedRubric['indicatorId']][1]['points'] = '2';

        $update = new Payload\Assessable\Update($assessment, '');
        $update->setFormParam(
            "rubric[{$changedRubric['rubricInstanceId']}]",
            json_encode($changedPayload, JSON_THROW_ON_ERROR),
        );
        $update->setFormParam(
            "rubric[{$unchangedRubric['rubricInstanceId']}]",
            json_encode($unchangedRubric['payload'], JSON_THROW_ON_ERROR),
        );

        $i->amGoingTo('Change a descriptor value on one of two Due Work rubrics');
        $i->sendRequest($update);
        $i->seeResponseCodeIs(303);
        $i->seeInDatabase('rubric_capability_mark', [
            'id' => $changedRubric['capabilityMarkId'],
            'valid' => 0,
        ]);
        $i->seeInDatabase('rubric_indicator_mark', [
            'id' => $changedRubric['indicatorMarkId'],
            'valid' => 0,
        ]);
        $i->seeInDatabase('rubric_capability_mark', [
            'id' => $unchangedRubric['capabilityMarkId'],
            'valid' => 1,
        ]);
        $i->seeInDatabase('rubric_indicator_mark', [
            'id' => $unchangedRubric['indicatorMarkId'],
            'valid' => 1,
        ]);
    }

    /**
     * Creates a homepage to contain the assessment under test.
     *
     * @param IntegrationTester $i
     * @param string            $title
     * @return Folder\Model
     * @throws JsonException
     */
    private function createHomepage(IntegrationTester $i, string $title): Folder\Model
    {
        $payload = new Payload\Resource\Folder\Create();
        $payload->setTemplate(Payload\Resource\Folder\Modify::TEMPLATE_BLANK);
        $payload->setName($title);

        $i->sendRequest($payload);
        $i->seeResponseCodeIs(303);

        return $i->grabFirstFolderByTitle($title);
    }

    /**
     * Creates a Due Work assessment in the supplied homepage.
     *
     * @param IntegrationTester $i
     * @param Folder\Model      $folder
     * @param string            $title
     * @return IAssessable
     * @throws JsonException
     */
    private function createAssessment(
        IntegrationTester $i,
        Folder\Model $folder,
        string $title,
    ): IAssessable {
        $payload = new Payload\Assessable\Create(
            AssessmentContextType::TYPE_FOLDER,
            $folder->getId(),
            $folder->getId(),
            AssessmentType::TYPE_DUE_WORK,
        );
        $payload->setTitle($title);
        $payload->setFormParam('workType', AssessmentForm::WORKTYPE_ASSESSMENT_TASK);

        $i->sendRequest($payload);
        $i->seeResponseCodeIs(303);

        return $i->grabFirstAssessmentByTitle($title);
    }

    /**
     * Creates a rubric with a selected four-point descriptor on submission feedback.
     *
     * @param IntegrationTester $i
     * @param Folder\Model      $folder
     * @param IAssessable       $assessment
     * @param int               $feedbackId
     * @param string            $title
     * @return array{
     *     rubricInstanceId: int,
     *     indicatorId: int,
     *     capabilityMarkId: int,
     *     indicatorMarkId: int,
     *     payload: array
     * }
     */
    private function createMarkedRubric(
        IntegrationTester $i,
        Folder\Model $folder,
        IAssessable $assessment,
        int $feedbackId,
        string $title,
    ): array {
        $capabilityId = $i->haveInDatabase('rubric_capability', [
            'name' => 'Achievement',
            'parent_id' => $folder->getId(),
            'parent_type' => 'category',
        ]);
        $indicatorId = $i->haveInDatabase('rubric_indicator', [
            'rubric_capability_id' => $capabilityId,
            'name' => 'Achievement indicator',
        ]);
        $date = date('Y-m-d H:i:s');
        $rubricInstanceId = $i->haveInDatabase('rubric_instance', [
            'assessment_id' => $assessment->getId(),
            'title' => $title,
            'weight' => 50,
            'rubric_capability_mark_type_id' => (int)$i->grabFromDatabase(
                'submission_mark_type',
                'mark_type_id',
                ['slug' => MarkType::SLUG_NONE],
            ),
            'rubric_mark_range' => 0,
            'created_at' => $date,
            'updated_at' => $date,
        ]);
        $levels = [
            [
                'id' => $i->haveInDatabase('rubric_level', [
                    'rubric_instance_id' => $rubricInstanceId,
                    'name' => 'Not met',
                    'sequence' => 1,
                ]),
                'name' => 'Not met',
            ],
            [
                'id' => $i->haveInDatabase('rubric_level', [
                    'rubric_instance_id' => $rubricInstanceId,
                    'name' => 'Met',
                    'sequence' => 2,
                ]),
                'name' => 'Met',
            ],
        ];
        $i->haveInDatabase('rubric_capability_usage', [
            'rubric_capability_id' => $capabilityId,
            'rubric_instance_id' => $rubricInstanceId,
            'max_value' => 4,
            'sequence' => 1,
        ]);
        $i->haveInDatabase('rubric_indicator_usage', [
            'rubric_indicator_id' => $indicatorId,
            'rubric_instance_id' => $rubricInstanceId,
            'max_value' => 4,
            'sequence' => 1,
        ]);
        $descriptors = [
            [
                'id' => $i->haveInDatabase('rubric_descriptor', [
                    'rubric_indicator_id' => $indicatorId,
                    'level_id' => $levels[0]['id'],
                    'max_value' => 0,
                    'description' => 'Not met',
                ]),
                'text' => 'Not met',
                'points' => '0',
            ],
            [
                'id' => $i->haveInDatabase('rubric_descriptor', [
                    'rubric_indicator_id' => $indicatorId,
                    'level_id' => $levels[1]['id'],
                    'max_value' => 4,
                    'description' => 'Met',
                ]),
                'text' => 'Met',
                'points' => '4',
            ],
        ];
        $capabilityMarkId = $i->haveInDatabase('rubric_capability_mark', [
            'feedback_id' => $feedbackId,
            'feedback_table' => 'submission_return',
            'rubric_instance_id' => $rubricInstanceId,
            'rubric_capability_id' => $capabilityId,
            'human_readable_value' => '4',
            'value' => 4,
            'valid' => 1,
        ]);
        $indicatorMarkId = $i->haveInDatabase('rubric_indicator_mark', [
            'rubric_capability_mark_id' => $capabilityMarkId,
            'descriptor_id' => $descriptors[1]['id'],
            'value' => 4,
            'valid' => 1,
        ]);

        return [
            'rubricInstanceId' => $rubricInstanceId,
            'indicatorId' => $indicatorId,
            'capabilityMarkId' => $capabilityMarkId,
            'indicatorMarkId' => $indicatorMarkId,
            'payload' => [
                'level' => $levels,
                'group' => [[
                    'id' => $capabilityId,
                    'name' => 'Achievement',
                    'maxValue' => 4,
                ]],
                'criterion' => [
                    $indicatorId => [
                        'id' => $indicatorId,
                        'groupId' => $capabilityId,
                        'name' => 'Achievement indicator',
                        'on' => 1,
                        'sequence' => 1,
                    ],
                ],
                'descriptor' => [$indicatorId => $descriptors],
                'title' => $title,
                'weight' => 50,
                'useMarkRange' => false,
                'capabilityMarkType' => MarkType::SLUG_NONE,
                'capabilityGradeGroup' => null,
            ],
        ];
    }
}
