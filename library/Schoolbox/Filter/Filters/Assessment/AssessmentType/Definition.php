<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\AssessmentType;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
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
class Definition extends AbstractFilterDefinition
{
    const SLUG = 'a';

    const KEY_PROJECT  = AssessmentType::TYPE_PROJECT;
    const KEY_QUIZ     = AssessmentType::TYPE_QUIZ;
    const KEY_DUE_WORK = AssessmentType::TYPE_DUE_WORK;
    const KEY_TASK     = AssessmentType::TYPE_TASK;

    /** @var Collection|string[] */
    private $assessmentTypes;


    /**
     * @param Filter\Model    $filter
     * @param Option\Mapper   $filterOptionMapper
     * @param Defaults\Mapper $filterDefaultMapper
     * @param Collection      $assessmentTypes
     */
    public function __construct(
        Filter\Model $filter,
        Option\Mapper $filterOptionMapper,
        Defaults\Mapper $filterDefaultMapper,
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
            Option\Model::PAGE_PENDING_ASSESSMENTS,
            Option\Model::PAGE_GRADES,
            Option\Model::PAGE_MARK_BOOK,
            Option\Model::PAGE_DUE_WORK_CALENDAR,
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
    public function getInitialAvailability($customAbstractKey, $page)
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
    protected function insertDynamicOptions(GenericCollection $pageOptions, $page)
    {
        // noop - AssessmentTypes are purely hard-coded.
        return $pageOptions;
    }


    /**
     * @inheritdoc
     */
    protected function unsetInvalidOptions(GenericCollection $pageOptions)
    {
        foreach ($pageOptions as $idx => $option) {
            // Do not permit 'projects' on the 'pendingAssessments' page
            if (self::KEY_PROJECT === $option->getKey() && Option\Model::PAGE_PENDING_ASSESSMENTS === $option->getPage()) {
                $pageOptions->offsetUnset($idx);
            }
        }
        return $pageOptions;
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
    protected function getTemplatesDir()
    {
        return '@filterDefinitions/Assessment/AssessmentType/templates';
    }
}
