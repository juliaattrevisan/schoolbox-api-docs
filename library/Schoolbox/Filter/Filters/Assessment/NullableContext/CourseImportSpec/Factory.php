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
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
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
     * @var Access
     */
    private $access;


    /**
     * Constructor
     *
     * @param Filter\Page\Option\Mapper          $optionMapper
     * @param Filter\Page\Option\Defaults\Mapper $defaultMapper
     * @param Filter\Selection\Mapper            $selectionMapper
     * @param CourseSpec\Mapper                  $courseSpecMapper
     * @param Access                             $access
     */
    public function __construct(
        Filter\Page\Option\Mapper $optionMapper,
        Filter\Page\Option\Defaults\Mapper $defaultMapper,
        Filter\Selection\Mapper $selectionMapper,
        CourseSpec\Mapper $courseSpecMapper,
        Access $access
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->courseSpecMapper = $courseSpecMapper;
        $this->access           = $access;
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
     * @inheritDoc
     * @return Filter\IComponent|Component
     * @throws \InvalidArgumentException If neither $selections or $overrides are supplied
     */
    public function buildComponent(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $selections,
        ?User $viewee,
        array $routeArgs,
        GenericCollection $overrides
    ): Filter\IComponent {
        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides[$pageFilter->getFilter()->getId()],
            $this->getSelectionMapper(),
            $this->courseSpecMapper
                ->findAll()
                ->filter(function (CourseSpec\Model $courseSpec) use ($viewee) {
                    return $this->access->getFolderAccess($viewee, $courseSpec->getFolder()->getId()) >= UserAccess::ACCESS_READ;
                })
                ->sortByCallback(
                    static function (CourseSpec\Model $a, CourseSpec\Model $b) {
                        return strnatcasecmp($a->getTitle(), $b->getTitle());
                    },
                    true
                )
        );
    }


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
        return new KeyConverter($filter, $unitSpecIds);
    }
}
