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
use Schoolbox\GenericCollection;

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
        return [];
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
            case self::KEY_CLASSES:
                return 'Classes';
            case self::KEY_GROUPS:
                return 'Groups';
        }
        throw new InvalidArgumentException("Unknown FolderFilter key: '$key''");
    }
}
