<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Pastoral\Type;

use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Pastoral\Type;

/**
 * Definition for pastoral type/subtype filter.
 */
class Definition extends Filter\AbstractDefinition
{
    public const NAME = 'Type';

    /**
     * Constructor
     *
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param GenericCollection|Type\Model[]     $types
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        private GenericCollection $types,
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
        // Return types and subtypes
        $keys = [];
        foreach ($this->types as $type) {
            // Only include type if there are no subtypes
            if (count($type->getSubTypes()) === 0) {
                $keys[] = $type->getId();
            }
            foreach ($type->getSubTypes() as $subType) {
                $keys[] = sprintf('%d_%d', $type->getId(), $subType->getId());
            }
        }
        return $keys;
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
        // Does this key contain a subtype?
        $keyParts   = explode('_', $key);
        $keyType    = filter_var($keyParts[0], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $keySubType = filter_var($keyParts[1] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        foreach ($this->types as $type) {
            if ($keyType !== $type->getId()) {
                continue;
            }
            if (!isset($keySubType)) {
                return $type->getName();
            }
            foreach ($type->getSubTypes() as $subType) {
                if ($keySubType === $subType->getId()) {
                    return sprintf('%s › %s', $type->getName(), $subType->getName());
                }
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
        foreach ($this->types as $type) {
            $abstractKey = (string)$type->getId();
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

            // Add subtypes
            foreach ($type->getSubTypes() as $subType) {
                $abstractKey = sprintf('%d_%d', $type->getId(), $subType->getId());
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
        }
        return $options;
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Pastoral/Type/templates';
    }
}
