<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Tests\Acceptance\Survey;

use Tests\Support\AcceptanceTester;
use Tests\Support\Page\Common;
use Tests\Support\Page\Survey\SurveyForm;
use Tests\Support\Page\Survey\QuestionForm;
use Tests\Support\Page\Survey\SummaryPage;
use Tests\Support\Page\Survey\QuestionBankPage;
use Exception;

/**
 *
 * creates and modify a survey, add a question to the question bank
 *
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /usr/share/schoolbox && ./vendor/bin/codecept run --steps Acceptance Survey/QuestionBankCest
 *
 */

class QuestionBankCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function _before(AcceptanceTester $i): void
    {
        $i->loginAsAdmin();
    }

    /**
     * Tests adding and modifying survey questions in question bank
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function questionBank(AcceptanceTester $i): void
    {
        $classId = Common::CLASS_ID_9AENG;
        $surveyTitle = 'Cest Survey One ' . Common::gentext(6);
        $sectionTitle = 'Cest Section One ' . Common::gentext(6);
        $question1 = Common::gentext(100);
        $question1Type = QuestionForm::TYPE_SHORT_ANSWER;
        $question2 = Common::gentext(50);
        $question2Type = QuestionForm::TYPE_SHORT_SIGNATURE;
        $question3 = Common::gentext(80);
        $question3Type = QuestionForm::TYPE_YESNO;

        $categoryId = QuestionBankPage::CATEGORY_ELECTIVE_SELECTION;

        $i->amGoingTo('create survey');
        $surveyForm = new SurveyForm($i);
        $surveyForm->openCreate($classId);
        $sectionForm = $surveyForm->setTitle($surveyTitle)
//            ->setOpenDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
        ->create();

        $assessId = $sectionForm->getAssessId();

        $i->amGoingTo('create section');
        $questionForm = $sectionForm->setTitle($sectionTitle)
            ->create();
        $sectionId = $questionForm->getSectionId();

        $i->amGoingTo('create question');
        $questionForm->setType($question1Type)
            ->setQuestion($question1)
            ->create();
        $question1Id = $questionForm->getQuestionId($question1Type, $sectionId);

        $i->amGoingTo('back to survey summary page');
        $summaryPage = new SummaryPage($i);
        $summaryPage->open($assessId, $surveyTitle);

        $i->amGoingTo('add the question to question bank');
        $summaryPage->addToQuestionBank($question1Id, $categoryId);

        $i->amGoingTo('find the question in question bank and click modify');
        $questionBankPage = $summaryPage->clickAddFromQuestionBank($sectionId);
        $questionBankPage->clickCategory($categoryId);
        $questionFormInBank = $questionBankPage->clickModify($question1);

        $i->amGoingTo('save without any change');
        $questionFormInBank->modify();

        $i->amGoingTo('back to survey summary page');
        $summaryPage = new SummaryPage($i);
        $summaryPage->open($assessId, $surveyTitle);

        $i->amGoingTo('create another question');
        $questionForm2 = $summaryPage->clickNewQuestion($sectionId);

        $i->amGoingTo('save the question');
        $questionForm2->setType($question2Type)
            ->setQuestion($question2)
            ->create();
        $question2Id = $questionForm->getQuestionId($question2Type, $sectionId);

        $i->amGoingTo('back to survey summary page');
        $summaryPage = new SummaryPage($i);
        $summaryPage->open($assessId, $surveyTitle);

        $i->amGoingTo('add the question to question bank');
        $summaryPage->addToQuestionBank($question2Id, $categoryId);

        $i->amGoingTo('find the question in question bank and click modify');
        $questionBankPage = $summaryPage->clickAddFromQuestionBank($sectionId);
        $questionBankPage->clickCategory($categoryId);
        $questionFormInBank2 = $questionBankPage->clickModify($question2);

        $i->amGoingTo('save without any change');
        $questionFormInBank2->modify();

        $i->amGoingTo('back to survey summary page');
        $summaryPage = new SummaryPage($i);
        $summaryPage->open($assessId, $surveyTitle);

        $i->amGoingTo('create one more question');
        $questionForm3 = $summaryPage->clickNewQuestion($sectionId);

        $i->amGoingTo('save the question');
        $questionForm3->setType($question3Type)
            ->setQuestion($question3)
            ->create();
        $question3Id = $questionForm->getQuestionId($question3Type, $sectionId);

        $i->amGoingTo('back to survey summary page');
        $summaryPage = new SummaryPage($i);
        $summaryPage->open($assessId, $surveyTitle);

        $i->amGoingTo('add the question to question bank');
        $summaryPage->addToQuestionBank($question3Id, $categoryId);

        $i->amGoingTo('find the question in question bank and click modify');
        $questionBankPage = $summaryPage->clickAddFromQuestionBank($sectionId);
        $questionBankPage->clickCategory($categoryId);
        $questionFormInBank3 = $questionBankPage->clickModify($question3);

        $i->amGoingTo('save without any change');
        $questionFormInBank3->modify();
    }

    /**
     * Soft deleting data to not impact the tests
     *
     * @param AcceptanceTester $i
     */
    public function tearDown(AcceptanceTester $i): void
    {
        $i->amGoingTo("update delete_at for the data created in this test so it does impact next suite of tests");
        $i->updateInDatabase('assess', ['deleted_at' => "2022-01-15 11:22:34"], ['title like' => "Automation Test Pastoral Record%"]);
    }
}
