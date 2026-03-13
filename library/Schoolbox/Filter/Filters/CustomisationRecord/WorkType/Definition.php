<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord\WorkType;

use Schoolbox\Filter;
use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter\Page;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\WorkType;

use function array_map;
use function array_merge;

/**
 * Work Type filter definition for customisation records
 */
class Definition extends AbstractDefinition
{
    /**
     * @inheritdoc
     */
    protected const NAME = 'Work Type';

    /**
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param GenericCollection                  $workTypes WorkType\Model[] Indexed by workType.id
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        private GenericCollection $workTypes
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);

        $this->workTypes = $workTypes->shallowClone()
            ->indexByCallback(static function (WorkType\Model $workType): int {
                return $workType->getId();
            });
    }

    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [];
    }

    /**
     * Returns a string by which to identify this filter
     *
     * @return string
     */
    public static function getSlug(): string
    {
        return 'work_type';
    }

    /**
     * Return the name of the filter to expose in the UI
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'Work Type';
    }

    /**
     * Is this filter mandatory for the specified page?
     *
     * @param string $page
     * @return bool
     */
    public static function isMandatory(string $page): bool
    {
        return false;
    }

    /**
     * Returns the twig template to render this filter's panel component into
     *
     * @return string
     */
    public static function getPanelComponentTemplate(): string
    {
        return '@filterDefinitions/CustomisationRecord/WorkType/templates/component.twig';
    }

    /**
     * Prior to admin-configuration, should this filter be enabled on the specified page?
     *
     * @param string $page
     * @return bool
     */
    public static function getEnabledInitialState(string $page): bool
    {
        return true;
    }

    /**
     * Prior to admin-configuration, should this filter be user-configurable on the specified page?
     *
     * @param string $page
     * @return bool
     */
    public static function getUserConfigurableInitialState(string $page): bool
    {
        return true;
    }

    /**
     * This filter is available on the customisation record page.
     *
     * @param IConfigLoader $configLoader
     * @return array
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Page\Model::PAGE_CUSTOMISATION_RECORD,
        ];
    }

    /**
     * Return the set of possible keys for this filter's options
     *
     * @return string[]
     */
    public function getAbstractKeys(): array
    {
        // Ensure numeric ids are cast to string keys
        return array_merge($this->getCustomKeys(), array_map(static function (int $workTypeId) {
            return (string)$workTypeId;
        }, $this->workTypes->keys()));
    }

    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        return $this->workTypes[$key]->getName();
    }

    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(Filter\Page\Model $pageFilter): GenericCollection
    {
        $options = $pageFilter->getOptions();

        // Ensure it contains all WorkTypes
        foreach ($this->workTypes as $workType) {
            $abstractKey = (string)$workType->getId();

            if (!$options->offsetExists($abstractKey)) {
                $option = new Filter\Page\Option\Model();
                $option
                    ->setFilterPage($pageFilter)
                    ->setKey($abstractKey)
                    ->setAvailable($this->getInitialAvailability($abstractKey, $pageFilter))
                    ->setDefaults(new GenericCollection(Filter\Page\Option\Defaults\Model::class));
                $options->setOffset($option->getKey(), $option);
            }
        }
        return $options;
    }

    /**
     * @inheritdoc
     */
    public function getKey(): string
    {
        return 'work_type';
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): string
    {
        return 'Work Type';
    }

    /**
     * @inheritdoc
     */
    public function getKeyConverterClass(): string
    {
        return KeyConverter::class;
    }

    /**
     * @inheritdoc
     */
    public function getComponentClass(): string
    {
        return Component::class;
    }

    /**
     * @inheritdoc
     */
    public function getSequence(): int
    {
        return 100;
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/CustomisationRecord/WorkType/templates';
    }
}
