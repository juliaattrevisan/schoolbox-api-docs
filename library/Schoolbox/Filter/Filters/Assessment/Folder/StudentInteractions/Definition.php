<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder\StudentInteractions;

use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;
use Schoolbox\Filter\Page;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;

/**
 * Display a list of folders (either classes, groups or both) where the student has submitted IResponses or received IFeedback.
 *
 * In contrast to the TeacherFolder filter, this filter does not permit insertion of arbitrary folders.
 *
 * Assessments will match this filter if their ultimate/indirect folder matches that selected.
 *
 * eg, Assessment_A lives in project_B which lives in folder_C, if folder_C is selected, Assessment_A will be fetched.
 */
class Definition extends FolderFilter\Definition
{

    // The name to expose in the UI
    protected const NAME = 'Student Folders';

    public const KEY_RESPONSE = 'response';
    public const KEY_FEEDBACK = 'feedback';


    /**
     * @inheritdoc
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
//            Option\Model::PAGE_GRADES,
            Page\Model::PAGE_UPCOMING_ASSESSMENTS,
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
            self::KEY_RESPONSE,
            self::KEY_FEEDBACK,
        ];
    }


    /**
     * @inheritdoc
     */
    protected function unsetInvalidOptions(Page\Model $pageFilter): GenericCollection
    {
        $options = parent::unsetInvalidOptions($pageFilter);

        // Upcoming Due Work Page doesn't support these options, so remove them
        if ($pageFilter->getPage() === Page\Model::PAGE_UPCOMING_ASSESSMENTS) {
            $notApplicable = [self::KEY_RESPONSE, self::KEY_FEEDBACK];
            foreach ($options as $idx => $option) {
                if (\in_array($option->getKey(), $notApplicable, true)) {
                    $options->offsetUnset($idx);
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
        return '@filterDefinitions/Assessment/Folder/StudentInteractions/templates';
    }

    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        switch ($key) {
            case self::KEY_CLASSES:
                return 'Currently-enrolled classes';
            case self::KEY_GROUPS:
                return 'Current group memberships';
            case self::KEY_RESPONSE:
                return 'Folders where student responded';
            case self::KEY_FEEDBACK:
                return 'Folders where student given feedback';
        }
        throw new \InvalidArgumentException("Unknown FolderFilter key: $key");
    }
}
