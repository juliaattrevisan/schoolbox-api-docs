<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 * Steps:
 * 1. Provision legacy.tar.gz data
 * 2. vagrant ssh
 * 3. Execute cd /vagrant && ./vendor/bin/codecept run --steps acceptance SurveyCest
 * ./vendor/codeception/codeception/codecept --steps run acceptance SurveyCest
 */

use Page\Survey;
use Codeception\Util\Locator;

class SurveyCest
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
     * Clean up after every test
     *
     * @param AcceptanceTester $i
     */
    public function _after(AcceptanceTester $i)
    {
        // TODO: clean up?
    }

    /**
     * Tests adding and modifying survey questions in question bank
     * @param AcceptanceTester $i
     * @throws Exception
     */
    public function questionBank(AcceptanceTester $i)
    {
        $surveyPage = new Survey($i);

        // create survey
        $classId = 176; // Chemistry 1 3

        $i->amOnPage(Survey::getCreateSurveyUrl($classId));
        $surveyTitle = 'Cest Survey One';
        $i->fillField('input[name="title"]', $surveyTitle);
        $i->click('#createAssess [type=submit][value="Create"]');
        $i->waitForElement('input[name="assess_id"]');
        $assessId = $i->grabValueFrom('input[name="assess_id"]');

        // create section
        // http://192.168.36.10/survey/section/create/54
        $sectionTitle = 'Cest Section One';
        $i->fillField('input[name="title"]', $sectionTitle);
        $i->click('#createSection [type=submit][value="Create Section"]');
        $i->waitForElement('input[name="section_id"]');
        $sectionId = $i->grabValueFrom('input[name="section_id"]');

        // create question
        $question1 = 'What kind of food do you like? ' . time();
        $question1Id = $surveyPage->createQuestion(Survey::TYPE_SHORT_ANSWER, $question1);

        // back to survey summary page
        // http://192.168.36.10/survey/summary/56
        $i->amOnPage(Survey::getSummarySurveyUrl($assessId));
        $i->waitForText($surveyTitle);

        // add a question to question bank
        $surveyPage->addToQuestionBank($question1Id);

        // find the question from question bank
        $j = $surveyPage->findInQuestionBank($question1);

        // goto modify a question
        $surveyPage->modifyInQuestionBank($j);

        // back to survey summary page
        // http://192.168.36.10/survey/summary/56
        $i->amOnPage(Survey::getSummarySurveyUrl($assessId));

        // create another question
        // Jerry Tot!
        $i->click(Locator::elementAt('#content a.icon-more', 2));
        // New Question
        $i->click(Locator::elementAt('#section' . $sectionId . '-options a.icon-add', 1));

        // save the question
        $question2 = 'Signature please? ' . time();
        $question2Id = $surveyPage->createQuestion(Survey::TYPE_SHORT_SIGNATURE, $question2);

        // back to survey summary page
        // http://192.168.36.10/survey/summary/56
        $i->amOnPage(Survey::getSummarySurveyUrl($assessId));
        $i->waitForText($surveyTitle);

        // add this to question bank
        $surveyPage->addToQuestionBank($question2Id);

        // find the question from question bank
        $j = $surveyPage->findInQuestionBank($question2);

        // goto modify a question
        // goto modify a question
        $surveyPage->modifyInQuestionBank($j);

        // back to survey summary page
        // http://192.168.36.10/survey/summary/56
        $i->amOnPage(Survey::getSummarySurveyUrl($assessId));

        // create one more question
        // Jerry Tot!
        $i->click(Locator::elementAt('#content a.icon-more', 2));
        // New Question
        $i->click(Locator::elementAt('#section' . $sectionId . '-options a.icon-add', 1));

        // save the question
        $question3 = 'Do you like travelling? ' . time();
        $question3Id = $surveyPage->createQuestion(Survey::TYPE_YESNO, $question3);

        // back to survey summary page
        // http://192.168.36.10/survey/summary/56
        $i->amOnPage(Survey::getSummarySurveyUrl($assessId));
        $i->waitForText($surveyTitle);

        // add this to question bank
        $surveyPage->addToQuestionBank($question3Id);

        // find the question from question bank
        $j = $surveyPage->findInQuestionBank($question3);

        // goto modify a question
        $surveyPage->modifyInQuestionBank($j);

    }

}
