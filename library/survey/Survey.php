<?php
/**
 * Survey
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
 *		-	Don't forget that type_id is a <select> element in create.html, but
 *			a hidden input in modify.html (because you can't change question
 *			types once a question has been created).
 *		-	Also note that modify.html needs to pass question_id, which is not
 *			provided/required in create.html
 *
 * 9. If you need the template to be different when taking the question vs. when
 * creating/editing the question, you can create a third template file called
 * 'answer.html' which will be displayed when someone is attempting the
 * question. This is used to reformat a question; e.g. matching pairs: changing
 * the inputs to readonly; fillgaps: changing <span></span> elements to <input>s.
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/Evaluation.php');

class Survey extends Evaluation {
    const ONLINE_ASSESSMENT_TYPE_ID = 11;
    const UNLIMITED_DURATION = -1;

    /**
     * A list of assessments available for a category
     *
     * @param <type> $category_id
     */
    public function getList($category_id, $limit = false) {
        if (is_array($category_id)) {
            $categoryIDList = implode(',', $category_id);

            $query = "SELECT *
                      FROM {$this->_table}
                      WHERE category_id IN ($categoryIDList)
                      AND availability = 'survey'
                      AND deleted_at IS NULL
                      ORDER BY finish";
        }
        else {
            $query = "SELECT *
                      FROM {$this->_table}
                      WHERE category_id = '$category_id'
                      AND availability = 'survey'
                      AND deleted_at IS NULL
                      ORDER BY finish";
        }

		if (is_numeric($limit)) {
			$query .= " LIMIT $limit";
		}

        return $this->db->getAll($query);
    }
}
