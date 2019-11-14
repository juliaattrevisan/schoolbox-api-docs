<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\TemporalState;

use InvalidArgumentException;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use function array_merge;
use function in_array;


/**
 * Filters assessments by their publish, open, due and close dates.
 *
 * Available options:
 * □ Unpublished (             now < a.publish)
 * □ Upcoming    (a.publish <= now < a.open)
 * □ Open        (a.open    <= now < a.late)
 * □ Overdue     (a.late    <= now < a.close)
 * □ Closed      (a.close   <= now )
 *
 * □ Plan Unpublished (             now < p.publish)
 * □ Plan Upcoming    (p.publish <= now < p.open)
 * □ Plan Open        (p.open    <= now < p.late)
 * □ Plan Overdue     (p.late    <= now < p.close)
 * □ Plan Closed      (p.close   <= now )
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    protected const NAME = 'Activity State';

    // Filter on the assessment dates
    public const KEY_UNPUBLISHED = 'unpublished';
    public const KEY_UPCOMING = 'upcoming';
    public const KEY_OPEN = 'open';
    public const KEY_OVERDUE = 'overdue';
    public const KEY_CLOSED = 'closed';

    // Filter on plan dates (ie CAP)
    public const KEY_CUSTOM_UNPUBLISHED = 'cap_unpublished';
    public const KEY_CUSTOM_UPCOMING = 'cap_upcoming';
    public const KEY_CUSTOM_OPEN = 'cap_open';
    public const KEY_CUSTOM_OVERDUE = 'cap_overdue';
    public const KEY_CUSTOM_CLOSED = 'cap_closed';


    public static function getTemporalStates(): array
    {
        return [
            self::KEY_UNPUBLISHED,
            self::KEY_UPCOMING,
            self::KEY_OPEN,
            self::KEY_OVERDUE,
            self::KEY_CLOSED,
        ];
    }

    public static function getCapTemporalStates(): array
    {
        return [
            self::KEY_CUSTOM_UNPUBLISHED,
            self::KEY_CUSTOM_UPCOMING,
            self::KEY_CUSTOM_OPEN,
            self::KEY_CUSTOM_OVERDUE,
            self::KEY_CUSTOM_CLOSED,
        ];
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return array_merge(self::getTemporalStates(), self::getCapTemporalStates());
    }


    /**
     * @inheritdoc
     */
    public static function getPages(): array
    {
        return [
            Filter\Page\Model::PAGE_PENDING_ASSESSMENTS,
            Filter\Page\Model::PAGE_CLASS_MARK_BOOK,
            Filter\Page\Model::PAGE_COURSE_MARK_BOOK,
            Filter\Page\Model::PAGE_RUBRIC_MARK_BOOK,
            Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS,
//            Filter\Page\Model::PAGE_GRADES,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        if (in_array($key, [self::KEY_UNPUBLISHED, self::KEY_CUSTOM_UNPUBLISHED], true)) {
            return 'Unpublished';
        }
        if (in_array($key, [self::KEY_UPCOMING, self::KEY_CUSTOM_UPCOMING], true)) {
            return 'Upcoming';
        }
        if (in_array($key, [self::KEY_OPEN, self::KEY_CUSTOM_OPEN], true)) {
            return 'Open';
        }
        if (in_array($key, [self::KEY_OVERDUE, self::KEY_CUSTOM_OVERDUE], true)) {
            return 'Overdue';
        }
        if (in_array($key, [self::KEY_CLOSED, self::KEY_CUSTOM_CLOSED], true)) {
            return 'Closed';
        }
        throw new InvalidArgumentException("Unknown TemporalState key: $key");
    }


    /**
     * @inheritdoc
     */
    protected function unsetInvalidOptions(Filter\Page\Model $pageFilter): GenericCollection
    {
        $cleanedPageOptions = parent::unsetInvalidOptions($pageFilter);

        foreach ($cleanedPageOptions as $idx => $option) {
            $key = $option->getKey();
            switch ($pageFilter->getPage()) {

                case Filter\Page\Model::PAGE_GRADES:
                    // Do not permit 'upcoming' on the 'grades' page
                    if ($key === self::KEY_UNPUBLISHED || !in_array($key, self::getTemporalStates(), true)) {
                        $cleanedPageOptions->offsetUnset($idx);
                    }
                    break;
                case Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS:
                    // 'Upcoming Assessments' only supports these 3 options
                    if (!in_array($key, [
                        self::KEY_CUSTOM_UPCOMING,
                        self::KEY_CUSTOM_OPEN,
                        self::KEY_CUSTOM_OVERDUE,
                    ], true)) {
                        $cleanedPageOptions->offsetUnset($idx);
                    }
                    break;
                default:
                    // All other pages, just support the non-cap version
                    if (!in_array($key, self::getTemporalStates(), true)) {
                        $cleanedPageOptions->offsetUnset($idx);
                    }
            }
        }
        return $cleanedPageOptions;
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/TemporalState/templates';
    }
}
