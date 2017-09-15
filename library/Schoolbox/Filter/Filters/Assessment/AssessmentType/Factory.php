<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\AssessmentType;

use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\Filter;

/**
 * A factory for building 'AssessmentType' filter objects
 */
class Factory extends Filter\AbstractFactory
{

    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Definition|Filter\IDefinition
     */
    public function buildDefinition(Filter\Model $filter): Filter\IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper(),
            $this->getAssessmentTypes()
        );
    }

    /**
     * @inheritdoc
     */
    protected function buildDefinitionArgs(Filter\Model $filter)
    {
        return array_merge(parent::buildDefinitionArgs($filter), [
            'assessmentTypes' => $this->getAssessmentTypes(),
        ]);
    }


    /**
     * @inheritdoc
     */
    protected function buildExtraComponentArgs(
        User $user,
        Filter\Page\Model $pageFilter,
        Filter\IDefinition $definition,
        Collection $overrides,
        User $viewee = null
    ) {
        return [];
    }


    /**
     * @inheritdoc
     */
    protected function buildKeyConverterArgs(Filter\Model $filter)
    {
        return array_merge(parent::buildKeyConverterArgs($filter), [
            'assessmentTypes' => $this->getAssessmentTypes(),
        ]);
    }


    /**
     * @return Collection|string[]
     */
    private function getAssessmentTypes()
    {
        return new Collection([
            Definition::KEY_PROJECT,
            Definition::KEY_QUIZ,
            Definition::KEY_DUE_WORK,
            Definition::KEY_TASK,
        ]);
    }


}
