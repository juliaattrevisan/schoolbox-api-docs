<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// invisibility cloak enabled
if(isset($this)) {
    $this->pageNotFound();
}

require_once '../../include/Bootstrap.php';

//
// FileYear and FileSemester session vars are required by most external database
// access. Aaarrrgghh.
session_start();

// don't bother if it's disabled at a school level
if(!Cfg::get('due_work_notifications_enabled')) {
    die();
}

if(isset($_GET['upcoming'])) {
    sendUpcomingNotifications(isset($_GET['view']));
}
elseif(isset($_GET['overdue'])) {
    sendOverdueNotifictions(isset($_GET['view']));
}
die();


/**
 * Send notifications for work that is due within the next day but not yet received.
 * Sent as a daily digest.
 * @return void
 */
function sendUpcomingNotifications($testRun) {
    $shandler = new Submission();
    $list = $shandler->getSubmissionReminderList(UPCOMING_WORK_TYPE);
    $flagAsSent = array();

    if(count($list)) {
        foreach($list as $user) {

            if($testRun) {
                $mailer = new SitePage(false);
                $mailer->setFile(TEMPLATE_body, 'email/submission-upcoming.html');
                $mailer->setBlock(TEMPLATE_body, 'subject', 'subject_block');
            }
            else {
                $mailer = new Mailer('submission-upcoming.html', 'Upcoming Due Work', $user['alt_email']);
                $mailer->setBlock('tplBODY', 'subject', 'subject_block');
            }

            $mailer->setBlock('subject', 'due_work', 'due_work_block');
            $mailer->setVar('NAME', trim($user['firstname']));

            if(count($user['categories'])) {
                foreach($user['categories'] as $cat) {
                    $mailer->setVar('due_work_block', '');
                    $mailer->setVar('CATEGORY_ID', $cat['id']);
                    $mailer->setVar('CATEGORY_NAME', $cat['name']);

                    if(count($cat['work'])) {
                        foreach($cat['work'] as $box) {
                            $mailer->setVar('DUEWORK_ID',   $box['id']);
                            $mailer->setVar('DUEWORK_NAME', $box['name']);
                            $due = '';
                            $stamp = strtotime($box['date']);
                            if(date('Hi', $stamp) != '2359') {
                                $due .= ' at ' . date('g:ia', $stamp);
                            }
                            $due .= ' on ' . date('D, jS M', $stamp);
                            $mailer->setVar('DUEWORK_DATE', $due);

                            $mailer->parse('due_work_block', 'due_work', true);
                            $flagAsSent[] = $box['id'];
                        } // end foreach duework
                    }

                    $mailer->parse('subject_block', 'subject', true);
                } // end foreach category
            }

            if($testRun) {
                $mailer->outputPage(true);
            }
            else {
                $mailer->send();
            }
        }
    }

    // set the sent flag on the submission boxes that were just processed so they don't get sent again
    if(!$testRun) {
        setNotificationsSent($flagAsSent, 'upcoming');
    }
}


/**
 * Send notifications for submission boxes that are now closed.
 * Sent ad-hoc, not as digest.
 * @return void
 */
function sendOverdueNotifictions($testRun) {
    $shandler = new Submission();
    $list = $shandler->getSubmissionReminderList(OVERDUE_WORK_TYPE);
    $flagAsSent = array();
    // yes, this looks like O(n^3)... the execution time is the same as if the array were flat.
    foreach($list as $user) {
        foreach($user['categories'] as $category) {
            foreach($category['work'] as $dueWork) {
                if($testRun) {
                    $mailer = new SitePage(false);
                    $mailer->setFile(TEMPLATE_body, 'email/submission-overdue.html');
                    $mailer->setBlock(TEMPLATE_body, 'subject', 'subject_block');
                }
                else {
                    $mailer = new Mailer('submission-overdue.html', 'Overdue Work', $user['u_alt_email']);
                    $mailer->setBlock('tplBODY', 'subject', 'subject_block');
                }

                $due = '';
                $stamp = strtotime($dueWork['date']);
                if(date('Hi', $stamp) != '2359') {
                    $due .= ' at ' . date('g:ia', $stamp);
                }
                $due .= ' on ' . date('D, jS M', $stamp);

                $output = array(
                    'NAME' => trim($user['firstname']),

                    'CATEGORY_ID'   => $category['id'],
                    'CATEGORY_NAME' => $category['name'],

                    'DUEWORK_ID'   => $dueWork['id'],
                    'DUEWORK_NAME' => $dueWork['name'],
                    'DUEWORK_DATE' => $due
                );

                $mailer->setVar($output);

                if($testRun) {
                    $mailer->outputPage(true);
                }
                else {
                    $mailer->send();
                    $flagAsSent[] = $dueWork['id'];
                }
            }
        }
    }
    if(!$testRun) {
        setNotificationsSent($flagAsSent, 'overdue');
    }
}


/**
 * Set the appropriate submission_notification records to indicate emails are sent
 * @param $boxList
 * @param $notificationType
 */
function setNotificationsSent($boxList, $notificationType) {
    if(count($boxList)) {
        $impl = implode(',', $boxList);
        $type = Database::getInstance()->quote($notificationType);
        $sql = "UPDATE `submission_notification` SET `sent` = 1 WHERE `box_id` IN ($impl) AND `type` = $type";
        Database::getInstance()->query($sql);
    }
}