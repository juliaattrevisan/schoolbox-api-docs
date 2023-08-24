<?php

namespace Tests\Support\Page\Admin\LMS\LessonPlanTemplates;

use Tests\Support\AcceptanceTester;
use Codeception\Util\Locator;

class LessonPlanTemplateList
{
    public const URL = '/adminv2/lesson-plan/template';

    public const ICON_ADD = 'a.icon-add';

    public const ICON_MODAL_CLOSE = 'a.close-reveal-modal';

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
     * click add lesson plan template icon
     * @return void
     */
    public function clickAddLessonPlanTemplateButton(): void
    {
        $this->tester->click(self::ICON_ADD);
    }

    /**
     * click modify lesson plan template icon by passing the lesson plan template id
     * @param int $templateIdNumber
     * @return void
     */
    public function clickModifyLessonPlanTemplateButton(int $templateIdNumber): void
    {
        $this->tester->click('a[href="/adminv2/lesson-plan/template/update/' . $templateIdNumber . '"]');
    }

    /**
     * click the lesson plan template title by passing the lesson plan template id
     * @param int $templateIdNumber
     * @return void
     */
    public function clickLessonPlanTemplateTitle(int $templateIdNumber): void
    {
        $this->tester->click('a[data-reveal-id="lpt-preview-' . $templateIdNumber . '"]');
    }

    /**
     * click 3-dot button for a lesson plan template by passing the lesson plan template id
     * @param int $templateIdNumber
     * @return void
     */
    public function clickLessonPlanTemplateMenu(int $templateIdNumber): void
    {
        $this->tester->click('[data-test="wc-actions-dropdown-button-lpt-' . $templateIdNumber . '"]');
    }

    /**
     * click disable/enable lesson plan on the 3-dot menu dropdown
     * @param int $templateIdNumber
     * @return void
     */
    public function clickDisableEnableVisibility(int $templateIdNumber): void
    {
        $this->tester->click('a[href="/adminv2/lesson-plan/template/toggle/' . $templateIdNumber . '"]');
    }

    /**
     * lick ddelete lesson plan on the 3-dot menu dropdown
     * @param int $templateIdNumber
     * @return void
     */
    public function clickDeleteTemplate(int $templateIdNumber): void
    {
        $this->tester->click('a[href="/adminv2/lesson-plan/template/delete/' . $templateIdNumber . '"]');
    }

    /**
     * click the close button on the modal dialog
     * @return void
     */
    public function clickCloseModal(): void
    {
        $this->tester->click(Locator::lastElement(self::ICON_MODAL_CLOSE));
    }
}
