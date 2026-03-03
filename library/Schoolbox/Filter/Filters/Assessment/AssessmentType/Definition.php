<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\AssessmentType;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\AssessmentType;

/**
 * Available options:
 * □ Project
 * □ Quiz
 * □ DueWork
 * □ Task
 *
 * Defaults to full set, but run-time options will enable controller to exclude individual items
 *
 */
class Definition extends Filter\AbstractDefinition
{
    // The name to expose in the UI
    protected const NAME = 'Activity Type';

    public const KEY_PROJECT = AssessmentType::TYPE_PROJECT;
    public const KEY_QUIZ = AssessmentType::TYPE_QUIZ;
    public const KEY_DUE_WORK = AssessmentType::TYPE_DUE_WORK;
    public const KEY_TASK = AssessmentType::TYPE_TASK;
    public const KEY_LTI_ASSESSMENT = AssessmentType::TYPE_LTI_ASSESSMENT;
    public const KEY_LESSON_PLAN = AssessmentType::TYPE_LESSON_PLAN;
    public const KEY_DIALOG_AGENT = AssessmentType::TYPE_DIALOG_AGENT;
    public const KEY_REPORTING = AssessmentType::TYPE_REPORTING;

    /** @var Collection|string[] */
    private $assessmentTypes;


    /**
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param Collection                         $assessmentTypes
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        Collection $assessmentTypes
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->assessmentTypes = $assessmentTypes->shallowClone();
    }


    /**
     * @inheritdoc
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Filter\Page\Model::PAGE_PENDING_ASSESSMENTS,
            Filter\Page\Model::PAGE_GRADES,
            Filter\Page\Model::PAGE_CLASS_MARK_BOOK,
            Filter\Page\Model::PAGE_COURSE_MARK_BOOK,
            Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS,
            Filter\Page\Model::PAGE_ASSESSMENT_CALENDAR,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        return AssessmentType::stringToReadableName($key);
    }

    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        // TODO this is a dynamic set of options: should this not be defined in `insertDynamicOptions`?
        return $this->assessmentTypes->getAll();
    }


    /**
     * @inheritdoc
     */
    protected function unsetInvalidOptions(Filter\Page\Model $pageFilter): GenericCollection
    {
        $cleanPageOptions = parent::unsetInvalidOptions($pageFilter);

        foreach ($cleanPageOptions as $idx => $option) {
            // Do not permit 'projects' on the 'pendingAssessments' page
            if (self::KEY_PROJECT === $option->getKey() && Filter\Page\Model::PAGE_PENDING_ASSESSMENTS === $pageFilter->getPage()) {
                $cleanPageOptions->offsetUnset($idx);
            }

            // Don't permit lesson plans on:
            // * any markbook
            // * grades
            // * pending assessments
            // This means lesson plans are permitted on:
            // * upcoming assessments
            // * assessment calendar
            if (
                self::KEY_LESSON_PLAN === $option->getKey()
                && in_array(
                    $pageFilter->getPage(),
                    [
                        Filter\Page\Model::PAGE_PENDING_ASSESSMENTS,
                        Filter\Page\Model::PAGE_GRADES,
                        Filter\Page\Model::PAGE_CLASS_MARK_BOOK,
                        Filter\Page\Model::PAGE_COURSE_MARK_BOOK,
                    ],
                    true,
                )
            ) {
                $cleanPageOptions->offsetUnset($idx);
            }
        }
        return $cleanPageOptions;
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/AssessmentType/templates';
    }
}
