<?php
/**
 * Assess class
 */
require_once '../include/model.php';

class Assess extends Model {
    var $_table = 'assess';
    var $_index = 'assess_id';

    const ONLINE_ASSESSMENT_TYPE_ID = 11;
    const UNLIMITED_DURATION = -1;

    /**
     * A list of assessments available for a category
     *
     * @param <type> $category_id
     */
    function getList($category_id) {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE category_id = '$category_id'
                  AND deleted_at IS NULL
                  ORDER BY finish";

        return $this->db->getAll($query);
    }

    /**
     * Get a list of assessments to display on the listSubmission page at
     * /submission/listSubmission
     *
     * @param <type> $category_id
     * @return <type>
     */
    function getSubmissionList($category_id) {
        $query = "SELECT
                      assess_id, title, category_id,
                      DATE_FORMAT(start, '%a, %D %b %l:%i%p') AS start,
                      DATE_FORMAT(finish, '%a, %D %b %l:%i%p') AS finish,
                      file_type.name AS type
                  FROM assess
                  LEFT JOIN file_type ON assess.type = file_type.id
                  WHERE category_id = '$category_id'
                  AND deleted_at IS NULL";

        return $this->db->getAll($query);
    }

    /**
     * Get an introduction to an Assess.
     *
     * @param <type> $assess_id
     * @return <type>
     */
    function getIntro($assess_id) {
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
    function getIntroSummary($assess_id) {
        $query = "SELECT
                     assess_section.title,
                     assess_section.sequence,
                     COUNT(assess_section.section_id) AS numQuestions
                  FROM assess_section
                  LEFT JOIN assess_question ON assess_section.section_id = assess_question.section_id
                  WHERE assess_section.assess_id = '$assess_id'
                  AND assess_section.deleted_at IS NULL
                  GROUP BY assess_section.section_id
                  ORDER BY assess_section.sequence";

        return $this->db->getAll($query);
    }

    /**
     * Get a list of Sections and their Questions for a particular Assess.
     * @param <type> $assess_id
     */
    function renderAssessContents($assess_id, $page) {
        $allQuestions = $this->getContents($assess_id);

        $sectionTitle = $allQuestions[0]['title'];

        for ($i=0, $numQuestions=0; $i < count($allQuestions); $i++) {
            $currentQuestion = $allQuestions[$i];

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
     * @param <type> $section_id
     * @return <type>
     */
    function getContents($assess_id) {
        $query = "SELECT assess_id,
                         assess_section.section_id,
                         assess_section.title,
                         assess_section.sequence AS sectionSequence,
                         question_id,
                         question,
                         marks,
                         assess_question.sequence AS questionSequence
                  FROM assess_section
                  LEFT JOIN assess_question ON assess_section.section_id = assess_question.section_id
                  WHERE assess_id = '$assess_id'
                  AND assess_section.deleted_at IS NULL
                  AND assess_question.deleted_at IS NULL
                  ORDER BY sectionSequence, questionSequence";

        return $this->db->getAll($query);
    }

    /**
     * Clears the db.
     */
    function reset() {
        $this->db->query("DELETE FROM assess");
        $this->db->query("DELETE FROM assess_answer");
        $this->db->query("DELETE FROM assess_attempt");
        $this->db->query("DELETE FROM assess_question");
        $this->db->query("DELETE FROM assess_response");
        $this->db->query("DELETE FROM assess_section");
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
    function sec2hms($sec, $padHours = false)  {
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
    function parse_sec2hms($val) {
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
    function allowResit($assess_id) {
        $query = "SELECT allow_resit
                  FROM {$this->_table}
                  WHERE assess_id = '$assess_id'";

        return $this->db->getOne($query);
    }
}
?>
