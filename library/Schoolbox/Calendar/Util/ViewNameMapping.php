<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Util;

/**
 * Mapping of view names between their name in FullCalendar and the name which
 * they are stored with.
 */
class ViewNameMapping
{
    // Stored names
    public const VIEW_MONTH_STORED_NAME = 'month';
    public const VIEW_WEEK_STORED_NAME  = 'agendaWeek';
    public const VIEW_DAY_STORED_NAME   = 'agendaDay';
    public const VIEW_LIST_STORED_NAME  = 'list';

    // FullCalendar names
    public const VIEW_MONTH_FC_NAME = 'dayGridMonth';
    public const VIEW_WEEK_FC_NAME  = 'timeGridWeek';
    public const VIEW_DAY_FC_NAME   = 'timeGridDay';
    public const VIEW_LIST_FC_NAME  = 'listWeek';

    public const MAP_FC_TO_STORED = [
        self::VIEW_DAY_FC_NAME   => self::VIEW_DAY_STORED_NAME,
        self::VIEW_WEEK_FC_NAME  => self::VIEW_WEEK_STORED_NAME,
        self::VIEW_MONTH_FC_NAME => self::VIEW_MONTH_STORED_NAME,
        self::VIEW_LIST_FC_NAME  => self::VIEW_LIST_STORED_NAME,
    ];

    public const MAP_STORED_TO_FC = [
        self::VIEW_DAY_STORED_NAME   => self::VIEW_DAY_FC_NAME,
        self::VIEW_WEEK_STORED_NAME  => self::VIEW_WEEK_FC_NAME,
        self::VIEW_MONTH_STORED_NAME => self::VIEW_MONTH_FC_NAME,
        self::VIEW_LIST_STORED_NAME  => self::VIEW_LIST_FC_NAME,
    ];
}
