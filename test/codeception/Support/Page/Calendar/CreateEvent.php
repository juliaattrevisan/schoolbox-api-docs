<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Tests\Support\Page\Calendar;

use Exception;
use Tests\Support\AcceptanceTester;

class CreateEvent
{
    public const URL_CREATE_EVENT = '/calendar/event/create';

    public const TITLE = 'input#title';

    public const SELECTOR_LEVEL = 'select[name="level"]';

    public const LEVEL_TYPE_PERSONAL = 'Personal';

    public const LEVEL_TYPE_GROUP = 'Group';

    public const LEVEL_TYPE_SCHOOL = 'School';
    public const SUBMIT_BUTTON = 'input#calendar-modify-submit';


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
     * typing an event title
     * @throws Exception
     */
    public function typeTitle($eventTitle): void
    {
        $this->tester->fillField(self::TITLE, $eventTitle);
    }


    /**
     * sets event level by passing its level: Personal, School, Group
     * @throws Exception
     */
    public function selectLevel(string $levelType): self
    {
        $this->tester->selectOption(self::SELECTOR_LEVEL, $levelType);
        return $this;
    }

        /**
         * click Add Event or Modify Event button
         * @throws Exception
         */
    public function clickSubmitEvent(): void
    {
        $this->tester->click(self::SUBMIT_BUTTON);
    }


    /**
     * tick the checkbox for a group
     */
    public function selectGroup($folderId): void
    {
        $this->tester->click(['css' => 'label[class="checkbox"][for="folder' . $folderId . '"]']);
    }

    /**
     * tick the checkbox for a role by passing its id
     */
    public function selectRole(int $roleId): void
    {
        $this->tester->click(['css' => 'label[for="role' . $roleId . '"]']);
    }

    /**
     * tick the checkbox for campus by passing its id
     */
    public function selectCampus(int $campusId): void
    {
        $this->tester->click(['css' => 'label[for="campus' . $campusId . '"]']);
    }
}
