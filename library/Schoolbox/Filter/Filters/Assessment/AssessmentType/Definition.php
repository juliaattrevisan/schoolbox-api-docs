<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\AssessmentType;

use Schoolbox\Collection;
use Schoolbox\Filter;
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
    const NAME = 'Activity Type';

    const KEY_PROJECT = AssessmentType::TYPE_PROJECT;
    const KEY_QUIZ = AssessmentType::TYPE_QUIZ;
    const KEY_DUE_WORK = AssessmentType::TYPE_DUE_WORK;
    const KEY_TASK = AssessmentType::TYPE_TASK;

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
    public static function getPages()
    {
        return [
            Filter\Page\Model::PAGE_PENDING_ASSESSMENTS,
            Filter\Page\Model::PAGE_GRADES,
            Filter\Page\Model::PAGE_MARK_BOOK,
//            Filter\Page\Model::PAGE_DUE_WORK_CALENDAR,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel($key)
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
        throw new \InvalidArgumentException("Unknown AssessmentType key: $key");
    }


    /**
     * @inheritdoc
     */
    public function getInitialAvailability($customAbstractKey, Filter\Page\Model $pageFilter)
    {
        // by default, all AssessmentTypes are available
        return true;
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys()
    {
        return $this->assessmentTypes->getAll();
    }


    /**
     * @inheritdoc
     */
    protected function unsetInvalidOptions(Filter\Page\Model $pageFilter)
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
    protected function getInitialSelectedState($key, $page, $roleType)
    {
        // No options checked
        return false;
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir()
    {
        return '@filterDefinitions/Assessment/AssessmentType/templates';
    }
}
