<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Event\EventType;

use Schoolbox\Collection;
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
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        return new KeyConverter($filter, $this->getAssessmentTypes());
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
