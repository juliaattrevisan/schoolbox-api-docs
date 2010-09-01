<?php
require_once '../include/model.php';

/**
 * Description of Assess
 *
 * @author aetworowsky
 */
class Assess extends Model {
    var $_table = 'assess';
    var $_index = 'assess_id';

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
            }

            /* Put the current Question into a new Section and parse it out
             */
            $page->setVar(array(
                'QUESTION_SEQUENCE' => $currentQuestion['questionSequence'],
                'QUESTION_QUESTION' => $currentQuestion['question'],
                'QUESTION_QUESTION_ID' => $currentQuestion['question_id'],
            ));

            $page->parse('question_block', 'question', true);
        }

        /* Finally, parse out this Section's info
         *
         * Dont' render question template for empty sections
         */
        if (!$numQuestions) {
            $page->setVar('question_block', '');
        }

        $page->setVar(array(
            'SECTION_TITLE' => $currentQuestion['title'],
            'SECTION_SEQUENCE' => $currentQuestion['sectionSequence'],
            'SECTION_SECTION_ID' => $currentQuestion['section_id'],
        ));

        $page->parse('section_block', 'section', true);
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
                         assess_question.sequence AS questionSequence
                  FROM assess_section
                  LEFT JOIN assess_question ON assess_section.section_id = assess_question.section_id
                  WHERE assess_id = '$assess_id'
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
}
?>
