<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\UnitSpec;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
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
     * @param Filter\IDefinition     $definition
     * @param User                   $user
     * @param Filter\Page\Model      $pageFilter
     * @param GenericCollection      $selections
     * @param null|User              $viewee
     * @param null|GenericCollection $overrides
     * @return Filter\IComponent|Component
     */
    public function buildComponent(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        GenericCollection $selections,
        ?User $viewee,
        ?GenericCollection $overrides
    ): Filter\IComponent {
        return new Component($definition, $user, $pageFilter, $overrides ?: $selections, $this->getSelectionMapper());
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        $unitSpecIds = (new Collection($this->unitSpecMapper->findAllIds(['deleted' => true])))
            ->indexByCallback(function ($unitSpecId) {
                return $unitSpecId;
            });
        return new KeyConverter($filter, $unitSpecIds);
    }
}
