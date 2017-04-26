<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\AssessmentType;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFilterFactory;
use Schoolbox\Filter\IFilterDefinition;
use Schoolbox\User;
use Schoolbox\Filter;

/**
 * A factory for building 'AssessmentType' filter objects
 */
class Factory extends AbstractFilterFactory
{

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
    protected function buildExtraComponentArgs(User $user, $page, IFilterDefinition $definition, User $viewee = null)
    {
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
