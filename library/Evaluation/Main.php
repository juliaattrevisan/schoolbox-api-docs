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
abstract class Evaluation_Main extends DeprecatedModel
{
    public $_table = 'assess';
    public $_index = 'assess_id';

    const ONLINE_ASSESSMENT_TYPE_ID = 11;
    const UNLIMITED_DURATION        = -1;

    /**
     * A list of assessments available for a category
     *
     * @param      $category_id
     * @param bool $limit
     *
     * @return array|bool
     */
    public function getList($category_id, $limit = false)
    {
        if (is_array($category_id)) {
            $categoryIDList = implode(',', $category_id);

            $query = "SELECT *
                      FROM {$this->_table}
                      WHERE category_id IN ($categoryIDList)
                      AND deleted_at IS NULL
                      ORDER BY finish";
        } else {
            $query = "SELECT *
                      FROM {$this->_table}
                      WHERE category_id = " . $this->db->smartQuote($category_id) . "
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
     * @param  $assess_id
     *
     * @return array|bool
     */
    public function getIntro($assess_id)
    {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE {$this->_index} = " . $this->db->smartQuote($assess_id);

        return $this->db->getRow($query);
    }

    /**
     * Loads the summary for the test
     *
     * @param  $assess_id
     *
     * @return array|bool
     */
    public function getIntroSummary($assess_id)
    {
        $query = "
            SELECT
                assess_section.section_id,
                assess_section.title,
                assess_section.sequence,
                assess_section.pool,
                SUM(IF (assess_question.deleted_at IS NULL AND assess_question.question_id IS NOT NULL, 1, 0)) AS numQuestions
            FROM assess_section
            LEFT JOIN assess_question ON assess_section.section_id = assess_question.section_id
            WHERE assess_section.assess_id = " . $this->db->smartQuote($assess_id) . "
            AND assess_section.deleted_at IS NULL
            GROUP BY assess_section.section_id
        ";

        return $this->db->getAll($query);
    }

    /**
     * Get a list of Sections and their Questions for a particular Assess.
     *
     * @param  int      $assess_id
     * @param  SitePage $page
     *
     * @return void
     */
    public function renderAssessContents($assess_id, $page)
    {
        $page->setBlock('assessContentsFragment', 'section', 'section_block');
        $page->setBlock('section', 'question', 'question_block');
        $page->setBlock('question', 'has_mark', 'has_mark_block');

        $allQuestions = $this->getContents($assess_id);

        $currentSection = $allQuestions[0]['section_id'];

        /* If there are no questions, remove template variable
           */
        $page->setVar('question_block', '');

        $j = 0;
        for ($i = 0, $questionNumber = 1; $i < count($allQuestions); $i++) {
            $currentQuestion = $allQuestions[$i];

            /* Parse out the previous Section's info if we're entering a new Section
             */
            if ($currentQuestion['section_id'] != $currentSection) {
                $questionNumber = 1;

                $page->setVar(
                    array(
                        'SECTION_TITLE'       => Scrub::text($allQuestions[$i - 1]['title']),
                        'SECTION_DESCRIPTION' => Scrub::html($allQuestions[$i - 1]['description']),
                        'SECTION_SEQUENCE'    => $allQuestions[$i - 1]['sectionSequence'],
                        'SECTION_SECTION_ID'  => $allQuestions[$i - 1]['section_id'],
                    )
                );

                $page->parse('section_block', 'section', true);
                $page->setVar('question_block', '');

                $currentSection = $currentQuestion['section_id'];
            }

            /* If section contains a deleted question, $allQuestions query is
             * empty. So we actually need to include the deleted questions to
             * get all the sections contained deleted questions.
             */

            /* Put the current Question into a new Section and parse it out. If
             * it's empty, just do nothing
             */
            if ($currentQuestion['questionDeletedAt'] == null) {
                if (is_numeric($currentQuestion['question_id'])) {
                    $page->setVar(
                        array(
                            'ON_OFF'               => ++$j % 2 ? '' : 'dark',
                            'QUESTION_SEQUENCE'    => $questionNumber,
                            'QUESTION_QUESTION'    => Scrub::html($currentQuestion['question']),
                            'QUESTION_QUESTION_ID' => $currentQuestion['question_id'],
                            'QUESTION_MARKS'       =>
                                floor($currentQuestion['marks']) < $currentQuestion['marks']
                                    ? $currentQuestion['marks']
                                    : floor($currentQuestion['marks']),
                        )
                    );

                    if ($page->getVar('ASSESS_KIND_LC') == 'survey') {
                        $page->setVar('has_mark_block', '');
                    } else {
                        $page->parse('has_mark_block', 'has_mark');
                    }

                    $page->parse('question_block', 'question', true);

                    $questionNumber++;
                }
            }

            /* A section with no questions in
             */
            if (!is_numeric($currentQuestion['question_id'])) {
                $page->setVar('question_block', '');
            }
        }

        /* Finally, parse out this Section's info
         */
        if (is_numeric($currentQuestion['section_id'])) {
            $page->setVar(
                array(
                    'SECTION_TITLE'       => Scrub::text($currentQuestion['title']),
                    'SECTION_DESCRIPTION' => Scrub::html($currentQuestion['description']),
                    'SECTION_SEQUENCE'    => $currentQuestion['sectionSequence'],
                    'SECTION_SECTION_ID'  => $currentQuestion['section_id'],
                )
            );

            $page->parse('section_block', 'section', true);
        } else {
            $page->setVar('section_block', '');
        }
    }

    /**
     * Gets a list of Questions that exist in a particular Assess. This is used
     * on the create question screens as a table of contents.
     *
     * @param  $assess_id
     *
     * @return array|bool
     */
    public function getContents($assess_id)
    {
        $query = "SELECT assess_id,
                         assess_section.section_id,
                         assess_section.title,
                         assess_section.description,
                         assess_section.sequence AS sectionSequence,
                         question_id,
                         question,
                         type_id,
                         marks,
                         assess_question.sequence AS questionSequence,
                         assess_question.deleted_at AS questionDeletedAt
                  FROM assess_section
                  LEFT JOIN assess_question ON assess_section.section_id = assess_question.section_id
                  WHERE assess_id = " . $this->db->smartQuote($assess_id) . "
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
     * @param      $sec
     * @param bool $padHours
     *
     * @return string
     */
    public function sec2hms($sec, $padHours = false)
    {
        // start with a blank string
        $hms = "";

        // do the hours first: there are 3600 seconds in an hour, so if we divide
        // the total number of seconds by 3600 and throw away the remainder, we're
        // left with the number of hours in those seconds
        $hours = intval(intval($sec) / 3600);

        // add hours to $hms (with a leading 0 if asked for)
        if($hours) {
            $hms .= ($padHours)
                ? str_pad($hours, 2, "0", STR_PAD_LEFT) . ":"
                : $hours . "hrs ";
        }

        // dividing the total seconds by 60 will give us the number of minutes
        // in total, but we're interested in *minutes past the hour* and to get
        // this, we have to divide by 60 again and then use the remainder
        $minutes = intval(($sec / 60) % 60);

        // add minutes to $hms (with a leading 0 if needed)
        if($minutes) {
            $hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT) . "min ";
        }

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
     * @param  $val
     *
     * @return array
     */
    public function parse_sec2hms($val)
    {
        preg_match('/(\d+)hrs\ (\d+)min (\d+)sec/', $val, $matches);

        $formatted['hours']   = $matches[1];
        $formatted['minutes'] = $matches[2];
        $formatted['seconds'] = $matches[3];

        return $formatted;
    }

    /**
     * Finds out if the specified user owns the assessment.
     *
     * @param        $assess_id
     * @param string $user_id
     *
     * @return mixed
     */
    public function isOwner($assess_id, $user_id = '')
    {
        if (!is_numeric($user_id)) {
            $uid = $_SESSION['id'];
        } else {
            $uid = $user_id;
        }

        $query = "SELECT COUNT(*)
                  FROM {$this->_table}
                  WHERE assess_id = " . $this->db->smartQuote($assess_id) . "
                  AND user_id = " . $this->db->smartQuote($uid);

        return $this->db->getOne($query);
    }

    /**
     * Determines whether the current user can take a test by checking which
     * categories the current user belongs to.
     *
     * @param  $assess_id
     *
     * @return bool
     */
    public function canDoAssessment($assess_id)
    {
        $groupHandler = new Group();
        $assess       = $this->getByID($assess_id);
        $perm         = $groupHandler->getPermission($assess['category_id']);

        // Users with write access can always do the assessment (generally teachers)
        if($perm >= WRITE_ACCESS) {
            return true;
        }

        // Students that are involved in the class can do the assessment, but not other students
        if($perm >= READ_ACCESS) {

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
        }
        return false;
    }

    /**
     * Find out whether this assessment has expired.
     *
     * @param  $assess_id
     *
     * @return bool
     */
    public function isExpired($assess_id)
    {
        $query = "SELECT COUNT(assess_id)
                  FROM assess
                  WHERE assess_id = " . $this->db->smartQuote($assess_id) . "
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
     * @param  $assess_id
     *
     * @return bool
     */
    public function isDeleted($assess_id)
    {
        $query = "SELECT COUNT(assess_id)
                  FROM assess
                  WHERE assess_id = " . $this->db->smartQuote($assess_id) . "
                  AND deleted_at IS NULL";

        $result = $this->db->getOne($query);

        if (!$result) {
            return true;
        }

        return false;
    }

    /**
     * Find out if this assess can be started
     *
     * @param  array $assess
     *
     * @return bool
     */
    public function isOpen($assess)
    {
        if (strtotime($assess['start']) <= time()) {
            return true;
        } else {
            return false;
        }
    }
}
