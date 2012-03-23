<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 *
 */
class Marksheet_Percent extends Marksheet_Abstract {

    public function normaliseMark($mark, $subbox) {
        return array(
            'norm_mark' => $mark,
            'mark'      => $mark
        );
    }

    public function retrieveMark($user_id, $box_id) {
        $subReturn = $this->getSubmissionReturnHandler()->getOneByUser($user_id, $box_id);

        return array(
            'mark'     => $this->clean_num($subReturn['norm_mark'], 0),
            'comment'  => $subReturn['comment']
        );
    }

}
