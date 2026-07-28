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
    private const string FEEDBACK_TABLE = 'submission_return';

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
     * Tests that an incompatible rubric edit invalidates marks only for the changed rubric instance.
     *
     * @param IntegrationTester $i
     * @return void
     * @throws JsonException
     */
    public function invalidateMarksOnlyForChangedRubricInstance(IntegrationTester $i): void
    {
        $timestamp = time();
        $folder = $this->createHomepage($i, "Testing rubric mark invalidation at {$timestamp}");
        $assessment = $this->createAssessment(
            $i,
            $folder,
            "Testing rubric instance mark invalidation at {$timestamp}",
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

        $definitions = $this->createCapabilityDefinitions($i, $folder);
        $changedRubric = $this->createRubric(
            $i,
            $assessment,
            'Changed rubric',
            $definitions,
            $feedbackId,
        );
        $unchangedRubric = $this->createRubric(
            $i,
            $assessment,
            'Unchanged rubric',
            $definitions,
            $feedbackId,
        );

        $i->seeInDatabase('rubric_capability_mark', [
            'id' => $changedRubric['capabilityMarkId'],
            'valid' => 1,
        ]);
        $i->seeInDatabase('rubric_indicator_mark', [
            'id' => $changedRubric['indicatorMarkId'],
            'valid' => 1,
        ]);
        $i->seeInDatabase('rubric_capability_mark', [
            'id' => $unchangedRubric['capabilityMarkId'],
            'valid' => 1,
        ]);
        $i->seeInDatabase('rubric_indicator_mark', [
            'id' => $unchangedRubric['indicatorMarkId'],
            'valid' => 1,
        ]);

        $changedPayload = $changedRubric['payload'];
        $removedCapability = array_pop($changedPayload['group']);
        $removedIndicatorId = $definitions[$removedCapability['id']]['indicatorId'];
        unset(
            $changedPayload['criterion'][$removedIndicatorId],
            $changedPayload['descriptor'][$removedIndicatorId],
        );

        $update = new Payload\Assessable\Update($assessment, '');
        $update->setFormParam(
            "rubric[{$changedRubric['rubricInstanceId']}]",
            json_encode($changedPayload, JSON_THROW_ON_ERROR),
        );
        $update->setFormParam(
            "rubric[{$unchangedRubric['rubricInstanceId']}]",
            json_encode($unchangedRubric['payload'], JSON_THROW_ON_ERROR),
        );

        $i->amGoingTo('Update one of two rubric grids with an incompatible structural change');
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
     * @param string $title
     * @return Folder\Model
     */
    private function createHomepage(IntegrationTester $i, string $title): Folder\Model
    {
        $payload = new Payload\Resource\Folder\Create();
        $payload->setTemplate(Payload\Resource\Folder\Create::TEMPLATE_BLANK);
        $payload->setName($title);

        $i->sendRequest($payload);
        $i->seeResponseCodeIs(303);

        return $i->grabFirstFolderByTitle($title);
    }

    /**
     * Creates a due work assessment in the supplied homepage.
     *
     * @param IntegrationTester $i
     * @param Folder\Model $folder
     * @param string $title
     * @return IAssessable
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
     * Creates two capabilities and their indicators for the rubric grids.
     *
     * @param IntegrationTester $i
     * @param Folder\Model $folder
     * @return array<int, array{capabilityName: string, indicatorId: int, indicatorName: string}>
     */
    private function createCapabilityDefinitions(IntegrationTester $i, Folder\Model $folder): array
    {
        $definitions = [];

        foreach (['Knowledge', 'Communication'] as $name) {
            $capabilityId = $i->haveInDatabase('rubric_capability', [
                'name' => $name,
                'parent_id' => $folder->getId(),
                'parent_type' => 'category',
            ]);
            $indicatorName = "{$name} indicator";
            $indicatorId = $i->haveInDatabase('rubric_indicator', [
                'rubric_capability_id' => $capabilityId,
                'name' => $indicatorName,
            ]);
            $definitions[$capabilityId] = [
                'capabilityName' => $name,
                'indicatorId' => $indicatorId,
                'indicatorName' => $indicatorName,
            ];
        }

        return $definitions;
    }

    /**
     * Creates a persisted rubric grid and a valid capability and indicator mark.
     *
     * @param IntegrationTester $i
     * @param IAssessable $assessment
     * @param string $title
     * @param array<int, array{capabilityName: string, indicatorId: int, indicatorName: string}> $definitions
     * @param int $feedbackId
     * @return array{
     *     rubricInstanceId: int,
     *     capabilityMarkId: int,
     *     indicatorMarkId: int,
     *     payload: array{
     *         level: list<array{id: int, name: string}>,
     *         group: list<array{id: int, name: string, maxValue: int}>,
     *         criterion: array<int, array{id: int, groupId: int, name: string, on: int, sequence: int}>,
     *         descriptor: array<int, list<array{id: int, text: string, points: string}>>,
     *         title: string,
     *         weight: int,
     *         useMarkRange: bool,
     *         capabilityMarkType: string,
     *         capabilityGradeGroup: null
     *     }
     * }
     */
    private function createRubric(
        IntegrationTester $i,
        IAssessable $assessment,
        string $title,
        array $definitions,
        int $feedbackId,
    ): array {
        $date = date('Y-m-d H:i:s');
        $rubricInstanceId = $i->haveInDatabase('rubric_instance', [
            'assessment_id' => $assessment->getId(),
            'title' => $title,
            'weight' => 50,
            'rubric_capability_mark_type_id' => MarkType::TYPE_NONE,
            'rubric_mark_range' => 0,
            'created_at' => $date,
            'updated_at' => $date,
        ]);
        $levels = [
            [
                'id' => $i->haveInDatabase('rubric_level', [
                    'rubric_instance_id' => $rubricInstanceId,
                    'name' => 'Developing',
                    'sequence' => 1,
                ]),
                'name' => 'Developing',
            ],
            [
                'id' => $i->haveInDatabase('rubric_level', [
                    'rubric_instance_id' => $rubricInstanceId,
                    'name' => 'Accomplished',
                    'sequence' => 2,
                ]),
                'name' => 'Accomplished',
            ],
        ];
        $groups = [];
        $criteria = [];
        $descriptors = [];

        foreach ($definitions as $capabilityId => $definition) {
            $i->haveInDatabase('rubric_capability_usage', [
                'rubric_capability_id' => $capabilityId,
                'rubric_instance_id' => $rubricInstanceId,
                'max_value' => 2,
                'sequence' => count($groups) + 1,
            ]);
            $i->haveInDatabase('rubric_indicator_usage', [
                'rubric_indicator_id' => $definition['indicatorId'],
                'rubric_instance_id' => $rubricInstanceId,
                'max_value' => 2,
                'sequence' => 1,
            ]);

            $groups[] = [
                'id' => $capabilityId,
                'name' => $definition['capabilityName'],
                'maxValue' => 2,
            ];
            $criteria[$definition['indicatorId']] = [
                'id' => $definition['indicatorId'],
                'groupId' => $capabilityId,
                'name' => $definition['indicatorName'],
                'on' => 1,
                'sequence' => 1,
            ];
            $descriptors[$definition['indicatorId']] = [
                [
                    'id' => $i->haveInDatabase('rubric_descriptor', [
                        'rubric_indicator_id' => $definition['indicatorId'],
                        'level_id' => $levels[0]['id'],
                        'max_value' => 1,
                        'description' => 'Developing',
                    ]),
                    'text' => 'Developing',
                    'points' => '1',
                ],
                [
                    'id' => $i->haveInDatabase('rubric_descriptor', [
                        'rubric_indicator_id' => $definition['indicatorId'],
                        'level_id' => $levels[1]['id'],
                        'max_value' => 2,
                        'description' => 'Accomplished',
                    ]),
                    'text' => 'Accomplished',
                    'points' => '2',
                ],
            ];
        }

        $firstCapabilityId = array_key_first($definitions);
        $firstIndicatorId = $definitions[$firstCapabilityId]['indicatorId'];
        $capabilityMarkId = $i->haveInDatabase('rubric_capability_mark', [
            'feedback_id' => $feedbackId,
            'feedback_table' => self::FEEDBACK_TABLE,
            'rubric_instance_id' => $rubricInstanceId,
            'rubric_capability_id' => $firstCapabilityId,
            'human_readable_value' => '2',
            'value' => 2,
            'valid' => 1,
        ]);
        $indicatorMarkId = $i->haveInDatabase('rubric_indicator_mark', [
            'rubric_capability_mark_id' => $capabilityMarkId,
            'descriptor_id' => $descriptors[$firstIndicatorId][1]['id'],
            'value' => 2,
            'valid' => 1,
        ]);

        return [
            'rubricInstanceId' => $rubricInstanceId,
            'capabilityMarkId' => $capabilityMarkId,
            'indicatorMarkId' => $indicatorMarkId,
            'payload' => [
                'level' => $levels,
                'group' => $groups,
                'criterion' => $criteria,
                'descriptor' => $descriptors,
                'title' => $title,
                'weight' => 50,
                'useMarkRange' => false,
                'capabilityMarkType' => MarkType::SLUG_NONE,
                'capabilityGradeGroup' => null,
            ],
        ];
    }
}
