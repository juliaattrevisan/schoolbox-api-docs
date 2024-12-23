<?php

/**
 * Evaluation_Section
 *
 * All Sections are managed here.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Evaluation_Section extends DeprecatedModel
{
    /**
     * POOL_SIZE_ALL Indicates to use all questions in section
     */
    public const POOL_SIZE_ALL = 0;

    /**
     * @var string
     */
    public $_table = 'assess_section';

    /**
     * @var string
     */
    public $_index = 'section_id';

    /**
     * Creates a new section.
     *
     * @param array $section
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
     * @param int $assess_id
     * @return int
     */
    public function getNextSequence($assess_id)
    {
        $query = "SELECT MAX(sequence)+1 AS num
                  FROM {$this->_table}
                  WHERE assess_id = ?
                  AND deleted_at IS NULL";

        $sequence = $this->db->query($query, [$assess_id])->fetchColumn();

        if ($sequence) {
            return (int)$sequence;
        }

        return 1;
    }

    /**
     * Gets the Section that comes after $section_id
     *
     * @param int $assess_id
     * @param int $sequence
     * @return array|bool
     */
    public function getAfter($assess_id, $sequence)
    {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE assess_id = ?
                  AND sequence > ?
                  AND deleted_at IS NULL
                  ORDER BY sequence
                  LIMIT 1";

        return $this->db->query($query, [$assess_id, $sequence])->fetch();
    }

    /**
     * Gets the Section that comes before $section_id
     *
     * @param int $assess_id
     * @param int $sequence
     * @return array|bool
     */
    public function getBefore($assess_id, $sequence)
    {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE assess_id = ?
                  AND sequence < ?
                  AND deleted_at IS NULL
                  ORDER BY sequence DESC
                  LIMIT 1";

        return $this->db->query($query, [$assess_id, $sequence])->fetch();
    }

    /**
     * Gets a list of Sections and Questions that exist in a particular Assess.
     * Optionally exclude Sections when just getting Questions for a section,
     * rather than a full index.
     *
     * @param int $assess_id
     * @param int $section_id
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
                  WHERE assess_id = ?
                  AND assess_section.deleted_at IS NULL
                  AND assess_question.deleted_at IS NULL
                  AND {$this->_table}.{$this->_index} = ?";
        $params = [$assess_id, $section_id];

        if ($sectionExclusion) {
            $query .= " AND question_id IS NOT NULL";
        }

        /* Optionally filter by question
         */
        if ($question_id) {
            $query .= " AND question_id = ?";
            $params[] = $question_id;
        }

        $query .= " ORDER BY sectionSequence, assess_question.sequence";

        return $this->db->query($query, $params)->fetchAll();
    }

    /**
     * Get a list of questions for a particular attempt and section
     *
     * If random was selected for this section, get the order in which
     * the randomised questions were chosen; otherwise use the question
     * sequence for ordering
     *
     * @param int $attempt_id
     * @param int $section_id
     * @return array|bool
     */
    public function getQuestions($attempt_id, $section_id)
    {
        $query = "SELECT
                      assess_question.section_id,
                      assess_response.question_id,
                      assess_question.type_id,
                      assess_question.sequence,
                      assess_question.marks,
                      assess_question.question,
                      assess_question.config
                  FROM assess_response
                  LEFT JOIN assess_question ON assess_response.question_id = assess_question.question_id
                  LEFT JOIN assess_luQuestionType ON assess_question.type_id = assess_luQuestionType.type_id
                  LEFT JOIN assess_section ON assess_question.section_id = assess_section.section_id
                  WHERE assess_response.attempt_id = ?
                  AND assess_question.section_id = ?
                  AND assess_response.deleted_at IS NULL
                  AND assess_question.deleted_at IS NULL
                  ORDER BY IF(assess_section.random = '1', assess_response.response_id, assess_question.sequence)";

        return $this->db->query($query, [$attempt_id, $section_id])->fetchAll();
    }

    /**
     * Get a list of Sections for an Assess.
     *
     * @param int $assess_id
     * @return array|bool
     */
    public function getList($assess_id)
    {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE assess_id = ?
                  AND deleted_at IS NULL
                  ORDER BY sequence";

        return $this->db->query($query, [$assess_id])->fetchAll();
    }

    /**
     * Gets the first Section in this Assess.
     *
     * @param int $assess_id
     * @return array|null
     */
    public function getFirst($assess_id)
    {
        $allSections = $this->getList($assess_id);

        return $allSections[0] ?? null;
    }

    /**
     * Find out how much of a section was completed.
     *
     * @param int $section_id
     * @param int $attemptId
     * @return array
     */
    public function sectionPercentageComplete($section_id, $attemptId)
    {
        $section = $this->getByID($section_id);
        $sectionList = $this->getList($section['assess_id']);

        $completion = [];
        foreach ($sectionList as $currentSection) {
            $numQuestions = "SELECT
                                 COUNT(assess_response.question_id) AS num
                             FROM assess_response
                             LEFT JOIN assess_question on assess_response.question_id = assess_question.question_id
                             LEFT JOIN assess_section on assess_section.section_id = assess_question.section_id
                             WHERE attempt_id = ?
                             AND assess_section.section_id = ?
                             AND assess_response.deleted_at IS NULL
                             AND assess_section.deleted_at IS NULL
                             AND assess_question.deleted_at IS NULL";
            $params = [$attemptId, $currentSection['section_id']];

            $numResponses = $numQuestions;
            $numResponses .= " AND assess_response.response IS NOT NULL";
            $numResponses .= " AND assess_response.response != 'N;'";
            $numResponses .= " AND assess_response.response != ''";

            $currentSection['numQuestions'] = (int)$this->db->query($numQuestions, $params)->fetchColumn();
            $currentSection['numResponses'] = (int)$this->db->query($numResponses, $params)->fetchColumn();

            if ($currentSection['numQuestions'] > 0) {
                $currentSection['percentageComplete'] = round(100 * $currentSection['numResponses'] / $currentSection['numQuestions']);
            } else {
                $currentSection['percentageComplete'] = 0;
            }

            $completion[] = $currentSection;
        }

        return $completion;
    }

    /**
     * Resequences sections after one has been deleted.
     *
     * @param int $assess_id
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
     * @param int $section_id
     * @return bool
     */
    public function isDeleted($section_id)
    {
        $query = "SELECT COUNT(section_id)
                  FROM assess_section
                  WHERE section_id = ?
                  AND deleted_at IS NULL";

        $result = $this->db->query($query, [$section_id])->fetchColumn();

        if (!$result) {
            return true;
        }

        return false;
    }
}
