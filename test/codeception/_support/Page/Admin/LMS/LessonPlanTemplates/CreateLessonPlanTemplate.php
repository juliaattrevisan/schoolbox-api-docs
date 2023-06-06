<?php

namespace Page\Admin\LMS\LessonPlanTemplates;

use AcceptanceTester;
use Codeception\Util\Locator;

class CreateLessonPlanTemplate
{
    public const URL = '/adminv2/lesson-plan/template/create';
    public const TEMPLATE_NAME = '#lesson-plan-template-title';

    public const ADD_NEW_FIELD = '#add-field';

    public const BUTTON_SAVE = '#save-button';

    public const BUTTON_DELETE = 'a.button.icon-delete';

    public const CONFIRM_YES = 'a#confirm-dialog-confirm-button.button.submit';

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
     * type a lesson plan template title
     */
    public function typeName($templateName): void
    {
        $this->tester->waitForElementVisible(self::TEMPLATE_NAME);
        $this->tester->fillField(self::TEMPLATE_NAME, $templateName);
    }

    /**
     * click 'Add a new field"
     */
    public function clickAddNewFieldButton(): void
    {
        $this->tester->waitForElement(self::ADD_NEW_FIELD);
        $this->tester->click(self::ADD_NEW_FIELD);
    }


    /**
     * type a field title
     */
    public function typeFieldTile(int $templateFieldNumber, string $templateFieldTitle): void
    {
        $templateField = 'input#field-title-new' . $templateFieldNumber;
        $this->tester->waitForElement($templateField);
        $this->tester->fillField($templateField, $templateFieldTitle);
    }

    /**
     * type a field body description
     * @param string $body
     */
    public function fillBody(int $templateFieldNumber, string $body): self
    {
//        $templateField = 'input#field-title-new' . $templateFieldNumber;
        $this->tester->fillRichTextField('field-body-new' . $templateFieldNumber, $body);
        return $this;
    }


    /**
     * checkbox field visible to students
     */
    public function clickCheckboxVisibleStudents(int $templateFieldNumber): void
    {
//        $this->tester->click('input#field-new-visibility-' . $templateFieldNumber);
        $this->tester->click('label[for="field-new-visibility-' . $templateFieldNumber.'"]');

    }

    /**
     * click SAVE button
     */
    public function clickSaveButton(): void
    {
        $this->tester->click(self::BUTTON_SAVE);
    }

    /**
     * click delete button
     */
    public function clickDeleteButton(): void
    {
        $this->tester->click(Locator::firstElement(self::BUTTON_DELETE));
    }

    /**
     * confirm to delete a field: click yes in the confirmation dialog
     */
    public function clickConfirmDeleteButton(): void
    {
        $this->tester->click(Locator::lastElement(self::CONFIRM_YES));
    }



    /**
     * create a Lesson Plan Template
     */
    public function createLessonPlanTemplate(string $templateName): void
    {
        $this->typeName($templateName);
        $this->clickSaveButton();
    }


}
