<?php
/**
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\CapabilityIndicator;

use InvalidArgumentException;
use ReflectionException;
use Schoolbox\Filter\AbstractFactory;
use Schoolbox\Filter;
use Schoolbox\Filter\IComponent;
use Schoolbox\Filter\IDefinition;
use Schoolbox\Filter\Page;
use Schoolbox\Filter\Selection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use UnexpectedValueException;

/**
 * A factory for building 'CapabilityIndicatorUsage' filter objects
 *
 * The admin page has the following options:
 * □ Show folder's capabilities
 * □ Show folder's indicators
 * If enabled, either or both (but not neither) must be checked.
 *
 * These options will then cause the component to list the folder's capabilities and/or indicators.
 * Javascript will run to ensure that:
 *  * if a capability is selected, then all its indicators are selected.
 *  * if an indicator is selected, then its capability is selected
 *
 * When the page viewer selects a capability/indicator, the filtered assessment list will only contain assessments
 * that use that capability/indicator.
 * The report will also only render that capability/indicator
 *
 */
class Factory extends AbstractFactory
{

    /** @var Folder\Mapper */
    private $folderMapper;


    /**
     * @param Page\Option\Mapper          $optionMapper
     * @param Page\Option\Defaults\Mapper $defaultMapper
     * @param Selection\Mapper            $selectionMapper
     * @param Folder\Mapper               $folderMapper
     */
    public function __construct(
        Page\Option\Mapper $optionMapper,
        Page\Option\Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        Folder\Mapper $folderMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->folderMapper = $folderMapper;
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
     * @param IDefinition            $definition
     * @param User                   $user
     * @param Page\Model             $pageFilter
     * @param GenericCollection|null $selections
     * @param User|null              $viewee
     * @param array                  $routeArgs
     * @param GenericCollection      $overrides
     * @return IComponent
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    public function buildComponent(
        IDefinition $definition,
        User $user,
        Page\Model $pageFilter,
        ?GenericCollection $selections,
        ?User $viewee,
        array $routeArgs,
        GenericCollection $overrides
    ): IComponent {

        $folders = $this->folderMapper->createCollection();

        if ($this->isSingleFolderPage($pageFilter->getPage())) {
            $folder                    = $this->findFolderModel($routeArgs);
            $folders[$folder->getId()] = $folder;
        }

        $options          = $pageFilter->getOptions();
        $showCapabilities = $options[Definition::KEY_CAPABILITIES]->getAvailable();
        $showIndicators   = $options[Definition::KEY_INDICATORS]->getAvailable();

        // If fetching both, just get indicators as they will have the capabilities attached
        // If fetching just capabilities, just get them

        if ($showCapabilities && !$showIndicators) {
            $capabilities = Rubric\GroupMapper::getInstance()->findForFolder($folders->getPropertyValues('id'));
        } elseif ($showIndicators) {
            $capabilities = Rubric\GroupMapper::getInstance()->createCollection();
            foreach (Rubric\CriterionMapper::getInstance()->findForFolder($folders->getPropertyValues('id')) as $indicator) {
                $capability                                       = $indicator->getGroup();
                $capability->getIndicators()[$indicator->getId()] = $indicator;
                if (!$capabilities->offsetExists($capability->getId())) {
                    $capabilities[$capability->getId()] = $capability;
                }
            }
        } else {
            throw new UnexpectedValueException('Component must be configured to show capabilities, indicators, or both');
        }

        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides[$pageFilter->getFilter()->getId()],
            $this->getSelectionMapper(),
            $capabilities,
            $showCapabilities,
            $showIndicators
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        return new KeyConverter($filter);
    }
}
