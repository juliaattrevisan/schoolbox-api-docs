<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Assessment;

use Alaress_Date_Format;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\Model;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\Search\Api\Database\Index\BuildType;

/**
 * Index builder for assessments.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * This builder works with assessment objects.
     *
     * @param object    $subject
     * @param BuildType $buildType
     * @return bool
     */
    protected function accepts(object $subject, BuildType $buildType): bool
    {
        return $subject instanceof Model;
    }

    /**
     * @param BuildType $buildType
     * @inheritdoc
     */
    protected function augmentQueryParams(object $subject, BuildType $buildType, string $baseQuery, array $baseParams): array
    {
        return [
            $baseQuery . ' AND assessment.id = :id',
            $baseParams + ['id' => $subject->getId()],
        ];
    }

    /**
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        // Need to query lesson plans separately
        $lessonPlanQuery = $this->connector->prepareQuery("
            SELECT title, body
            FROM lesson_plan_field
            INNER JOIN lesson_plan ON lesson_plan_field.lesson_plan_id = lesson_plan.id
            WHERE lesson_plan.assessment_id = :assessmentId
            AND lesson_plan_field.deleted_at IS NULL
        ");

        // Note: task link and LTI assessment URL are in the fulltext index,
        // because they won't fit in a standard indexed column.
        // May need to fall back to a non-indexed column?
        $queryString = "
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
                assessment.description,
                task.link AS task_link,
                lti_assessment.lti_assessment_url,
                lti_assessment.lti_assessment_title,
                lti_assessment.lti_assessment_text
            FROM mv_assessment_relation
            JOIN assessment ON mv_assessment_relation.assessment_id = assessment.id
            JOIN folder ON (
                mv_assessment_relation.folder_id = folder.id
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
            WHERE assessment.deleted_at IS NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND assessment.updated_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND assessment.updated_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            if (!$this->accepts($subject)) {
                return;
            }
            [$queryString, $params] = $this->augmentQueryParams($subject, $queryString, $queryString, $params);
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding assessments...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d assessments', $query->rowCount()));
        foreach ($query as $row) {
            // Somehow this row has no type?
            if (!isset($row['type'])) {
                continue;
            }
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['title'],
                $row['type'],
                $row['date'],
                true,
                $row['created_by'],
                $row['folder_id'],
            );
            $index = $this->addFulltextIndex($index, $row['description'], self::INDEX_STRATEGY_HTML);
            $index = $this->addFulltextIndex($index, $row['task_link'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['lti_assessment_url'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['lti_assessment_title'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['lti_assessment_text'], self::INDEX_STRATEGY_TEXT);

            // Build lesson plan indices as well
            $lessonPlanQuery->execute(['assessmentId' => $row['id']]);
            foreach ($lessonPlanQuery as $lessonPlanRow) {
                $index = $this->addFulltextIndex($index, $lessonPlanRow['title'], self::INDEX_STRATEGY_TEXT);
                $index = $this->addFulltextIndex($index, $lessonPlanRow['body'], self::INDEX_STRATEGY_HTML);
            }
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        $queryString = "
            SELECT
                assessment.id,
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
                ) AS type
            FROM mv_assessment_relation
            JOIN assessment ON mv_assessment_relation.assessment_id = assessment.id
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
            WHERE assessment.deleted_at IS NOT NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString        .= ' AND assessment.deleted_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString      .= ' AND assessment.deleted_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            if (!$this->accepts($subject)) {
                return;
            }
            [$queryString, $params] = $this->augmentQueryParams($subject, $queryString, $queryString, $params);
        }
        $query = $this->connector->prepareQuery($queryString);
        $query->execute($params);
        $this->logger->info(sprintf('Deleting search indices for %d assessments', $query->rowCount()));
        foreach ($query as $row) {
            // Somehow this row has no type?
            if (!isset($row['type'])) {
                continue;
            }
            yield $this->buildIndex(
                (string)$row['id'],
                null,
                $row['type'],
                null,
                true,
                null,
                null,
            );
        }
    }
}
