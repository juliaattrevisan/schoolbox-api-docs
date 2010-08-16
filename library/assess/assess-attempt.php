<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once '../include/model.php';

/**
 * Description of AssessAttempt
 *
 * @author aetworowsky
 */
class AssessAttempt extends Model {
    /**
     *
     * @var string
     */
    public $_table = 'assess_attempt';

    /**
     *
     * @var string
     */
    public $_index = 'id';

    /**
     * Creates a attempt and marks the start time
     *
     * @param integer $user_id
     * @return mixed
     */
    public function start($user_id, $assess_id) {
        if(!is_number($user_id) || !is_number($assess_id)) {
            return false;
        }

        $attempt_id = $this->create(array(
            'assess_id' => $assess_id,
            'user_id'   => $user_id,
            'start'     => time()
        ));

        if(is_numeric($attempt_id)) {
            return $attempt_id;
        } else {
            return false;
        }
    }

    /**
     * Marks an attempt as being finished
     *
     * @param integer $attempt_id
     * @return mixed
     */
    public function finish($attempt_id) {
        if(!is_number($attempt_id)) {
            return false;
        }

        $attempt_id = $this->update(array(
            'id'     => $attempt_id,
            'finish' => time()
        ));

        if(is_numeric($attempt_id)) {
            return $attempt_id;
        } else {
            return false;
        }
    }
}
?>
