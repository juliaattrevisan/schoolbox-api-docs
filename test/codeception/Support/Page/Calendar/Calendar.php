<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Tests\Support\Page\Calendar;

use Codeception\Util\Locator;
use Exception;
use Tests\Support\AcceptanceTester;

class Calendar
{
    public const URL = '/calendar/week';

    public const DAY_PANEL = 'td.fc-day';

    public const CALENDAR_EVENT = 'a.fc-daygrid-event';

    public const CALENDAR_PREVIOUS_BUTTON = 'button.fc-customPrev-button.fc-button.fc-button-primary';

    public const EVENT_DIALOG = 'div#createEventPanel';

    public const DIALOG_TITLE = 'input#createEventBubbleTitle';

    public const DIALOG_CREATE_BUTTON = 'input[value=Create\ Event]';

    public const BUTTON_LINK = 'a.button';

    public const DELETE_CONFIRMATION_DIALOG = 'a.button.submit';

    /**
     * This test's acceptance tester actor.
     */
    private AcceptanceTester $tester;

    /**
     * Constructor
     *
     * @param AcceptanceTester $i
     */
    public function __construct(AcceptanceTester $i)
    {
        $this->tester = $i;
    }

    /**
     * click a day field in the calendar
     */
    public function clickDayCalendar(): void
    {
        $this->tester->click(self::DAY_PANEL);
    }


    /**
     * click previous button in the calendar
     * @throws Exception
     */
    public function clickPreviousButton(): void
    {
        $this->tester->click(self::CALENDAR_PREVIOUS_BUTTON);
    }

    /**
     * click an event in the calendar grid
     * @throws Exception
     */
    public function clickCalendarEvent($evenTitle): void
    {
        $element = Locator::contains(self::CALENDAR_EVENT, $evenTitle);
        $this->tester->clickWithLeftButton($element);
    }
    //DIALOG

    /**
     * typing a title in the calendar dialog
     * @throws Exception
     */
    public function typeTitle($dialogEventTitle): void
    {
        $this->tester->fillField(self::DIALOG_TITLE, $dialogEventTitle);
    }

    /**
     * click Create button in the calendar dialog
     * @throws Exception
     */
    public function clickCreateEvent(): void
    {
        $this->tester->click(self::DIALOG_CREATE_BUTTON);
    }

    /**
     * click the button View Details, Modify or Delete the calendar event dialog
     * @throws Exception
     */
    public function clickDialogButton($buttonName): void
    {
        $element = Locator::contains(self::BUTTON_LINK, $buttonName);
        $this->tester->clickWithLeftButton($element);
    }

    /**
     * click the button Delete in the confirmation dialog
     * @throws Exception
     */
    public function clickConfirmDelete(): void
    {
        $element = Locator::contains(self::BUTTON_LINK, 'Delete');
        $this->tester->clickWithLeftButton(Locator::lastElement(self::DELETE_CONFIRMATION_DIALOG));
    }
}
