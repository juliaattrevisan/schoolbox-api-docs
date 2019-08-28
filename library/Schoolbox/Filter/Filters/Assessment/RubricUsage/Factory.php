<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\RubricUsage;

use InvalidArgumentException;
use ReflectionException;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Mapper as RubricCapabilityMapper;
use Schoolbox\Assessment\Rubric\DataModel\Indicator;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as RubricIndicatorMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractFactory;
use Schoolbox\Filter\IComponent;
use Schoolbox\Filter\IDefinition;
use Schoolbox\Filter\Page;
use Schoolbox\Filter\Selection;
use Schoolbox\GenericCollection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * A factory for building 'RubricUsage' filter objects
 *
 * The admin page has the following logical options:
 * □ (no rubric) - Assessment does not use rubric
 * □ List folder's capabilities
 * □ List folder's indicators
 *
 * When the page viewer selects a capability/indicator, the filtered assessment list will only contain assessments that use that capability/indicator.
 * The report will also only render that capability/indicator
 */
class Factory extends AbstractFactory
{

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var RosettaStone */
    private $rosettaStone;


    /**
     * @param Page\Option\Mapper          $optionMapper
     * @param Page\Option\Defaults\Mapper $defaultMapper
     * @param Selection\Mapper            $selectionMapper
     * @param Folder\Mapper               $folderMapper
     * @param RosettaStone                $rosettaStone
     */
    public function __construct(
        Page\Option\Mapper $optionMapper,
        Page\Option\Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        Folder\Mapper $folderMapper,
        RosettaStone $rosettaStone
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->folderMapper = $folderMapper;
        $this->rosettaStone = $rosettaStone;
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
            $this->getDefaultsMapper(),
            $this->rosettaStone
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
     * @throws \DomainException
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

        if ($viewee && $this->isInvolvedFolderPage($pageFilter->getPage())) {
            foreach ($this->folderMapper->findForUser($viewee) as $involvedFolder) {
                $folders[$involvedFolder->getId()] = $involvedFolder;
            }
        }

        $options          = $pageFilter->getOptions();

        // Need to check the option is relevant to the current page, before checking whether admin has configured it to be available.
        $showCapabilities = $options->keyExists(Definition::KEY_CAPABILITIES) && $options[Definition::KEY_CAPABILITIES]->getAvailable();
        $showIndicators   = $options->keyExists(Definition::KEY_INDICATORS) && $options[Definition::KEY_INDICATORS]->getAvailable();

        // If fetching both, just get indicators as they will have the capabilities attached
        // If fetching just capabilities, just get them

        if ($showCapabilities && !$showIndicators) {
            $capabilities = RubricCapabilityMapper::getInstance()->findForFolder($folders->getPropertyValues('id'));
        } elseif ($showIndicators) {
            $capabilities = RubricCapabilityMapper::getInstance()->createCollection();
            foreach (RubricIndicatorMapper::getInstance()->findForFolder($folders->getPropertyValues('id')) as $indicator) {
                $capability = $indicator->getCapability();
                if (!$capability->getIndicators()) {
                    $capability->setIndicators(new GenericCollection(Indicator\Model::class));
                }
                $capability->getIndicators()->setOffset($indicator->getId(), $indicator);
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
            new Codec,
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
        return new KeyConverter($filter, new Codec);
    }
}
