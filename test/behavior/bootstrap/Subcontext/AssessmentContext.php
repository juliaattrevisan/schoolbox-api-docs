<?php

/**
 * Contains test steps for online assessments.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Behat\Mink\Element;

class Subcontext_AssessmentContext extends BaseContext
{

    /**
     * Clicks the "add rubric" button on a homepage.
     *
     * @Then /^(?:|I )edit rubrics$/
     */
    public function setRubrics()
    {
        $this->getSession()->getPage()->find('css', '.rubric_listing a.modify')->click();
    }

    /**
     * Adds a new rubric item.
     *
     * @Then /^(?:|I )add rubric group "(?P<name>[^"]*)" with criteria "(?P<items>[^"]*)"$/
     *
     * @param  string $name
     * @param  string $items
     * @throws Exception if the form isn't found
     */
    public function addRubricGroup($name, $items)
    {

        // Find the rubric form, and add a new group to it.
        $this->getCoreContext()->waitForElementOnPage('form.rubric-settings');
        $form  = $this->getSession()->getPage()->find('css', 'form.rubric-settings');
        $group = $form->find('css', 'ul#crit-list li.group.new');
        $group_input = $group->find('css', 'input.group.new');
        $this->spin(
            function () use ($group_input) {
                return $group_input->isVisible();
            }
        );
        $group_input->setValue($name);
        $group_input->blur();

        // What group did we just add? We're going to add items to it.
        $is_group_found = false;
        foreach ($form->findAll('css', 'ul#crit-list li.group') as $group) {
            /** @var Element\NodeElement $group */
            $group_input = $group->find('css', 'input.group');
            if ($group_input->getValue() === $name) {
                $is_group_found = true;
                break;
            }
        }
        if (!$is_group_found) {
            throw new Exception('The group we just entered could not be found');
        }

        // And add the items.
        foreach (explode(',', $items) as $item) {
            $item = trim($item);
            if (strlen($item)) {
                $item_input = $group->find('css', 'input.item.new');
                $this->spin(
                    function () use ($item_input) {
                        return $item_input->isVisible();
                    }
                );
                $item_input->setValue($item);
                $item_input->blur();
            }
        }
    }

    /**
     * Chooses the rubric result set "None, Very Poor, Poor, Average, Good, Very Good"
     *
     * @Then /^(?:|I )choose 5-level result set$/
     */
    public function selectFiveLevelResultSet()
    {
        $this->getSession()->getPage()->checkField('rs-2');
    }

    /**
     * Chooses a rubric criterion for this due work item.
     *
     * @Then /^(?:|I )check rubric criterion "(?P<base_name>[^"]*):(?P<item>[^"]*)"$/
     *
     * @param string $base_name
     * @param string $item
     * @throws Exception if the rubric option isn't found
     */
    public function selectRubricOnDueWork($base_name, $item)
    {
        $label_text = "$base_name - $item";
        $form       = $this->getSession()->getPage()->find('css', 'form#createSubmissionForm');
        $input_id   = false;
        foreach ($form->findAll('css', '#marktype-rubric .crit label') as $label) {
            /** @var Element\NodeElement $label */
            if ($label->getText() === $label_text) {
                $input_id = $label->getAttribute('for');
                break;
            }
        }
        if (!$input_id) {
            throw new Exception('The group we just entered could not be found');
        }

        $checkbox = $form->findById($input_id);
        if (!$checkbox->isChecked()) {
            $checkbox->click();
        }
    }

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
    public function addMultipleChoiceAnswer($position, $answer, $correct = true)
    {
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
    public function addWrongMultipleChoiceAnswer($position, $answer)
    {
        $this->addMultipleChoiceAnswer($position, $answer, false);
    }

    /**
     * @return Element\NodeElement[]
     */
    protected function getAnswerInputList()
    {
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
    protected function editAnswer($position, $answer, $correct = true)
    {
        $element_list = $this->getAnswerInputList();
        $element      = $element_list[$position - 1];
        $element->fillField('answerDescription[]', $answer);


        $checkbox   = $element->findField('answer[]');
        $is_checked = $checkbox->isChecked();
        if (($correct && !$is_checked) || (!$correct && $is_checked)) {
            $checkbox->click();
        }
    }
}
