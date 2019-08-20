<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\AssessmentType;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
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
    public static function getPages(): array
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
        if (self::KEY_PROJECT === $key) {
            return 'Project';
        }
        if (self::KEY_QUIZ === $key) {
            return 'Quiz';
        }
        if (self::KEY_DUE_WORK === $key) {
            return 'Due Work';
        }
        if (self::KEY_TASK) {
            return 'Task';
        }
        throw new InvalidArgumentException("Unknown AssessmentType key: $key");
    }

    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
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
