<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord\WorkType;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter\Page;
use Schoolbox\IConfigLoader;

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
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [];
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
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        // Get work type name from the database
        $workTypeMapper = \Schoolbox\Learning\Assessment\WorkType\Mapper::getInstance();
        try {
            $workType = $workTypeMapper->findById((int)$key);
            return $workType ? $workType->getName() : '';
        } catch (\Exception $e) {
            return '';
        }
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