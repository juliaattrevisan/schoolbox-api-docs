<?php

namespace Page\Admin\LMS\LessonPlanTemplates;

use AcceptanceTester;
use Codeception\Util\Locator;

class LessonPlanTemplateList
{
    public const URL = '/adminv2/lesson-plan/template';

    public const ICON_ADD = 'a.icon-add';

    public const ICON_DELETE = 'a.button.icon-delete';

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
     */
    public function clickAddLessonPlanTemplateButton(): void
    {
        $this->tester->click(self::ICON_ADD);
    }

    /**
     * click modify lesson plan template icon by passing the lesson plan template id
     */
    public function clickModifyLessonPlanTemplateButton(int $templateIdNumber): void
    {
        $this->tester->click('a[href="/adminv2/lesson-plan/template/update/' . $templateIdNumber . '"]');
    }

    /**
     * click the lesson plan template title by passing the lesson plan template id
     */
    public function clickLessonPlanTemplateTitle(int $templateIdNumber): void
    {
        $this->tester->click('a[data-reveal-id="lpt-preview-' . $templateIdNumber . '"]');
    }

    /**
     * click the delete button for a lesson plan template field
     */
    public function clickDeleteLessonPlanTemplateTitle(): void
    {
        $this->tester->click(self::ICON_DELETE);
    }

    /**
     * click the close button on the modal dialog
     */
    public function clickCloseModal(): void
    {
        $this->tester->click(Locator::lastElement(self::ICON_MODAL_CLOSE));
    }
}
