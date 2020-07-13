<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder\TeacherInvolved;

use InvalidArgumentException;
use Schoolbox\Filter\Page;
use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;

/**
 * Display a list of folders (either classes, groups or both)
 * Whether classes, groups or both are available for selection is configured by the admin
 * The list of folders is generated at run-time for the current user and page
 *
 * Assessments will match this filter if their ultimate/indirect folder matches that selected.
 *
 * eg, Assessment_A lives in project_B which lives in folder_C, if folder_C is selected, Assessment_A will be fetched.
 */
class Definition extends FolderFilter\Definition
{

    // The name to expose in the UI
    protected const NAME = 'Teacher Folders';


    /**
     * @inheritdoc
     */
    public static function getPages(): array
    {
        return [
            Page\Model::PAGE_PENDING_ASSESSMENTS,
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
    public static function isMandatory(string $page): bool
    {
        // The teacher folder filter must be enabled on all pages. No effect on user-configurability
        return true;
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/Folder/TeacherInvolved/templates';
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
        throw new InvalidArgumentException("Unknown FolderFilter key: $key");
    }
}
