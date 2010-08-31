<?php
require_once '../include/model.php';

/**
 * Description of AssessAttempt
 *
 * @author aetworowsky
 */
class AssessAttempt extends Model {
    var $_table = 'assess_attempt';
    var $_index = 'attempt_id';
    
    /**
     * Creates an attempt and marks the start time
     *
     * @param integer $user_id
     * @return mixed
     */
    function start($user_id, $assess_id) {
        if (!is_numeric($user_id) || !is_numeric($assess_id)) {
            return false;
        }

        $attempt_id = parent::create(array(
            'assess_id' => $assess_id,
            'user_id' => $user_id,
            'start' => date('Y-m-d H:i:s')
        ));

        if (is_numeric($attempt_id)) {
            return $attempt_id;
        }
        else {
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
        if(!is_numeric($attempt_id)) {
            return false;
        }

        $attempt_id = $this->update(array(
            'assess_id' => $attempt_id,
            'finish' => date('Y-m-d H:i:s')
        ));

        if (is_numeric($attempt_id)) {
            return $attempt_id;
        }
        else {
            return false;
        }
    }
}
?>
