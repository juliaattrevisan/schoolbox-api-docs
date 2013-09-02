<?php
/**
 * Contains test steps for online assessments.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Behat\Mink\Element;

class Subcontext_AssessmentContext extends BaseContext {

    /**
     * Adds a multiple choice answer, at a position (1-indexed)
     *
     * @Then /^(?:|I )add (?:right |correct |)answer "(?P<answer>[^"]*)" at position "(?P<position>[^"]*)"$/
     *
     * @param  int    $position
     * @param  string $answer
     * @param  bool   $correct
     * @throws Exception if the form isn't found
     */
    public function addMultipleChoiceAnswer($position, $answer, $correct = true) {
        $form = $this->getSession()->getPage()->find('css', 'form#createQuestionForm');
        if (!($form instanceof Element\NodeElement)) {
            throw new Exception('Add task button not found');
        }

        // Keep on adding answers until there are $position answers.
        // Assume position 0 = 1
        $position = max(1, intval($position));
        while (count($this->getAnswerInputList()) < $position) {
            $form->find('css', 'a.add-answer')->click();
        }

        $this->editAnswer($position, $answer, $correct);
    }

    /**
     * Adds an incorrect multiple choice answer
     * I fill in right answer "1" with "Answer 1"
     *
     * @Then /^(?:|I )add (?:wrong|incorrect) answer "(?P<answer>[^"]*)" at position "(?P<position>[^"]*)"$/
     *
     * @param  int    $position
     * @param  string $answer
     * @throws Exception if the form isn't found
     */
    public function addWrongMultipleChoiceAnswer($position, $answer) {
        $this->addMultipleChoiceAnswer($position, $answer, false);
    }

    /**
     * @return Element\NodeElement[]
     */
    protected function getAnswerInputList() {
        $form = $this->getSession()->getPage()->find('css', 'form#createQuestionForm');
        $list = $form->findAll('css', '#answerCollection .inputs');
        return $list;
    }

    /**
     * Makes changes to an answer.
     *
     * @param int    $position
     * @param string $answer
     * @param bool   $correct
     */
    protected function editAnswer($position, $answer, $correct = true) {
        $element_list = $this->getAnswerInputList();
        $element      = $element_list[$position-1];
        $element->fillField('answerDescription[]', $answer);


        $checkbox   = $element->findField('answer[]');
        $is_checked = $checkbox->isChecked();
        if (($correct && !$is_checked) || (!$correct && $is_checked)) {
            $checkbox->click();
        }
    }

}