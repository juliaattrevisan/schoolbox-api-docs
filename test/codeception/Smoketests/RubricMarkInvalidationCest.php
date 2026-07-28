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
     * Tests that changing a descriptor value invalidates Reporting Activity rubric marks.
     *
     * @param IntegrationTester $i
     * @return void
     * @throws JsonException
     */
    public function invalidateReportingMarksAfterDescriptorValueChange(IntegrationTester $i): void
    {
        $timestamp = time();
        $folder = $this->createHomepage($i, "Testing rubric mark invalidation at $timestamp");
        $markTypeId = (int)$i->grabFromDatabase(
            'submission_mark_type',
            'mark_type_id',
            ['slug' => MarkType::SLUG_PERCENT],
        );
        $assessment = $this->createReportingActivity(
            $i,
            $folder,
            "Testing reporting rubric mark invalidation at $timestamp",
            $markTypeId,
        );
        $admin = $i->grabUser('alaress');
        $student = $i->grabUser('jreed');
        $reportingId = (int)$i->grabFromDatabase(
            'reporting',
            'id',
            ['assessment_id' => $assessment->getId()],
        );
        $date = date('Y-m-d H:i:s');
        $feedbackId = $i->haveInDatabase('reporting_feedback', [
            'reporting_id' => $reportingId,
            'learner_id' => $student->getId(),
            'instructor_id' => $admin->getId(),
            'published_at' => $date,
            'published_by' => $admin->getId(),
            'created_at' => $date,
            'updated_at' => $date,
        ]);
        $rubric = $this->createMarkedRubric($i, $folder, $assessment, $feedbackId);
        $payload = $rubric['payload'];
        $payload['descriptor'][$rubric['indicatorId']][1]['points'] = '2';

        $update = new Payload\Assessable\Update($assessment, '');
        $update->setFormParam('instructor-markType', $markTypeId);
        $update->setFormParam(
            "rubric[{$rubric['rubricInstanceId']}]",
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        $i->amGoingTo('Change the point value of a selected Reporting Activity rubric descriptor');
        $i->sendRequest($update);
        $i->seeResponseCodeIs(303);
        $i->seeInDatabase('rubric_capability_mark', [
            'id' => $rubric['capabilityMarkId'],
            'valid' => 0,
        ]);
        $i->seeInDatabase('rubric_indicator_mark', [
            'id' => $rubric['indicatorMarkId'],
            'valid' => 0,
        ]);
    }

    /**
     * Creates a homepage to contain the Reporting Activity.
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
     * Creates a Reporting Activity in the supplied homepage.
     *
     * @param IntegrationTester $i
     * @param Folder\Model      $folder
     * @param string            $title
     * @param int               $markTypeId
     * @return IAssessable
     * @throws JsonException
     */
    private function createReportingActivity(
        IntegrationTester $i,
        Folder\Model $folder,
        string $title,
        int $markTypeId,
    ): IAssessable {
        $payload = new Payload\Assessable\Create(
            AssessmentContextType::TYPE_FOLDER,
            $folder->getId(),
            $folder->getId(),
            AssessmentType::TYPE_REPORTING,
        );
        $payload->setTitle($title);
        $payload->setFormParam(
            'workType',
            $i->grabFromDatabase('lu_work_type', 'id', ['permitted_reporting_activity' => 1]),
        );
        $payload->setFormParam('instructor-markType', $markTypeId);

        $i->sendRequest($payload);
        $i->seeResponseCodeIs(303);

        return $i->grabFirstAssessmentByTitle($title);
    }

    /**
     * Creates a rubric with a selected four-point descriptor on Reporting feedback.
     *
     * @param IntegrationTester $i
     * @param Folder\Model      $folder
     * @param IAssessable       $assessment
     * @param int               $feedbackId
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
            'title' => 'Reporting rubric',
            'weight' => 100,
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
            'feedback_table' => 'reporting_feedback',
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
                'title' => 'Reporting rubric',
                'weight' => 100,
                'useMarkRange' => false,
                'capabilityMarkType' => MarkType::SLUG_NONE,
                'capabilityGradeGroup' => null,
            ],
        ];
    }
}
