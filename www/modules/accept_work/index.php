<?php
/**
 *
 * Custom script written for SEDA to capture students accepting their marks
 * This script is called using ajax on the submissionHistory page.
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 *
 * CREATE TABLE `seda_accept_work`( `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `uid` INT, `box_id` INT, `accepted` DATETIME, PRIMARY KEY (`id`) );
 *
 */

use \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;


if(is_numeric($this->arg[0])) {

    $returnMapper = ReturnedWorkMapper::getInstance();

    try {
        $staffReturn = $returnMapper->findById($this->arg[0]);

        if( $staffReturn->getNormalizedMark() > 0 &&
            $staffReturn->getOwner()->getId() == $_SESSION['user']->getId() ) {

            $db = Database::getInstance();

            $st = $db->prepare("INSERT INTO seda_accept_work (`uid`, `box_id`, accepted) VALUES (:uid,:box_id,NOW())");
            $st->execute(array(':uid'=>$_SESSION['user']->getId(),':box_id'=> $this->arg[0]));

            print "success";

        }
        else {
            print "not owner, or mark not set";
        }


    } catch (Schoolbox\Mapper\Exception $e) {
        print "return not found";
    }

}
exit;