<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\CourseImportSpec;

use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\User;

/**
 * @method Definition getDefinition()
 */
class Component extends Filter\BaseComponent
{
    /**
     * Course specs that have been imported somewhere imported to folders
     * (we need this because injected folders may be neither classes or groups)
     *
     * @var GenericCollection|UnitSpec\Model[]
     */
    private $importedCourseSpecs;


    /**
     * Note that it is possible to inject a folder that is neither a class not group.
     * In this case, it will most likely clobber user-selections, so don't select the units
     * imported to that injected folder so the unit filter can be nulled.
     *
     * @param Filter\IDefinition                              $definition
     * @param User                                            $user
     * @param Filter\Page\Model                               $pageFilter
     * @param null|GenericCollection|Filter\Selection\Model[] $userSelections
     * @param null|GenericCollection|Filter\Selection\Model[] $urlOverrides
     * @param Filter\Selection\Mapper                         $filterSelectionMapper
     * @param GenericCollection|UnitSpec\Model[]              $importedCourseSpecs
     * @throws \InvalidArgumentException If neither $userSelections or $urlOverrides are supplied
     */
    public function __construct(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $userSelections,
        ?GenericCollection $urlOverrides,
        Filter\Selection\Mapper $filterSelectionMapper,
        GenericCollection $importedCourseSpecs
    ) {
        parent::__construct($definition, $user, $pageFilter, $userSelections, $urlOverrides, $filterSelectionMapper);
        $this->importedCourseSpecs = $importedCourseSpecs;
    }


    /**
     * @inheritdoc
     * @param GenericCollection|Filter\Page\Option\Model[] $abstractOptions $optionDefaults
     * @throws \InvalidArgumentException If option key unrecognised
     */
    protected function buildRoleSelections(GenericCollection $abstractOptions)
    {
        $availableSelections = $this->getFilterSelectionMapper()->createCollection();
        $roleType            = $this->getUser()->getRole()->getType();

        // Piece the selections together
        $selectNoneOption       = $abstractOptions[Definition::KEY_NONE]->getDefaults()[$roleType]->getSelected();
        $selectAllCourseOptions = $abstractOptions[Definition::KEY_READ_ACCESS]->getDefaults()[$roleType]->getSelected();
        $urlOverrides           = $this->getUrlOverrides();

        $availableSelections[Definition::KEY_NONE] = $this->createSelection(
            Definition::KEY_NONE,
            $selectNoneOption || ($urlOverrides && $urlOverrides->offsetExists(Definition::KEY_NONE))
        );

        foreach ($this->importedCourseSpecs as $courseSpecId => $courseSpec) {
            $availableSelections[$courseSpecId] = $this->createSelection(
                $courseSpecId,
                $selectAllCourseOptions || ($urlOverrides && $urlOverrides->offsetExists($courseSpecId))
            );
        }
        return $availableSelections;
    }


    /**
     * @inheritdoc
     */
    public function toContext()
    {
        return array_merge(parent::toContext(), [
            'courseSpecs'     => $this->importedCourseSpecs,
            'keyNone'         => Definition::KEY_NONE,
        ]);
    }
}
