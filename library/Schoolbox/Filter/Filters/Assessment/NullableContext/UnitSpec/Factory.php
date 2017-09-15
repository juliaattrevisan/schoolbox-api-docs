<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\UnitSpec;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Filter\Filters\Assessment\NullableContext;

/**
 * A factory for building 'UnitSpec' filter objects
 */
class Factory extends NullableContext\Factory
{

    /** @var UnitSpec\Mapper */
    private $unitSpecMapper;


    /**
     * @param Filter\Page\Option\Mapper          $optionMapper
     * @param Filter\Page\Option\Defaults\Mapper $defaultMapper
     * @param Filter\Selection\Mapper            $selectionMapper
     * @param UnitSpec\Mapper                    $unitSpecMapper
     */
    public function __construct(
        Filter\Page\Option\Mapper $optionMapper,
        Filter\Page\Option\Defaults\Mapper $defaultMapper,
        Filter\Selection\Mapper $selectionMapper,
        UnitSpec\Mapper $unitSpecMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->unitSpecMapper = $unitSpecMapper;
    }


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
            $this->getDefaultsMapper()
        );
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
        $unitSpecIds = (new Collection($this->unitSpecMapper->findAllIds(['deleted' => true])))->indexByCallback(function ($unitSpecId) {
            return $unitSpecId;
        });

        return array_merge(parent::buildKeyConverterArgs($filter), [
            'unitSpecIds' => $unitSpecIds,
        ]);
    }

}
