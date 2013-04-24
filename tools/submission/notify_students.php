<?php

$options      = getopt("h", array('upcoming', 'overdue', 'help', 'send'));
$help         = isset($options['h']) || isset($options['help']);
$test_run     = !isset($options['send']);
$run_upcoming = isset($options['upcoming']);
$run_overdue  = isset($options['overdue']);

if ($help) {
	$filename = basename(__FILE__);
	echo
<<<HELP
$filename [--send] [--upcoming] [--overdue]

-h, --help: view this help message
--send:     actually send notifications. If not set, a diagnostic run will be
            done instead.
--upcoming: run notifications for upcoming submissions. Either this or --overdue
            must be set.
--overdue:  run notifications for overdue submissions

HELP
	;
	exit;
}
elseif (!($run_overdue || $run_upcoming)) {
	fwrite(STDERR, "Either --upcoming or --overdue must be set - see usage with --help.\n");
	exit(1);
}

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

//
// FileYear and FileSemester session vars are required by most external database
// access. Aaarrrgghh.
session_start();

//
// Copy, paste, up yours for setting session vars everywhere
$synUser  = new External_User;
$semester = $synUser->getCurrentSemester();

// don't bother if it's disabled at a school level
if (!Cfg::get('due_work_notifications_enabled')) {
	fwrite(STDERR, "Due work notifications are not enabled - this script will do nothing.\n");
	exit(2);
}

if ($run_upcoming) {
	sendUpcomingNotifications($test_run);
}
if ($run_overdue) {
	sendOverdueNotifictions($test_run);
}
exit;


/**
 * Send notifications for work that is due within the next day but not yet received.
 * Sent as a daily digest.
 * @return void
 */
function sendUpcomingNotifications($testRun) {
	echo $testRun ?
		"Test run of notifications for upcoming submissions:\n\n":
		"Sending notifications for upcoming submissions:\n\n";

	$shandler = new Submission();
	$list = $shandler->getSubmissionReminderList(UPCOMING_WORK_TYPE);
	$flagAsSent = array();
	if (count($list)) {
		foreach ($list as $user) {

			$dump = new SitePage(false);
			$dump->setFile(TEMPLATE_body, 'tools/submission-upcoming.txt');
			$dump->setBlock(TEMPLATE_body, 'subject', 'subject_block');
			$dump->setBlock('subject', 'due_work', 'due_work_block');

			$mailer = new Mailer('submission-upcoming.html', 'Upcoming Due Work', $user['alt_email']);
			$mailer->setBlock('tplBODY', 'subject', 'subject_block');
			$mailer->setBlock('subject', 'due_work', 'due_work_block');

			$output = array(
				'NAME'        => trim($user['firstname']),
				'SURNAME'     => trim($user['lastname']),
				'SITE_DOMAIN' => Cfg::get('site_domain'),
				'USER_EMAIL'  => trim($user['alt_email']),
			);
			$dump->setVar($output);
			$mailer->setVar($output);

			if(count($user['categories'])) {
				foreach($user['categories'] as $cat) {
					$dump->setVar('due_work_block', '');
					$dump->setVar('CATEGORY_ID', $cat['id']);
					$dump->setVar('CATEGORY_NAME', $cat['name']);

					$mailer->setVar('due_work_block', '');
					$mailer->setVar('CATEGORY_ID', $cat['id']);
					$mailer->setVar('CATEGORY_NAME', $cat['name']);

					if(count($cat['work'])) {
						foreach($cat['work'] as $box) {
							$due = '';
							$stamp = strtotime($box['date']);
							if(date('Hi', $stamp) != '2359') {
								$due .= ' at ' . date('g:ia', $stamp);
							}
							$due .= ' on ' . date('D, jS M', $stamp);

							$dump->setVar('DUEWORK_ID',   $box['id']);
							$dump->setVar('DUEWORK_NAME', $box['name']);
							$dump->setVar('DUEWORK_DATE', $due);
							$dump->parse('due_work_block', 'due_work', true);

							$mailer->setVar('DUEWORK_ID',   $box['id']);
							$mailer->setVar('DUEWORK_NAME', $box['name']);
							$mailer->setVar('DUEWORK_DATE', $due);
							$mailer->parse('due_work_block', 'due_work', true);

							$flagAsSent[] = $box['id'];
						} // end foreach duework
					}

					$dump->parse('subject_block', 'subject', true);
					$mailer->parse('subject_block', 'subject', true);
				} // end foreach category
			}

			$dump->outputPage(true);
			if (!$testRun) {
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
	echo $testRun ?
		"Test run of notifications for overdue submissions:\n\n":
		"Sending notifications for overdue submissions:\n\n";

	$shandler = new Submission();
	$list = $shandler->getSubmissionReminderList(OVERDUE_WORK_TYPE);
	$flagAsSent = array();
	// yes, this looks like O(n^3)... the execution time is the same as if the array were flat.
	foreach($list as $user) {
		foreach($user['categories'] as $category) {
			foreach($category['work'] as $dueWork) {
//				if($testRun) {
//					$mailer = new SitePage(false);
//					$mailer->setFile(TEMPLATE_body, 'tools/submission-overdue.txt');
//					$mailer->setBlock(TEMPLATE_body, 'subject', 'subject_block');
//				}
//				else {
//				}

				$dump = new SitePage(false);
				$dump->setFile(TEMPLATE_body, 'tools/submission-overdue.txt');
				$dump->setBlock(TEMPLATE_body, 'subject', 'subject_block');

				$mailer = new Mailer('submission-overdue.html', 'Overdue Work', $user['u_alt_email']);
				$mailer->setBlock('tplBODY', 'subject', 'subject_block');

				$due = '';
				$stamp = strtotime($dueWork['date']);
				if(date('Hi', $stamp) != '2359') {
					$due .= ' at ' . date('g:ia', $stamp);
				}
				$due .= ' on ' . date('D, jS M', $stamp);

				$output = array(
					'NAME'        => trim($user['firstname']),
					'SURNAME'     => trim($user['lastname']),
					'SITE_DOMAIN' => Cfg::get('site_domain'),
					'USER_EMAIL'  => trim($user['alt_email']),

					'CATEGORY_ID'   => $category['id'],
					'CATEGORY_NAME' => $category['name'],

					'DUEWORK_ID'   => $dueWork['id'],
					'DUEWORK_NAME' => $dueWork['name'],
					'DUEWORK_DATE' => $due
				);

				$dump->setVar($output);
				$mailer->setVar($output);

				$dump->outputPage(true);
				if (!$testRun) {
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