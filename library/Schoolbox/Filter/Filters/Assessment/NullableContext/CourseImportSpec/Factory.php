<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\CourseImportSpec;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Filter\Filters\Assessment\NullableContext;

/**
 * A factory for building 'CourseSpec' filter objects
 */
class Factory extends NullableContext\Factory
{

    /** @var CourseSpec\Mapper */
    private $courseSpecMapper;


    /**
     * @param Filter\Page\Option\Mapper          $optionMapper
     * @param Filter\Page\Option\Defaults\Mapper $defaultMapper
     * @param Filter\Selection\Mapper            $selectionMapper
     * @param CourseSpec\Mapper                  $courseSpecMapper
     */
    public function __construct(
        Filter\Page\Option\Mapper $optionMapper,
        Filter\Page\Option\Defaults\Mapper $defaultMapper,
        Filter\Selection\Mapper $selectionMapper,
        CourseSpec\Mapper $courseSpecMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->courseSpecMapper = $courseSpecMapper;
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


//    /**
//     * @inheritDoc
//     * @return Filter\IComponent|Component
//     * @throws \InvalidArgumentException If neither $selections or $overrides are supplied
//     */
//    public function buildComponent(
//        Filter\IDefinition $definition,
//        User $user,
//        Filter\Page\Model $pageFilter,
//        ?GenericCollection $selections,
//        ?User $viewee,
//        array $routeArgs,
//        GenericCollection $overrides
//    ): Filter\IComponent {
//        // TODO inject any units that belong to folder in route args, depending on page
//        return new Component(
//            $definition,
//            $user,
//            $pageFilter,
//            $selections,
//            $overrides[$pageFilter->getFilter()->getId()],
//            $this->getSelectionMapper()
//        );
//    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|NullableContext\KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        $unitSpecIds = (new Collection($this->courseSpecMapper->findAllIds(['deleted' => true])))
            ->indexByCallback(static function ($unitSpecId) {
                return $unitSpecId;
            });
        return new NullableContext\KeyConverter($filter, $unitSpecIds);
    }
}
