<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\RubricUsage;

use InvalidArgumentException;
use ReflectionException;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Model as CapabilityModel;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\User;

use function array_merge;

/**
 * Class Component
 *
 * @package Schoolbox\Filter\Filters\Assessment\RubricUsage
 */
class Component extends Filter\BaseComponent
{

    /**
     * @var Codec
     */
    private $codec;


    /**
     * @var GenericCollection|CapabilityModel[]
     */
    private $capabilities;


    /**
     * @var bool
     */
    private $showCapabilities;


    /**
     * @var bool
     */
    private $showIndicators;


    /**
     * Formatted data to be injected into twig template.
     *
     * @var array
     */
    private $contextData;


    /**
     * @param Filter\IDefinition                              $definition
     * @param User                                            $user
     * @param Filter\Page\Model                               $pageFilter
     * @param null|GenericCollection|Filter\Selection\Model[] $userSelections
     * @param null|GenericCollection|Filter\Selection\Model[] $urlOverrides
     * @param Filter\Selection\Mapper                         $filterSelectionMapper
     * @param Codec                                           $codec
     * @param null|GenericCollection|CapabilityModel[]        $capabilities
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
        Codec $codec,
        GenericCollection $capabilities,
        bool $showCapabilities,
        bool $showIndicators
    ) {
        parent::__construct($definition, $user, $pageFilter, $userSelections, $urlOverrides, $filterSelectionMapper);

        if (!$capabilities->isCollectionOf(CapabilityModel::class)) {
            throw new InvalidArgumentException("Capability collection is of wrong type. Found: '{$capabilities->getElementClass()}'");
        }

        $this->codec            = $codec;
        $this->capabilities     = $capabilities;
        $this->showCapabilities = $showCapabilities;
        $this->showIndicators   = $showIndicators;
    }


    /**
     * @inheritdoc
     * @param GenericCollection|Filter\Page\Option\Model[] $abstractOptions
     * @throws InvalidArgumentException
     */
    protected function buildRoleSelections(GenericCollection $abstractOptions): GenericCollection
    {
        // These represent the set of options that will be rendered, and their default selected state
        $roleSelections = $this->getFilterSelectionMapper()->createCollection();

        // Had admin configured Capabilities and/or Indicators to be selected for current user's role type?
        $roleType           = $this->getUser()->getRole()->getType();
        $selectCapabilities = $this->showCapabilities && $abstractOptions[Definition::KEY_CAPABILITIES]->getDefaults()[$roleType]->getSelected();
        $selectIndicators   = $this->showIndicators && $abstractOptions[Definition::KEY_INDICATORS]->getDefaults()[$roleType]->getSelected();

        if (isset($abstractOptions[Definition::KEY_NONE])) {
            $noneSelected                         = $abstractOptions[Definition::KEY_NONE]->getDefaults()[$roleType]->getSelected();
            $roleSelections[Definition::KEY_NONE] = $this->createSelection(Definition::KEY_NONE, $noneSelected);
        }

        $data = [];

        foreach ($this->capabilities as $capability) {
            $data[$capability->getId()] = ['name' => $capability->getName()];
            if ($this->showCapabilities) {
                $key                  = $this->codec->encodeCapability($capability);
                $roleSelections[$key] = $this->createSelection($key, $selectCapabilities);
                $data[$capability->getId()]['key'] = $key;
            }
            if ($this->showIndicators) {
                $indicators = [];
                foreach ($capability->getIndicators() as $indicator) {
                    $key                  = $this->codec->encodeIndicator($indicator);
                    $roleSelections[$key] = $this->createSelection($key, $selectIndicators);
                    $indicators[$indicator->getId()] = ['key' => $key, 'name' => $indicator->getName()];
                }
                $data[$capability->getId()]['indicators'] = $indicators;
            }
        }
        $this->contextData = $data;

        return $roleSelections;
    }


    /**
     * @inheritdoc
     */
    public function toContext(): array
    {
        return array_merge(parent::toContext(), [
            'keyNone'          => DEFINITION::KEY_NONE,
            'capabilities'     => $this->contextData,
            'showCapabilities' => $this->showCapabilities,
            'showIndicators'   => $this->showIndicators,
        ]);
    }
}
