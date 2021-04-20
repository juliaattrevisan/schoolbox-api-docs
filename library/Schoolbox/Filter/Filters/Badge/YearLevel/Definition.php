<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\YearLevel;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\YearLevel\Model as YearLevel;

/**
 * Definition for the badge year level filter.
 */
class Definition extends AbstractDefinition
{
    public const KEY_NONE = 'none';

    /**
     * @inheritdoc
     */
    protected const NAME = 'Year Level';


    /** @var GenericCollection|YearLevel[] */
    private $yearLevels;


    /**
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param GenericCollection|YearLevel[]      $yearLevels
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        GenericCollection $yearLevels
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->yearLevels = $yearLevels->shallowClone()->indexByCallback(
            static function (YearLevel $yearLevel) {
                return $yearLevel->getId();
            }
        );
    }


    /**
     * This definition is available on the badge list page, but only if
     * curriculum tagging is enabled.
     *
     * @param IConfigLoader $configLoader
     * @return array
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Filter\Page\Model::PAGE_BADGE_LIST,
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
        if ($this->yearLevels[$key]) {
            return $this->yearLevels[$key]->getName();
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
                static function (int $yearLevelId) {
                    return (string)$yearLevelId;
                },
                $this->yearLevels->keys()
            )
        );
    }


    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(Filter\Page\Model $pageFilter): GenericCollection
    {
        $options = $pageFilter->getOptions();

        // Ensure it contains all YearLevels
        foreach ($this->yearLevels as $yearLevel) {
            $abstractKey = (string)$yearLevel->getId();
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
        return '@filterDefinitions/Badge/YearLevel/templates';
    }
}
