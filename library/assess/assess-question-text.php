<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once '../include/model.php';

/**
 * Description of assessquestiontext
 *
 * @author aetworowsky
 */
class AssessQuestionText extends AssessQuestion {
    public function create($object) {
        /* Create qustion */
        $question = array_intersect_key($object, array_flip(array('section_id', 'order', 'type', 'weight', 'question')));
        $object['question_id'] = parent::create($question);

        if(!$object['question_id']) {
            return false;
        }

        /* Create answer */
        $this->_table = 'assess_answer';

        $answer = array_intersect_key($object, array_flip(array('question_id', 'order')));

        foreach(array('text', 'keywords') as $i => $answer_component) {
            $answer = array_merge($answer, array(
                'order' => $i,
                'answer' => $object[$answer_component]
            ));

            if(!parent::create($answer)) {
                return false;
            }
        }
        
        return $object['question_id'];
    }
}
?>
