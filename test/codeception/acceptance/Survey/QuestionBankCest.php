<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/**
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /vagrant && ./vendor/bin/codecept run --steps acceptance Survey/QuestionBankCest
 * ./vendor/codeception/codeception/codecept --steps run acceptance Survey/QuestionBankCest
 */

use Page\Common;
use Page\Survey\SurveyForm;
use Page\Survey\QuestionForm;
use Page\Survey\SummaryPage;
use Page\Survey\QuestionBankPage;

class QuestionBankCest
{
    /**
     * Preparations
     *
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function _before(AcceptanceTester $i)
    {
        $i->loginAsAdmin();
    }

    /**
     * Tests adding and modifying survey questions in question bank
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function questionBank(AcceptanceTester $i)
    {
        $classId = Common::CLASS_ID_CHEMISTRY_1_3;
        $surveyTitle = 'Cest Survey One ' . Common::gentext(6);
        $sectionTitle = 'Cest Section One ' . Common::gentext(6);
        $question1 = Common::gentext(100);
        $question1Type = QuestionForm::TYPE_SHORT_ANSWER;
        $question2 = Common::gentext(50);
        $question2Type = QuestionForm::TYPE_SHORT_SIGNATURE;
        $question3 = Common::gentext(80);
        $question3Type = QuestionForm::TYPE_YESNO;

        $categoryId = QuestionBankPage::CATEGORY_ELECTIVE_SELECTION;

        // create survey
        $surveyForm = new SurveyForm($i);
        $surveyForm->openCreate($classId);
        $sectionForm = $surveyForm->setTitle($surveyTitle)
//            ->setOpenDate(\Alaress_Date_Format::now()->setTime(0, 0, 0))
        ->create();

        $assessId = $sectionForm->getAssessId();

        // create section
        $questionForm = $sectionForm->setTitle($sectionTitle)
            ->create();
        $sectionId = $questionForm->getSectionId();

        // create question
        $questionForm->setType($question1Type)
            ->setQuestion($question1)
            ->create();
        $question1Id = $questionForm->getQuestionId($question1Type, $sectionId);

        // back to survey summary page
        $summaryPage = new SummaryPage($i);
        $summaryPage->open($assessId, $surveyTitle);

        // add the question to question bank
        $summaryPage->addToQuestionBank($question1Id, $categoryId);

        // find the question in question bank and click modify
        $questionBankPage = $summaryPage->clickAddFromQuestionBank($sectionId);
        $questionBankPage->clickCategory($categoryId);
        $questionFormInBank = $questionBankPage->clickModify($question1);

        // save without any change
        $questionFormInBank->modify();

        // back to survey summary page
        $summaryPage = new SummaryPage($i);
        $summaryPage->open($assessId, $surveyTitle);

        // create another question
        $questionForm2 = $summaryPage->clickNewQuestion($sectionId);

        // save the question
        $questionForm2->setType($question2Type)
            ->setQuestion($question2)
            ->create();
        $question2Id = $questionForm->getQuestionId($question2Type, $sectionId);

        // back to survey summary page
        $summaryPage = new SummaryPage($i);
        $summaryPage->open($assessId, $surveyTitle);

        // add the question to question bank
        $summaryPage->addToQuestionBank($question2Id, $categoryId);

        // find the question in question bank and click modify
        $questionBankPage = $summaryPage->clickAddFromQuestionBank($sectionId);
        $questionBankPage->clickCategory($categoryId);
        $questionFormInBank2 = $questionBankPage->clickModify($question2);

        // save without any change
        $questionFormInBank2->modify();

        // back to survey summary page
        $summaryPage = new SummaryPage($i);
        $summaryPage->open($assessId, $surveyTitle);

        // create one more question
        $questionForm3 = $summaryPage->clickNewQuestion($sectionId);

        // save the question
        $questionForm3->setType($question3Type)
            ->setQuestion($question3)
            ->create();
        $question3Id = $questionForm->getQuestionId($question3Type, $sectionId);

        // back to survey summary page
        $summaryPage = new SummaryPage($i);
        $summaryPage->open($assessId, $surveyTitle);

        // add the question to question bank
        $summaryPage->addToQuestionBank($question3Id, $categoryId);

        // find the question in question bank and click modify
        $questionBankPage = $summaryPage->clickAddFromQuestionBank($sectionId);
        $questionBankPage->clickCategory($categoryId);
        $questionFormInBank3 = $questionBankPage->clickModify($question3);

        // save without any change
        $questionFormInBank3->modify();
    }
}
