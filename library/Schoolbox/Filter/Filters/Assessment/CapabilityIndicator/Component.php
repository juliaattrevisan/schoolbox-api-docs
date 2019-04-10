<?php
/**
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\CapabilityIndicator;

use function array_merge;
use InvalidArgumentException;
use ReflectionException;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Group;

class Component extends Filter\BaseComponent
{

    /** @var GenericCollection|Group[] */
    private $capabilities;

    /** @var bool */
    private $showCapabilities;

    /** @var bool */
    private $showIndicators;


    /**
     * @param Filter\IDefinition                              $definition
     * @param User                                            $user
     * @param Filter\Page\Model                               $pageFilter
     * @param null|GenericCollection|Filter\Selection\Model[] $userSelections
     * @param null|GenericCollection|Filter\Selection\Model[] $urlOverrides
     * @param Filter\Selection\Mapper                         $filterSelectionMapper
     * @param null|GenericCollection|Group[]                  $capabilities
     * @param bool                                            $showCapabilities
     * @param bool                                            $showIndicators
     * @throws InvalidArgumentException If neither $capabilities or $indicators are supplied
     * @throws ReflectionException
     */
    public function __construct(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $userSelections,
        ?GenericCollection $urlOverrides,
        Filter\Selection\Mapper $filterSelectionMapper,
        GenericCollection $capabilities,
        bool $showCapabilities,
        bool $showIndicators
    ) {
        parent::__construct($definition, $user, $pageFilter, $userSelections, $urlOverrides, $filterSelectionMapper);
        if (!$showCapabilities && !$showIndicators) {
            throw new InvalidArgumentException('Either capabilities or indicators or both must be configured to be available');
        }

        if (!$capabilities->isCollectionOf(Group::class)) {
            throw new InvalidArgumentException("Capability collection is of wrong type. Found: '{$capabilities->getElementClass()}'");
        }

        $this->capabilities     = $capabilities;
        $this->showCapabilities = $showCapabilities;
        $this->showIndicators   = $showIndicators;
    }


    /**
     * @inheritdoc
     * @param GenericCollection|Filter\Page\Option\Model[] $abstractOptions
     */
    protected function buildRoleSelections(GenericCollection $abstractOptions): GenericCollection
    {
        // These represent the set of options that will be rendered, and their default selected state
        $roleSelections = $this->getFilterSelectionMapper()->createCollection();

        // Had admin configured Capabilities and/or Indicators to be selected for current user's role type?
        $roleType           = $this->getUser()->getRole()->getType();
        $selectCapabilities = $abstractOptions[Definition::KEY_CAPABILITIES]->getDefaults()[$roleType]->getSelected();
        $selectIndicators   = $abstractOptions[Definition::KEY_INDICATORS]->getDefaults()[$roleType]->getSelected();

        foreach ($this->capabilities as $capability) {
            if ($this->showCapabilities) {
                $key                  = "c{$capability->getId()}";
                $roleSelections[$key] = $this->createSelection($key, $selectCapabilities);
            }
            if ($this->showIndicators) {
                foreach ($capability->getIndicators() as $indicator) {
                    $key                  = "i{$indicator->getId()}";
                    $roleSelections[$key] = $this->createSelection($key, $selectIndicators);
                }
            }
        }

        return $roleSelections;
    }


    /**
     * @inheritdoc
     */
    public function toContext(): array
    {
        return array_merge(parent::toContext(), [
            'capabilities'     => $this->capabilities,
            'showCapabilities' => $this->showCapabilities,
            'showIndicators'   => $this->showIndicators,
        ]);
    }
}
