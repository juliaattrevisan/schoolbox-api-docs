<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\Category;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter\Model;
use Schoolbox\Filter\Page;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Badge\Category\Model as BadgeCategoryModel;

/**
 * Definition for badge category filters.
 */
class Definition extends AbstractDefinition
{
    protected const NAME = 'Category';


    /**
     * A list of categories to display in the filter.
     *
     * @var GenericCollection|BadgeCategoryModel[]
     */
    private GenericCollection $categories;


    /**
     * Constructor
     *
     * @param Model                       $filter
     * @param Page\Option\Mapper          $filterOptionMapper
     * @param Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param GenericCollection           $categories
     */
    public function __construct(
        Model $filter,
        Page\Option\Mapper $filterOptionMapper,
        Page\Option\Defaults\Mapper $filterDefaultMapper,
        GenericCollection $categories
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->categories = $categories;
    }


    /**
     * This filter has no custom keys, so always returns empty.
     *
     * @return array
     */
    protected function getCustomKeys(): array
    {
        return [];
    }


    /**
     * This filter is available on the badge list page.
     *
     * @param IConfigLoader $configLoader
     * @return array
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Page\Model::PAGE_BADGE_LIST,
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
        return array_map(
            static function (int $key) {
                return (string)$key;
            },
            $this->categories->keys()
        );
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        return $this->categories[$key]->getName();
    }


    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(Page\Model $pageFilter): GenericCollection
    {
        $options = parent::insertDynamicOptions($pageFilter);

        // Ensure it contains all categories
        foreach ($this->categories as $category) {
            $abstractKey = (string)$category->getId();

            if (!$options->offsetExists($abstractKey)) {
                $option = new Page\Option\Model();
                $option
                    ->setFilterPage($pageFilter)
                    ->setKey($abstractKey)
                    ->setAvailable($this->getInitialAvailability($abstractKey, $pageFilter))
                    ->setDefaults(new GenericCollection(Page\Option\Defaults\Model::class));
                $options->setOffset($option->getKey(), $option);
            }
        }
        return $options;
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Badge/Category/templates';
    }
}
