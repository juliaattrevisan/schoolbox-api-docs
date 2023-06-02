<?php

namespace Page\Admin\LMS\LessonPlanTemplates;

use AcceptanceTester;

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
     */
    public function typeFieldTile(int $templateFieldNumber, string $templateFieldTitle): void
    {
        $templateField = 'input#field-title-' . $templateFieldNumber;
        $this->tester->waitForElement($templateField);
        $this->tester->fillField($templateField, $templateFieldTitle);
    }

    /**
     * type a field body description
     * @param string $body
     */
    public function fillBody(int $templateFieldNumber, string $body): self
    {
        $templateField = 'input#field-title-' . $templateFieldNumber;
        $this->tester->fillRichTextField('field-body-' . $templateFieldNumber, $body);
        return $this;
    }
}
