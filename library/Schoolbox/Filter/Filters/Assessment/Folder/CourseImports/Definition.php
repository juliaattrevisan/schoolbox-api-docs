<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder\CourseImports;

use InvalidArgumentException;
use Schoolbox\Filter\Page;
use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;
use Schoolbox\IConfigLoader;

/**
 * Displays a list of folders where the courseSpec has been imported
 *
 * Assessments will match this filter if their ultimate/indirect folder matches that selected.
 *
 * eg, Assessment_A lives in project_B which lives in folder_C, if folder_C is selected, Assessment_A will be fetched.
 */
class Definition extends FolderFilter\Definition
{

    // The name to expose in the Admin UI
    protected const NAME = 'Folders';

    /**
     * @inheritdoc
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Page\Model::PAGE_COURSE_MARK_BOOK,
        ];
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [
            self::KEY_CLASSES,
            self::KEY_GROUPS,
        ];
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/Folder/CourseImports/templates';
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
