<?php

namespace Admin\LMS\LessonPlanTemplates;

use AcceptanceTester;
use Exception;
use Page\Admin\LMS\LessonPlanTemplates\CreateLessonPlanTemplate;
use Page\Admin\LMS\LessonPlanTemplates\LessonPlanTemplateList;

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
     * Deleting Lesson Plan Templates when the test is about to start so it does not get impacted by other tests already executed
     *
     * @param AcceptanceTester $i
     */
//    public function beforeSuite(AcceptanceTester $i): void
//    {
//        $i->amGoingTo("update delete_at for the lesson plan template so it does not impact the news tests");
//        $i->updateInDatabase('lesson_plan_template', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => "Automation%"]);
//    }

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
        $templateList->clickAddLessonPlanTemplateIcon();
        $i->amGoingTo("Type a template name,add a field name and body and save it");
        $templates->typeName($templateName);
        $templates->clickAddNewFieldButton();
        $templates->typeFieldTile(1, 'Automation Field 1');
        $templates->fillBody(1, 'Automation Body 1');
        $templates->clickSaveButton();
        $i->amGoingTo("Check the lesson pan template is presented in the template list");
        $i->canSee('Automation Template 1');

    }
}
