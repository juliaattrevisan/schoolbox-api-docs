<?php

use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Class Survey_Main
 *
 * Class for Surveys
 *
 * Adding a new answer type
 * ------------------------
 * 1. Go to /library/survey/answerType and /library/survey/questionType
 *
 * 2. Copy the short.php files for both folders and rename them so they match
 * the 'filename' value in the QuestionType lookup table.
 *
 * 3. Rename the classes to also match a ucwords()'d version of 'filename'. e.g.
 *      class SurveyAnswerMatch extends SurveyAnswer { ...
 *      class SurveyAnswerShort extends SurveyAnswer { ...
 *
 * 4. You'll need to do the same for AssessQuestion as well. These classes are
 * empty, but are provided in case you need a special way to render questions.
 *
 * 5. Go to /templates/core/survey/answer and copy the files in 'short'. You'll
 * again use the short answer as a template to modify to suit your needs.
 *
 * 6. Edit the create.html form to suit.
 *
 * 7. Edit the answer handler to suit.
 *
 * 8. Edit the modify.html form to suit.
 *        -    Don't forget that type_id is a <select> element in create.html, but
 *            a hidden input in modify.html (because you can't change question
 *            types once a question has been created).
 *        -    Also note that modify.html needs to pass question_id, which is not
 *            provided/required in create.html
 *
 * 9. If you need the template to be different when taking the question vs. when
 * creating/editing the question, you can create a third template file called
 * 'answer.html' which will be displayed when someone is attempting the
 * question. This is used to reformat a question; e.g. matching pairs: changing
 * the inputs to readonly; fillgaps: changing <span></span> elements to <input>s.
 */
class Survey_Main extends Evaluation_Main
{
    const ONLINE_ASSESSMENT_TYPE_ID = 11;
    const UNLIMITED_DURATION = -1;


    /**
     * Determines whether the current user can take a test by checking which
     * categories the current user belongs to.
     *
     * @param int $assess_id
     * @return bool
     */
    public function canDoAssessment($assess_id)
    {
        $groupHandler = new Group();
        $assess = $this->getByID($assess_id);

        return $groupHandler->getPermission($assess['category_id']) >= UserAccess::ACCESS_READ;
    }


    /**
     * A list of assessments available for a category
     *
     * @param int|array $category_id
     * @param bool $limit
     * @return array|bool
     */
    public function getList($category_id, $limit = false)
    {
        if (is_array($category_id)) {
            $categoryIDList = implode(',', $category_id);

            $query = "SELECT *
                      FROM {$this->_table}
                      WHERE category_id IN ($categoryIDList)
                      AND availability = 'survey'
                      AND deleted_at IS NULL
                      ORDER BY created_at DESC";
        } else {
            $query = "SELECT *
                      FROM {$this->_table}
                      WHERE category_id = " . $this->db->smartQuote($category_id) . "
                      AND availability = 'survey'
                      AND deleted_at IS NULL
                      ORDER BY created_at DESC";
        }

        if (is_numeric($limit)) {
            $query .= " LIMIT $limit";
        }

        return $this->db->getAll($query);
    }


    /**
     * Soft-deletes content
     *
     * @param int $category_id
     * @return void
     */
    public function homepageClean($category_id)
    {
        $query = "SELECT assess_id FROM assess WHERE category_id = '{$category_id}' AND availability = 'survey'";

        $allAssess = $this->db->getAll($query);

        if (is_array($allAssess) && count($allAssess)) {
            /* Get a list of questions and answers to softdelete
             */
            $assessHandler = new Survey_Main();
            $questionHandler = new Survey_Question();
            $answerHandler = new Survey_Answer();
            $sectionHandler = new Survey_Section();

            /* Shamelessly stolen from delete.php and squished
             * into position here
             */
            foreach ($allAssess as $currentAssess) {
                $sectionList = $sectionHandler->getList($currentAssess['assess_id']);

                if (is_array($sectionList) && count($sectionList)) {
                    foreach ($sectionList as $currentSection) {
                        $section = $sectionHandler->getByID($currentSection['section_id']);
                        $questionList = $questionHandler->getList($currentSection['section_id']);

                        if (is_array($questionList) && count($questionList)) {
                            foreach ($questionList as $currentQuestion) {
                                $answer = $answerHandler->getForQuestion($currentQuestion['question_id']);

                                /* Perform soft delete
                                         */
                                $questionHandler->update(array(
                                    'question_id' => $currentQuestion['question_id'],
                                    'deleted_at' => 'NOW()',
                                ));

                                $answerHandler->update(array(
                                    'answer_id' => $answer['answer_id'],
                                    'deleted_at' => 'NOW()',
                                ));
                            }
                        }

                        $section['deleted_at'] = 'NOW()';
                        $sectionHandler->update($section);
                    }
                }

                /* Remove assess
                 */
                $assessHandler->update(array(
                    'assess_id' => $currentAssess['assess_id'],
                    'deleted_at' => 'NOW()'
                ));
            }
        }
    }


    /**
     * @return string
     */
    protected function getAvailabilityType()
    {
        return Quiz::AVAILABILITY_SURVEY;
    }
}
