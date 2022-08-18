<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Pastoral\Severity;

use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Pastoral\Severity;

/**
 * Definition for pastoral severity filter.
 */
class Definition extends Filter\AbstractDefinition
{
    public const NAME = 'Severity';

    /**
     * Constructor
     *
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param GenericCollection|Severity\Model[] $severities
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        private GenericCollection $severities,
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
    }

    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAbstractKeys(): array
    {
        return $this->severities->getPropertyValues('id');
    }

    /**
     * @inheritdoc
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Filter\Page\Model::PAGE_CLASS_PASTORAL,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        foreach ($this->severities as $severity) {
            if ((int)$key === $severity->getId()) {
                return $severity->getName();
            }
        }
        return '';
    }

    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(Filter\Page\Model $pageFilter): GenericCollection
    {
        $options = parent::insertDynamicOptions($pageFilter);
        foreach ($this->severities as $severity) {
            $abstractKey = (string)$severity->getId();
            if (!$options->offsetExists($abstractKey)) {
                $option = new Filter\Page\Option\Model();
                $option
                    ->setFilterPage($pageFilter)
                    ->setKey($abstractKey)
                    ->setAvailable($this->getInitialAvailability($abstractKey, $pageFilter))
                    ->setDefaults(
                        new GenericCollection(Filter\Page\Option\Defaults\Model::class)
                    );
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
        return '@filterDefinitions/Pastoral/Severity/templates';
    }
}
