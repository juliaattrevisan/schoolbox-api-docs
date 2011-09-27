<?php
/**
 * Evaluation
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
abstract class Evaluation extends DeprecatedModel {
    public $_table = 'assess';
    public $_index = 'assess_id';

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
                      AND deleted_at IS NULL
                      ORDER BY finish";
        }
        else {
            $query = "SELECT *
                      FROM {$this->_table}
                      WHERE category_id = '$category_id'
                      AND deleted_at IS NULL
                      ORDER BY finish";
        }

		if (is_numeric($limit)) {
			$query .= " LIMIT $limit";
		}

        return $this->db->getAll($query);
    }

    /**
     * Get an introduction to an Assess.
     *
     * @param <type> $assess_id
     * @return <type>
     */
    public function getIntro($assess_id) {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE {$this->_index} = '$assess_id'";

        return $this->db->getRow($query);
    }

    /**
     * Loads the summary for the test
     *
     * @param <type> $assess_id
     */
    public function getIntroSummary($assess_id) {
        $query = "SELECT
                     assess_section.section_id,
                     assess_section.title,
                     assess_section.sequence,
                     COUNT(assess_section.section_id) AS numQuestions
                  FROM assess_section
                  LEFT JOIN assess_question ON assess_section.section_id = assess_question.section_id
                  WHERE assess_section.assess_id = '$assess_id'
                  AND assess_section.deleted_at IS NULL
				  AND assess_question.deleted_at IS NULL
                  GROUP BY assess_section.section_id
                  ORDER BY assess_section.sequence";

        return $this->db->getAll($query);
    }

    /**
     * Get a list of Sections and their Questions for a particular Assess.
     *
     * @param <type> $assess_id
     */
    public function renderAssessContents($assess_id, $page) {
        $allQuestions = $this->getContents($assess_id);

        $sectionTitle = $allQuestions[0]['title'];

        for ($i=0, $numQuestions=0; $i < count($allQuestions); $i++) {
            $currentQuestion = $allQuestions[$i];

			/* If section contains a deleted question, $allQuestions query is
			 * empty. So we actually need to include the deleted questions to
			 * get all the sections contained deleted questions.
			 *
			 * This if() checks whether the question is deleted, and if it has
			 * just continue with everyone else
			 */
			if (!empty($currentQuestion['questionDeletedAt'])) {
				continue;
			}

            if (is_numeric($currentQuestion['question_id'])) {
                $numQuestions++;
            }

            /* Parse out the previous Section's info if we're entering a new Section
             */
            if ($currentQuestion['title'] != $sectionTitle) {
                $page->setVar(array(
                    'SECTION_TITLE' => $allQuestions[$i-1]['title'],
                    'SECTION_SEQUENCE' => $allQuestions[$i-1]['sectionSequence'],
                    'SECTION_SECTION_ID' => $allQuestions[$i-1]['section_id'],
                ));

                $page->parse('section_block', 'section', true);
                $page->setVar('question_block', '');

                $sectionTitle = $currentQuestion['title'];
            }

            /* Put the current Question into a new Section and parse it out. If
             * it's empty, just hide it
             */
            if (is_numeric($currentQuestion['question_id'])) {
                $page->setVar(array(
                    'QUESTION_SEQUENCE' => $currentQuestion['questionSequence'],
                    'QUESTION_QUESTION' => $currentQuestion['question'],
                    'QUESTION_QUESTION_ID' => $currentQuestion['question_id'],
                    'QUESTION_MARKS' => $currentQuestion['marks'],
                ));

                $page->parse('question_block', 'question', true);
            }
            else {
                $page->setVar('question_block', '', true);
            }
        }

        /* Finally, parse out this Section's info
         *
         * Don't render question template for empty sections
         */
        if (!$numQuestions) {
            $page->setVar('question_block', '');
        }

        if (is_numeric($currentQuestion['section_id'])) {
            $page->setVar(array(
                'SECTION_TITLE' => $currentQuestion['title'],
                'SECTION_SEQUENCE' => $currentQuestion['sectionSequence'],
                'SECTION_SECTION_ID' => $currentQuestion['section_id'],
            ));

            $page->parse('section_block', 'section', true);
        }
        else {
            $page->setVar('section_block', '');
        }
    }

    /**
     * Gets a list of Questions that exist in a particular Assess. This is used
     * on the create question screens as a table of contents.
     *
     * TODO: Sections disappear when their questions are all deleted?
     *
     * @param <type> $section_id
     * @return <type>
     */
    public function getContents($assess_id) {
        $query = "SELECT assess_id,
                         assess_section.section_id,
                         assess_section.title,
                         assess_section.sequence AS sectionSequence,
                         question_id,
                         question,
                         type_id,
                         marks,
                         assess_question.sequence AS questionSequence,
						 assess_question.deleted_at AS questionDeletedAt
                  FROM assess_section
                  LEFT JOIN assess_question ON assess_section.section_id = assess_question.section_id
                  WHERE assess_id = '$assess_id'
                  AND assess_section.deleted_at IS NULL
                  ORDER BY sectionSequence, questionSequence";

        return $this->db->getAll($query);
    }

    /**
     * Display seconds as hours, minutes and seconds. Yoinked from
     * http://www.laughing-buddha.net/php/lib/sec2hms/
     *
     * but slightly modified for formatting.
     *
     * @param <type> $sec
     * @param <type> $padHours
     * @return <type>
     */
    public function sec2hms($sec, $padHours = false)  {
        // start with a blank string
        $hms = "";

        // do the hours first: there are 3600 seconds in an hour, so if we divide
        // the total number of seconds by 3600 and throw away the remainder, we're
        // left with the number of hours in those seconds
        $hours = intval(intval($sec) / 3600);

        // add hours to $hms (with a leading 0 if asked for)
        $hms .= ($padHours)
              ? str_pad($hours, 2, "0", STR_PAD_LEFT). ":"
              : $hours. "hrs ";

        // dividing the total seconds by 60 will give us the number of minutes
        // in total, but we're interested in *minutes past the hour* and to get
        // this, we have to divide by 60 again and then use the remainder
        $minutes = intval(($sec / 60) % 60);

        // add minutes to $hms (with a leading 0 if needed)
        $hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT). "min ";

        // seconds past the minute are found by dividing the total number of seconds
        // by 60 and using the remainder
        $seconds = intval($sec % 60);

        // add seconds to $hms (with a leading 0 if needed)
        $hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);

        $hms .= 'sec';

        // done!
        return $hms;
    }

    /**
     * Extract hours, minutes and seconds from a sec2hms value. Used to evaluate
     * duration of assessments, converting from duration entirely in seconds.
     *
     * @param <type> $val
     * @return <type>
     */
    public function parse_sec2hms($val) {
        preg_match('/(\d+)hrs\ (\d+)min (\d+)sec/', $val, $matches);

        $formatted['hours'] = $matches[1];
        $formatted['minutes'] = $matches[2];
        $formatted['seconds'] = $matches[3];

        return $formatted;
    }

    /**
     * Find out if an assessment can be re-sat.
     *
     * @param <type> $assess_id
     */
    public function allowResit($assess_id) {
        $query = "SELECT allow_resit
                  FROM {$this->_table}
                  WHERE assess_id = '$assess_id'";

        return $this->db->getOne($query);
    }

    /**
     * Finds out if the specified user owns the assessment.
     *
     * @param <type> $assess_id
     * @param <type> $user_id
     * @return <type>
     */
    public function isOwner($assess_id, $user_id = '') {
        if (!is_numeric($user_id)) {
            $uid = $_SESSION['id'];
        }
        else {
            $uid = $user_id;
        }

        $query = "SELECT COUNT(*)
                  FROM {$this->_table}
                  WHERE assess_id = '$assess_id'
                  AND user_id = '$uid'";

        return $this->db->getOne($query);
    }

    /**
     * Determines whether the current user can take a test by checking which
     * categories the current user belongs to.
     *
     * @return <type>
     */
    public function canDoAssessment($assess_id) {
        $groupHandler = new Group();
        $assess = $this->getByID($assess_id);

        $categoryList = $groupHandler->involvedInCategoryList($_SESSION['id']);

		/* See if you're in the Category first
		 */
		if (is_array($categoryList)) {
			foreach ($categoryList as $key => $value) {
				if ($value['id'] == $assess['category_id']) {
					return true;
				}
			}
		}

        return false;
    }

    /**
     * Find out whether this assessment has expired.
     *
     * @param <type> $assess_id
     * @return <type>
     */
    public function isExpired($assess_id) {
        $query = "SELECT COUNT(assess_id)
                  FROM assess
                  WHERE assess_id = '$assess_id'
                  AND finish > NOW()";

        $result = $this->db->getOne($query);

        if (!$result) {
            return true;
        }

        return false;
    }

    /**
     * Find out whether this assessment has been deleted.
     *
     * @param <type> $assess_id
     * @return <type>
     */
    public function isDeleted($assess_id) {
        $query = "SELECT COUNT(assess_id)
                  FROM assess
                  WHERE assess_id = '$assess_id'
                  AND deleted_at IS NULL";

        $result = $this->db->getOne($query);

        if (!$result) {
            return true;
        }

        return false;
    }
}
