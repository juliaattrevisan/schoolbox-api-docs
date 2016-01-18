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


if(is_numeric($this->arg[0])) {
    $db = Database::getInstance();

    $st = $db->prepare("INSERT INTO seda_accept_work (`uid`, `box_id`, accepted) VALUES (:uid,:box_id,NOW())");
    $st->execute(array(':uid'=>$_SESSION['id'],':box_id'=> $this->arg[0]));

    print "success";
}
exit;