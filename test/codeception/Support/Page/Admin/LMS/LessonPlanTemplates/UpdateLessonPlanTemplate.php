<?php

namespace Tests\Support\Page\Admin\LMS\LessonPlanTemplates;

use Tests\Support\AcceptanceTester;

class UpdateLessonPlanTemplate
{
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
     * type a field title
     * @param int    $templateFieldNumber
     * @param string $templateFieldTitle
     * @return void
     */
    public function typeFieldTile(int $templateFieldNumber, string $templateFieldTitle): void
    {
        $templateField = 'input#field-title-' . $templateFieldNumber;
        $this->tester->waitForElement($templateField);
        $this->tester->fillField($templateField, $templateFieldTitle);
    }

    /**
     * type a field body description
     * @param int    $templateFieldNumber
     * @param string $body
     * @return $this
     */
    public function fillBody(int $templateFieldNumber, string $body): self
    {
        $this->tester->fillRichTextField('field-body-' . $templateFieldNumber, $body);
        return $this;
    }
}
