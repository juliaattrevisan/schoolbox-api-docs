<?php
require_once '../include/model.php';

class AssessSection extends Model {
    public $_table = 'assess_section';
    public $_index = 'section_id';

    /* A const to determine whether to include sections in getContents
     */
    const SECTION_EXCLUDE = true;

    /**
     * Creates a new section.
     *
     * @param <type> $section
     */
    function create($section) {
        return parent::create($section);
    }

    /**
     * For a new Section, find out what its sequence should be. If it's the
     * first Section for an Assess, return 0
     */
    function getNextSequence($assess_id) {
        $query = "SELECT MAX(sequence)+1 AS num
                  FROM {$this->_table}
                  WHERE assess_id = $assess_id";

        $sequence = $this->db->getOne($query);

        if ($sequence) {
            return $sequence['num'];
        }

        return 1;
    }

    /**
     * Gets the Section that comes after $section_id
     *
     * @param <type> $section_id
     */
    function getAfter($assess_id, $sequence) {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE assess_id = '$assess_id'
                  AND sequence > $sequence
                  ORDER BY sequence
                  LIMIT 1";

        return $this->db->getRow($query);
    }

    /**
     * Gets the Section that comes before $section_id
     *
     * @param <type> $section_id
     */
    function getBefore($assess_id, $sequence) {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE assess_id = '$assess_id'
                  AND sequence < $sequence
                  ORDER BY sequence DESC
                  LIMIT 1";

        return $this->db->getRow($query);
    }

    /**
     * Gets a list of Sections and Questions that exist in a particular Assess.
     * Optionally exclude Sections when just getting Questions for a section,
     * rather than a full index.
     *
     * @param <type> $section_id
     * @return <type>
     */
    function getContents($assess_id, $section_id, $sectionExclusion = false) {
        $query = "SELECT assess_id,
                         assess_section.section_id,
                         assess_section.title,
                         assess_section.sequence AS sectionSequence,
                         assess_question.*
                  FROM {$this->_table}
                  LEFT JOIN assess_question ON assess_section.section_id = assess_question.section_id
                  WHERE assess_id = '$assess_id'
                  AND {$this->_table}.{$this->_index} = '$section_id'";

        if ($sectionExclusion) {
            $query .= " AND question_id IS NOT NULL";
        }

        $query .= " ORDER BY sectionSequence, assess_question.sequence";

        return $this->db->getAll($query);
    }

    /**
     * Get a list of Sections for an Assess.
     *
     * @param <type> $assess_id
     * @return <type>
     */
    function getList($assess_id) {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE assess_id = '$assess_id'
                  ORDER BY sequence";

        return $this->db->getAll($query);
    }

    /**
     * Gets the last Section in this Assess.
     *
     * @param <type> $assess_id
     */
    function getLast($assess_id) {
        $allSections = $this->getList($assess_id);

        return $allSections[count($allSections)-1];
    }

    /**
     * Find out how much of a section was completed.
     *
     * @param <type> $section_id
     * @return <type>
     */
    function sectionPercentageComplete($section_id) {
        $section = $this->getByID($section_id);
        $sectionList = $this->getList($section['assess_id']);

        foreach ($sectionList as $currentSection) {
            $numResponses = "SELECT
                                 COUNT(response_id) AS num
                             FROM assess_response
                             LEFT JOIN assess_question ON assess_response.question_id = assess_question.question_id
                             WHERE attempt_id = '$_SESSION[attempt_id]'
                             AND assess_question.section_id = '$currentSection[section_id]'";

            $numQuestions = "SELECT
                                 COUNT(question_id) AS num
                             FROM assess_question
                             WHERE assess_question.section_id = '$currentSection[section_id]'";

            $currentSection['numResponses'] = $this->db->getOne($numResponses);
            $currentSection['numQuestions'] = $this->db->getOne($numQuestions);

            if ($currentSection['numQuestions'] > 0) {
                $currentSection['percentageComplete'] = round(100 * $currentSection['numResponses'] / $currentSection['numQuestions']);
            }
            else {
                $currentSection['percentageComplete'] = 0;
            }
            
            $completion[] = $currentSection;
        }

        return $completion;
    }
}
?>
