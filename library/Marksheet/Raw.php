<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Marksheet_Raw extends Marksheet_Abstract {

    public function normaliseMark($mark, $subbox) {

        // TODO: need validation mechanism
        $normalised = (($mark / $subbox['max_mark']) * 100);

        return array(
            'norm_mark' => $normalised,
            'mark'      => $this->clean_num($mark, 4)
        );
    }

    public function retrieveMark($user_id, $box_id) {
        $subReturn = $this->getSubmissionReturnHandler()->getOneByUser($user_id, $box_id);
        $subbox = $this->getSubmissionHandler()->getByID($box_id);

        return array(
            'mark'     => $this->clean_num(($subReturn['norm_mark'] / 100) * $subbox['max_mark'], 4),
            'max_mark' => $subbox['max_mark'],
            'comment'  => $subReturn['comment']
        );

    }
}
