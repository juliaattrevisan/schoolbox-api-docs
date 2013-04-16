<?php
/**
 * Assess_Main
 *
 * Class for Online Assessments
 *
 * Adding a new answer type
 * ------------------------
 * 1. Go to /library/assess/answerType and /library/assess/questionType
 *
 * 2. Copy the short.php files for both folders and rename them so they match
 * the 'filename' value in the QuestionType lookup table.
 *
 * 3. Rename the classes to also match a ucwords()'d version of 'filename'. e.g.
 *      class AssessAnswerMatch extends AssessAnswer { ...
 *      class AssessAnswerShort extends AssessAnswer { ...
 *
 * 4. You'll need to do the same for AssessQuestion as well. These classes are
 * empty, but are provided in case you need a special way to render questions.
 *
 * 5. Go to /templates/core/assess/answer and copy the files in 'short'. You'll
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
class Assess_Main extends Evaluation_Main {
    public $_table = 'assess';
    public $_index = 'assess_id';

    const ONLINE_ASSESSMENT_TYPE_ID = 11;
    const UNLIMITED_DURATION = -1;

	/**
	 * A list of assessments available for a category
	 *
	 * @param  $category_id
	 * @param bool $limit
	 * @return array|bool
	 */
    public function getList($category_id, $limit = false, $year = 0, $semester = 0) {

        if (is_array($category_id)) {
            $categoryIDList = implode(',', $category_id);

            $query = "SELECT *
                      FROM {$this->_table}
                      WHERE category_id IN ($categoryIDList)";
        }
        else {
            $query = "SELECT *
                      FROM {$this->_table}
                      WHERE category_id = '$category_id'";
        }

        if($year) {
            $query .= " AND DATE_FORMAT(start, '%Y') = $year";
        }

        if($semester) {
            $query .= " AND DATE_FORMAT(start, '%m') " . DueWork_All::getSemesterLogic($semester);
        }

        $query .= " AND availability = 'assess'
                    AND deleted_at IS NULL
                    ORDER BY created_at DESC";

		if (is_numeric($limit)) {
			$query .= " LIMIT $limit";
		}

        return $this->db->getAll($query);
    }

	/**
	 * Soft-deletes content
	 *
	 * @param  $category_id
	 * @return void
	 */
	public function homepageClean($category_id) {
		$query = "SELECT assess_id FROM assess WHERE category_id = '{$category_id}' AND availability = 'assess'";

		$allAssess = $this->db->getAll($query);

		if (is_array($allAssess) && count($allAssess)) {

			/* Get a list of questions and answers to softdelete
			 */
			$assessHandler = new Assess_Main();
			$questionHandler = new Assess_Question();
			$answerHandler = new Assess_Answer();
			$sectionHandler = new Assess_Section();

			/* Shamelessly stolen from delete.php and squished
			 * into position here
			 */
			foreach($allAssess as $currentAssess) {
				$sectionList = $sectionHandler->getList($currentAssess['assess_id']);

				if (is_array($sectionList) && count($sectionList)) {
					foreach($sectionList as $currentSection) {
						$section = $sectionHandler->getByID($currentSection['section_id']);
						$questionList = $questionHandler->getList($currentSection['section_id']);

						if (is_array($questionList) && count($questionList)) {
							foreach($questionList as $currentQuestion) {
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
	 * Retrieves the name of the assessment type for online assessments.
	 *
	 * @return mixed
	 */
	public function getAssessmentType() {
		return $this->db->getOne(
			"SELECT name FROM file_type WHERE id = " . $this->db->smartQuote(self::ONLINE_ASSESSMENT_TYPE_ID)
		);
	}
}
