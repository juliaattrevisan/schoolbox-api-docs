<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\UnitImpl;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractFilterFactory;
use Schoolbox\Filter\IFilterDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Filter\Selection;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;


/**
 * A factory for building 'UnitImpl' filter objects
 */
class Factory extends AbstractFilterFactory
{
    /** @var UnitImpl\Mapper */
    private $unitImplMapper;


    /**
     * @param Filter\Mapper    $filterMapper
     * @param Option\Mapper    $optionMapper
     * @param Defaults\Mapper  $defaultMapper
     * @param Selection\Mapper $selectionMapper
     * @param UnitImpl\Mapper  $unitImplMapper
     */
    public function __construct(
        Filter\Mapper $filterMapper,
        Option\Mapper $optionMapper,
        Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        UnitImpl\Mapper $unitImplMapper
    ) {
        parent::__construct($filterMapper, $optionMapper, $defaultMapper, $selectionMapper);
        $this->unitImplMapper = $unitImplMapper;
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
        $unitImplIds = (new Collection($this->unitImplMapper->findAllIds(['deleted' => true])))->indexByCallback(function ($unitImplId) {
            return $unitImplId;
        });

        return array_merge(parent::buildKeyConverterArgs($filter), [
            'unitImplIds' => $unitImplIds,
        ]);
    }

}
