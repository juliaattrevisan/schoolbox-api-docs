<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\Project;

use InvalidArgumentException;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\NullableContext;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;

/**
 * Displays a list of projects.
 *
 * Admin can configure whether to only show projects:
 * □ class folders,
 * □ group folders,
 * □ imported to classes,
 * □ imported to groups,
 * □ in unit-specs
 */
class Definition extends NullableContext\Definition
{

    // The name to expose in the UI
    protected const NAME = 'Project';

    // Where does the project reside?
    const KEY_ON_CLASS_FOLDER = 'on_class_folder';     // created directly on a class folder
    const KEY_ON_GROUP_FOLDER = 'on_group_folder';     // created directly on a group folder
    const KEY_ON_CLASS_UNIT_IMPL = 'on_class_unit_impl';  // imported onto class folder
    const KEY_ON_GROUP_UNIT_IMPL = 'on_group_unit_impl';  // imported onto group folder
    const KEY_ON_UNIT_SPEC = 'on_unit_spec';        // on unit spec


    /**
     * @inheritdoc
     */
    protected function unsetInvalidOptions(Filter\Page\Model $pageFilter): GenericCollection
    {
        $cleanedPageOptions = parent::unsetInvalidOptions($pageFilter);

        foreach ($cleanedPageOptions as $idx => $option) {
            // For now, no pages can have projects that reside in unitSpecs
            // This is because the list of projects is set to:
            // 1. Projects in folders taught by user, plus
            // 2. Projects in unit-impls on folders taught by user
            if (self::KEY_ON_UNIT_SPEC === $option->getKey()) {
                $cleanedPageOptions->offsetUnset($idx);
            }
        }
        return $cleanedPageOptions;
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return array_merge(parent::getCustomKeys(), [
            self::KEY_ON_CLASS_FOLDER,
            self::KEY_ON_GROUP_FOLDER,
            self::KEY_ON_CLASS_UNIT_IMPL,
            self::KEY_ON_GROUP_UNIT_IMPL,
            self::KEY_ON_UNIT_SPEC,
        ]);
    }


    /**
     * @inheritdoc
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        // For now, not available on any pages - purely used for internal query building
        return [
//            Filter\Page\Model::PAGE_MARK_BOOK,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        switch ($key) {
            case self::KEY_NONE:
                return 'Not inside a project';
            case self::KEY_ON_CLASS_FOLDER:
                return 'Created on class';
            case self::KEY_ON_GROUP_FOLDER:
                return 'Created on group';
            case self::KEY_ON_CLASS_UNIT_IMPL:
                return 'In unit imported to class';
            case self::KEY_ON_GROUP_UNIT_IMPL:
                return 'In unit imported to group';
            case self::KEY_ON_UNIT_SPEC:
                return 'In unit specification';
        }
        throw new InvalidArgumentException("Unknown Project filter key: $key");
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/NullableContext/Project/templates';
    }
}
