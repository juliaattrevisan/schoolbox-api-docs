<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder\Readable;

use InvalidArgumentException;
use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;
use Schoolbox\Filter\Page;

/**
 * Display a list of folders (either classes, groups or both) which the current
 * user may read.
 *
 * Because the entire set of readable folders for a user may be too large to
 * display in a list, folders will be selected via a Selectize selector.
 */
class Definition extends FolderFilter\Definition
{
    // The name to expose in the UI
    protected const NAME = 'Classes and Groups';


    public const KEY_MY_CLASSES    = 'myClasses';
    public const KEY_MY_GROUPS     = 'myGroups';
    public const KEY_OTHER_CLASSES = 'otherClasses';
    public const KEY_OTHER_GROUPS  = 'otherGroups';


    /**
     * @inheritdoc
     */
    public static function getPages(): array
    {
        return [
            Page\Model::PAGE_ASSESSMENT_CALENDAR,
        ];
    }

    /**
     * This filter has no custom keys.
     *
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [
            self::KEY_MY_CLASSES,
            self::KEY_MY_GROUPS,
            self::KEY_OTHER_CLASSES,
            self::KEY_OTHER_GROUPS,
        ];
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/Folder/Readable/templates';
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        switch ($key) {
            case self::KEY_MY_CLASSES:
                return 'User\'s Classes';
            case self::KEY_MY_GROUPS:
                return 'User\'s Groups';
            case self::KEY_OTHER_CLASSES:
                return 'Other Classes';
            case self::KEY_OTHER_GROUPS:
                return 'Other Groups';
        }
        throw new InvalidArgumentException("Unknown FolderFilter key: '$key''");
    }
}
