<?php
/**
 * Evaluation_Section
 *
 * All Sections are managed here.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
abstract class Evaluation_Section extends DeprecatedModel
{
    public $_table = 'assess_section';
    public $_index = 'section_id';

    /* A const to determine whether to include sections in getContents
     */
    const SECTION_EXCLUDE = true;

    /**
     * Creates a new section.
     *
     * @param  $section
     * @return int
     */
    public function create($section)
    {
        return parent::create($section);
    }

    /**
     * For a new Section, find out what its sequence should be. If it's the
     * first Section for an Assess, return 0
     *
     * @param  $assess_id
     * @return int
     */
    public function getNextSequence($assess_id)
    {
        $query = "SELECT MAX(sequence)+1 AS num
                  FROM {$this->_table}
                  WHERE assess_id = " . $this->db->smartQuote($assess_id) . "
                  AND deleted_at IS NULL";

        $sequence = $this->db->getOne($query);

        if ($sequence) {
            return $sequence;
        }

        return 1;
    }

    /**
     * Gets the Section that comes after $section_id
     *
     * @param  $assess_id
     * @param  $sequence
     * @return array|bool
     */
    public function getAfter($assess_id, $sequence)
    {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE assess_id = " . $this->db->smartQuote($assess_id) . "
                  AND sequence > " . $this->db->smartQuote($sequence) . "
                  AND deleted_at IS NULL
                  ORDER BY sequence
                  LIMIT 1";

        return $this->db->getRow($query);
    }

    /**
     * Gets the Section that comes before $section_id
     *
     * @param  $assess_id
     * @param  $sequence
     * @return array|bool
     */
    public function getBefore($assess_id, $sequence)
    {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE assess_id = " . $this->db->smartQuote($assess_id) . "
                  AND sequence < " . $this->db->smartQuote($sequence) . "
                  AND deleted_at IS NULL
                  ORDER BY sequence DESC
                  LIMIT 1";

        return $this->db->getRow($query);
    }

    /**
     * Gets a list of Sections and Questions that exist in a particular Assess.
     * Optionally exclude Sections when just getting Questions for a section,
     * rather than a full index.
     *
     * @param  $assess_id
     * @param  $section_id
     * @param bool $sectionExclusion
     * @param bool $question_id
     * @return array|bool
     */
    public function getContents($assess_id, $section_id, $sectionExclusion = false, $question_id = false)
    {
        $query = "SELECT assess_id,
                         assess_section.section_id,
                         assess_section.title,
                         assess_section.sequence AS sectionSequence,
                         assess_question.*
                  FROM {$this->_table}
                  INNER JOIN assess_question ON assess_section.section_id = assess_question.section_id
                  WHERE assess_id = " . $this->db->smartQuote($assess_id) . "
                  AND assess_section.deleted_at IS NULL
                  AND assess_question.deleted_at IS NULL
                  AND {$this->_table}.{$this->_index} = " . $this->db->smartQuote($section_id);

        if ($sectionExclusion) {
            $query .= " AND question_id IS NOT NULL";
        }

        /* Optionally filter by question
           */
        if ($question_id) {
            $query .= " AND question_id = '$question_id'";
        }

        $query .= " ORDER BY sectionSequence, assess_question.sequence";

        return $this->db->getAll($query);
    }

    /**
     * Get a list of questions for a particular attempt and section
     *
     * If random was selected for this section, get the order in which
     * the randomised questions were chosen; otherwise use the question
     * sequence for ordering
     *
     * @param  $attempt_id
     * @param  $section_id
     * @return array|bool
     */
    public function getQuestions($attempt_id, $section_id)
    {
        $query = "SELECT
        			  assess_response.section_id,
                      assess_response.question_id,
                      assess_question.type_id,
                      assess_question.sequence,
                      assess_question.marks,
                      assess_question.question,
                      assess_luQuestionType.plugin
                  FROM assess_response
                  LEFT JOIN assess_question ON assess_response.question_id = assess_question.question_id
                  LEFT JOIN assess_luQuestionType ON assess_question.type_id = assess_luQuestionType.type_id
                  LEFT JOIN assess_section ON assess_question.section_id = assess_section.section_id
                  WHERE assess_response.attempt_id = " . $this->db->smartQuote($attempt_id) . "
                  AND assess_response.section_id = " . $this->db->smartQuote($section_id) . "
                  AND assess_question.deleted_at IS NULL
                  ORDER BY IF(assess_section.random = '1', assess_response.response_id, assess_question.sequence)";

        return $this->db->getAll($query);
    }

    /**
     * Get a list of Sections for an Assess.
     *
     * @param  $assess_id
     * @return array|bool
     */
    public function getList($assess_id)
    {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE assess_id = " . $this->db->smartQuote($assess_id) . "
                  AND deleted_at IS NULL
                  ORDER BY sequence";

        return $this->db->getAll($query);
    }

    /**
     * Gets the first Section in this Assess.
     *
     * @param  $assess_id
     * @return
     */
    public function getFirst($assess_id)
    {
        $allSections = $this->getList($assess_id);

        return $allSections[0];
    }

    /**
     * Find out how much of a section was completed.
     *
     * @param  $section_id
     *
     * @return array
     */
    public function sectionPercentageComplete($section_id)
    {
        $section = $this->getByID($section_id);
        $sectionList = $this->getList($section['assess_id']);

        foreach ($sectionList as $currentSection) {
            $numQuestions = "SELECT
                                 COUNT(assess_response.question_id) AS num
                             FROM assess_response
                             LEFT JOIN assess_section on assess_section.section_id = assess_response.section_id
                             LEFT JOIN assess_question on assess_response.question_id = assess_question.question_id
                             WHERE attempt_id = '$_SESSION[attempt_id]'
                             AND assess_response.section_id = '$currentSection[section_id]'
                             AND assess_section.deleted_at IS NULL
                             AND assess_question.deleted_at IS NULL";

            $numResponses = $numQuestions;
            $numResponses .= " AND assess_response.response IS NOT NULL";
            $numResponses .= " AND assess_response.response != 'N;'";
            $numResponses .= " AND assess_response.response != ''";

            $currentSection['numQuestions'] = $this->db->getOne($numQuestions);
            $currentSection['numResponses'] = $this->db->getOne($numResponses);

            if ($currentSection['numQuestions'] > 0) {
                $currentSection['percentageComplete'] = round(100 * $currentSection['numResponses'] / $currentSection['numQuestions']);
            } else {
                $currentSection['percentageComplete'] = 0;
            }

            $currentSection['sequence'] = $currentSection['sequence'];

            $completion[] = $currentSection;
        }

        return $completion;
    }

    /**
     * Resequences sections after one has been deleted.
     *
     * @param  $assess_id
     * @return void
     */
    public function resequenceSectionsAfterDelete($assess_id)
    {
        $sectionList = $this->getList($assess_id);

        if (is_array($sectionList) && count($sectionList)) {
            $newSequence = 1;

            foreach ($sectionList as $currentSection) {
                $updatedSection = array(
                    'section_id' => $currentSection['section_id'],
                    'sequence' => $newSequence,
                );

                parent::update($updatedSection);

                $newSequence++;
            }
        }
    }

    /**
     * Find out whether this assessment has been deleted.
     *
     * @param  $section_id
     * @return bool
     */
    public function isDeleted($section_id)
    {
        $query = "SELECT COUNT(section_id)
                  FROM assess_section
                  WHERE section_id = " . $this->db->smartQuote($section_id) . "
                  AND deleted_at IS NULL";

        $result = $this->db->getOne($query);

        if (!$result) {
            return true;
        }

        return false;
    }
}
