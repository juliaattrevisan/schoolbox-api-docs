<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once '../include/model.php';

/**
 * Description of AssessQuestion class
 *
 * @author aetworowsky
 */
class AssessQuestion extends Model {
    /**
     *
     * @var string
     */
    public $_table = 'assess_question';

    /**
     *
     * @var string
     */
    public $_index = 'id';

    /**
     *
     * @param integer $section_id
     * @return array
     */
    public function getLast($section_id) {
        $query = "SELECT *
            FROM `$this->_table`
            WHERE `section_id` = $section_id
            ORDER BY `order`
            DESC LIMIT 1";

        return $this->db->getRow($query);
    }   
}
?>
