<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Assessment;

use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Resource\Folder;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\Search\Index;

/**
 * Index builder for assessments.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * @inheritdoc
     */
    public function buildUpdates(Alaress_Date_Format $date): iterable
    {
        // TODO include other indices
        $query = $this->connector->prepareQuery("
            SELECT
                assessment.id,
                assessment.title,
                CONCAT(
                    '" . Hydrator::BASE_TYPE . ".',
                    COALESCE(
                        IF(task.id, '" . AssessmentType::TYPE_TASK . "', NULL),
                        IF(assess.assessment_id, '" . AssessmentType::TYPE_QUIZ . "', NULL),
                        IF(lti_assessment.assessment_id, '" . AssessmentType::TYPE_LTI_ASSESSMENT . "', NULL),
                        IF(submission_box.id, '" . AssessmentType::TYPE_DUE_WORK . "', NULL),
                        IF(project.id, '" . AssessmentType::TYPE_PROJECT . "', NULL),
                        IF(lesson_plan.id, '" . AssessmentType::TYPE_LESSON_PLAN . "', NULL)
                    )
                ) AS type,
                COALESCE(assessment.due_date, assessment.updated_at) AS date,
                NULL AS created_by,
                folder.id AS folder_id,
                assessment.description AS fulltext_index,
                assessment.title AS text_index
            FROM mv_assessment_relation
            JOIN assessment ON mv_assessment_relation.assessment_id = assessment.id
            JOIN folder ON (
                mv_assessment_relation.folder_id = folder.id
            )
            LEFT JOIN course_implementation ON (
                mv_assessment_relation.course_implementation_id = course_implementation.id
            )
            LEFT JOIN course_unit_implementation ON (
                mv_assessment_relation.course_unit_implementation_id = course_unit_implementation.id
            )
            LEFT JOIN course_specification ON (
                mv_assessment_relation.course_specification_id = course_specification.id
            )
            LEFT JOIN course_unit_specification ON (
                mv_assessment_relation.course_unit_specification_id = course_unit_specification.id
            )
            LEFT JOIN project ON (
                assessment.id=project.assessment_id
            )
            LEFT JOIN lti_assessment ON (
                assessment.id = lti_assessment.assessment_id
            )
            LEFT JOIN submission_box ON (
                assessment.id = submission_box.assessment_id
            )
            LEFT JOIN task ON (
                task.assessment_id = assessment.id
            )
            LEFT JOIN assess ON (
                assess.assessment_id = assessment.id
            )
            LEFT JOIN lesson_plan ON (
                lesson_plan.assessment_id = assessment.id
            )
            WHERE COALESCE(assessment.due_date, assessment.updated_at) >= :date
        ");
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        $query->execute($params);
        $this->logger->notice(sprintf('Building search indices for %d assessments', $query->rowCount()));
        foreach ($query as $row) {
            $identifier = (string)$row['id'];
            yield (new Index\Model())
                ->setIdentifier($identifier)
                ->setTitle($row['title'])
                ->setType($row['type'])
                ->setDate(Alaress_Date_Format::newDatabaseDate($row['date']))
                ->setIsRelevanceDateAffected(true)
                ->setCreatedBy($row['created_by'])
                ->setFolders(
                    (new GenericCollection(Folder\Model::class))
                        ->add((new Folder\Model())->setId($row['folder_id']))
                )
                ->setFulltextIndices(
                    (new GenericCollection(Index\Fulltext\Model::class))
                        ->add(
                            (new Index\Fulltext\Model())
                                ->setFulltextIndex(strip_tags($row['fulltext_index']))
                        )
                )
                ->setTextIndices(
                    (new GenericCollection(Index\Text\Model::class))
                        ->add(
                            (new Index\Text\Model())
                                ->setTextIndex($row['text_index'])
                        )
                );
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(Alaress_Date_Format $date): iterable
    {
        $query = "SELECT
            assessment.id
            FROM mv_assessment_relation
            JOIN assessment ON mv_assessment_relation.assessment_id = assessment.id
            WHERE assessment.deleted_at >= :date
        ";
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        return $this->connector->executeQuery($query, $params)->fetchColumn();
    }
}
