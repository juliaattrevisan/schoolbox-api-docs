<?php

namespace Admin\LMS\LessonPlanTemplates;

use AcceptanceTester;
use Exception;
use Page\Admin\LMS\LessonPlanTemplates\CreateLessonPlanTemplate;
use Page\Admin\LMS\LessonPlanTemplates\LessonPlanTemplateList;
use Page\Admin\LMS\LessonPlanTemplates\UpdateLessonPlanTemplate;

class LessonPlanTemplatesCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     *
     * get logged in
     * navigate to  /adminv2/lesson-plan/template/create
     */
    public function _before(AcceptanceTester $i): void
    {
        $i->logout();
        $i->loginAsAdmin();
        $templateList = new LessonPlanTemplateList($i);
        $i->amOnPage($templateList::URL);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * @beforeSuite
     *
     * on adminv2/lesson-plan/template page click Create button
     * Type a Title
     * Click Add a new field button
     * Add a Field title
     * Add a Field Body
     * Click Save
     * Ensure the Lesson Plan Template is presented in the Lesson PLan Template list
     */
    public function createLessonPlanTemplate(AcceptanceTester $i): void
    {
        $templates = new CreateLessonPlanTemplate($i);
        $templateList = new LessonPlanTemplateList($i);
        $templateName = "Automation Template 1";

        $i->amGoingTo('Click add a lesson plan template button in the lesson plan template lists page');
        $templateList->clickAddLessonPlanTemplateButton();
        $i->amGoingTo("Type a template name,add a field name and body and save it");
        $templates->typeName($templateName);
        $templates->typeFieldTile(1, 'Automation Field 1');
        $templates->fillBody(1, 'Automation Body 1');
        $templates->clickSaveButton();
        $i->amGoingTo("Check the lesson pan template is presented in the template list");
        $i->canSee($templateName);
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * @depends createLessonPlanTemplate
     * @beforeSuite
     *
     * on adminv2/lesson-plan/template page click the Modify button
     * Update the Title
     * Click Add a new field button
     * Update the existing field title and body
     * Add a new Field with field title and body
     * Delete a Field
     * Click Save
     * Click the template in the list
     * Ensure the dialog is presented with the fields properly presented
     */
    public function updateLessonPlanTemplate(AcceptanceTester $i): void
    {
        $templates = new CreateLessonPlanTemplate($i);
        $templateList = new LessonPlanTemplateList($i);
        $templateUpdate = new UpdateLessonPlanTemplate($i);

        $i->amGoingTo('Click the modify button in the lesson plan template lists page');
        $templateId = $i->grabFromDatabase('lesson_plan_template', 'id', ['deleted_at' => null, 'title like' => "Automation Template 1"]);
        $templateList->clickModifyLessonPlanTemplateButton($templateId);
        $i->waitForText('Fields', 3);
        $i->amGoingTo('Update the field title and body');
        $templateFieldId = $i->grabFromDatabase('lesson_plan_template_field', 'id', ['deleted_at' => null, 'title like' => "Automation Field 1"]);
        $templateUpdate->typeFieldTile($templateFieldId, 'Automation Template 1 Updated');
        $templateUpdate->fillBody($templateFieldId, 'Automation Body 1 Updated');
        $i->amGoingTo('Add a new field, type a filed title and body and save it');
        $templates->clickAddNewFieldButton();
        $templates->typeFieldTile(1, 'Automation Field 2');
        $templates->fillBody(1, 'Automation Body 2');
        $templates->clickSaveButton();
        $i->amGoingTo("Click the template title in the template list and check the fields are updated");
        $templateList->clickLessonPlanTemplateTitle($templateId);
        $i->canSee('Automation Template 1 Updated');
        $i->canSee('Automation Body 1 Updated');
        $i->canSee('Automation Field 2');
        $i->canSee('Automation Body 2');
        $i->amGoingTo("Close the modal dialog");
        $templateList->clickCloseModal();
        $i->amGoingTo('Modify a lesson plan template, delete a field, save it');
        $templateList->clickModifyLessonPlanTemplateButton($templateId);
        $templates->clickDeleteButton();
        $templates->clickConfirmDeleteButton();
        $templates->clickSaveButton();
        $i->amGoingTo("Click the template title in the template list and check the field deleted is not presented");
        $templateList->clickLessonPlanTemplateTitle($templateId);
        $i->cantSee('Automation Template 1 Updated');
        $i->cantSee('Automation Body 1 Updated');
        $i->canSee('Automation Field 2');
        $i->canSee('Automation Body 2');
        $i->cantSee('This field is not visible to students');
        $templateList->clickCloseModal();
        $i->amGoingTo('Modify a lesson plan template, uncheck the visible to students and save it');
        $templateList->clickModifyLessonPlanTemplateButton($templateId);
        $templateFieldId = $i->grabFromDatabase('lesson_plan_template_field', 'id', ['deleted_at' => null, 'title like' => "Automation Field 2"]);
        $templates->clickCheckboxVisibleStudents($templateFieldId);
        $templates->clickSaveButton();
        $i->amGoingTo("Click the template is presenting the message invisible to students on the modal");
        $templateList->clickLessonPlanTemplateTitle($templateId);
        $i->canSee('This field is not visible to students');
    }

    /**
     * @param AcceptanceTester $i
     * @throws Exception
     * @beforeSuite
     *
     * on adminv2/lesson-plan/template page
     * disable the lesson plan template
     * enable the lesson plan template
     * delete the template
     */
    public function deleteLessonPlanTemplate(AcceptanceTester $i): void
    {

        $templates = new CreateLessonPlanTemplate($i);
        $templateList = new LessonPlanTemplateList($i);

        $i->amGoingTo('Disable the lesson plan template');
        $templateId = $i->grabFromDatabase('lesson_plan_template', 'id', ['deleted_at' => null, 'title like' => "Automation Template 1"]);
        $templateList->clickLessonPlanTemplateMenu($templateId);
        $templateList->clickDisableEnableVisibility($templateId);
        $templateList->clickLessonPlanTemplateMenu($templateId);
        $i->canSee('Enable Visibility');
        $i->amGoingTo('Enable the lesson plan template');
        $templateList->clickDisableEnableVisibility($templateId);
        $templateList->clickLessonPlanTemplateMenu($templateId);
        $i->canSee('Disable Visibility');
        $i->amGoingTo('Delete the lesson plan template');
        $templateList->clickDeleteTemplate($templateId);
        $templates->clickConfirmDeleteButton();
        $i->cantSee('Automation Template 1');
    }
}
