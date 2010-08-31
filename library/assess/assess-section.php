<?php
require_once '../include/model.php';

/**
 * Description of AssessSection
 *
 * @author aetworowsky
 */
class AssessSection extends Model {
    public $_table = 'assess_section';
    public $_index = 'section_id';

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
                  AND sequence > $sequence";

        return $this->db->getRow($query);
    }

    /**
     * Gets a list of Questions that exist in a particular Assess and Section.
     *
     * @param <type> $section_id
     * @return <type>
     */
    function getContents($assess_id, $section_id) {
        $query = "SELECT assess_id,
                         assess_section.section_id,
                         assess_section.title,
                         assess_section.sequence AS sectionSequence,
                         assess_question.*
                  FROM {$this->_table}
                  LEFT JOIN assess_question ON assess_section.section_id = assess_question.section_id
                  WHERE assess_id = '$assess_id'
                  AND {$this->_table}.{$this->_index} = '$section_id'
                  ORDER BY sectionSequence, assess_question.sequence";

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
}
?>
