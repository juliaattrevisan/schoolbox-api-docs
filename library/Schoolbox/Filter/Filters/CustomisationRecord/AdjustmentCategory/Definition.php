<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord\AdjustmentCategory;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\DifferentiationProfile\AdjustmentCategories\Model as AdjustmentCategory;

/**
 * Definition for the adjustment category filter.
 */
class Definition extends AbstractDefinition
{
    public const KEY_NONE = 'none';

    /**
     * @inheritdoc
     */
    protected const NAME = 'Adjustment Category';


    /** @var GenericCollection|AdjustmentCategory[] */
    private $adjustmentCategories;


    /**
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param GenericCollection|AdjustmentCategory[]      $adjustmentCategories
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        GenericCollection $adjustmentCategories
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->adjustmentCategories = $adjustmentCategories->shallowClone()->indexByCallback(
            static function (AdjustmentCategory $adjustmentCategory) {
                return $adjustmentCategory->getId();
            }
        );
    }


    /**
     *
     * @param IConfigLoader $configLoader
     * @return array
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Filter\Page\Model::PAGE_CUSTOMISATION_RECORD,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        if ($key === self::KEY_NONE) {
            return 'None';
        }
        if ($this->adjustmentCategories[$key]) {
            return $this->adjustmentCategories[$key]->getName();
        }
        return '';
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [self::KEY_NONE];
    }


    /**
     * Return the set of possible keys for this filter's options
     *
     * @return string[]
     */
    public function getAbstractKeys(): array
    {
        // Ensure numeric ids are cast to string keys
        return array_merge(
            $this->getCustomKeys(),
            array_map(
                static function (int $adjustmentCategoryId) {
                    return (string)$adjustmentCategoryId;
                },
                $this->adjustmentCategories->keys()
            )
        );
    }


    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(Filter\Page\Model $pageFilter): GenericCollection
    {
        $options = $pageFilter->getOptions();

        // Ensure it contains all Adjustment Categories
        foreach ($this->adjustmentCategories as $adjustmentCategory) {
            $abstractKey = (string)$adjustmentCategory->getId();
            if (!$options->offsetExists($abstractKey)) {
                $option = $this->getFilterOptionMapper()->createModel()
                    ->setFilterPage($pageFilter)
                    ->setKey($abstractKey)
                    ->setAvailable($this->getInitialAvailability($abstractKey, $pageFilter))
                    ->setDefaults($this->getFilterDefaultMapper()->createCollection());
                $options->setOffset($abstractKey, $option);
            }
        }
        return $options;
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/CustomisationRecord/AdjustmentCategory/templates';
    }
}
