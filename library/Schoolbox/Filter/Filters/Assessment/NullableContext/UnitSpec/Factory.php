<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\UnitSpec;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractFactory;
use Schoolbox\Filter\IDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Filter\Selection;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;


/**
 * A factory for building 'UnitSpec' filter objects
 */
class Factory extends AbstractFactory
{
    /** @var UnitSpec\Mapper */
    private $unitSpecMapper;


    /**
     * @param Filter\Mapper     $filterMapper
     * @param Option\Mapper     $optionMapper
     * @param Defaults\Mapper   $defaultMapper
     * @param Selection\Mapper  $selectionMapper
     * @param UnitSpec\Mapper   $unitSpecMapper
     */
    public function __construct(
        Filter\Mapper $filterMapper,
        Option\Mapper $optionMapper,
        Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        UnitSpec\Mapper $unitSpecMapper
    ) {
        parent::__construct($filterMapper, $optionMapper, $defaultMapper, $selectionMapper);
        $this->unitSpecMapper   = $unitSpecMapper;
    }


    /**
     * @inheritdoc
     */
    protected function buildExtraComponentArgs(User $user, $page, IDefinition $definition, GenericCollection $overrides = null, User $viewee = null)
    {
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
