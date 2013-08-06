<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

$pollHandler = new Poll;
$groupHandler = new Group(HOMEPAGE_SERVICE);

if (is_numeric($this->arg[0])) {

    $poll = $pollHandler->getByID($this->arg[0]);

    if (!isset($poll['pollID'])) {
        error(101);
    }

    // check permission
    if ($groupHandler->getPermission($poll['categoryID']) < WRITE_ACCESS) {
        error(19);
    }

    $this->setFile(TEMPLATE_content, "poll/modify.html");
    $this->setTitle('Modify Poll');

    $closeDate = new Alaress_Date_Format($poll['close'], 'U');

    $output = array(
        'ACTION' => 'Modify',
        'POLL_ID' => $poll['pollID'],
        'CATEGORY_ID' => $poll['categoryID'],
        'POLL_TITLE' => $poll['title'],
        'QUESTION' => $poll['question'],
        'OPTION_0' => $poll['option_0'],
        'OPTION_1' => $poll['option_1'],
        'OPTION_2' => $poll['option_2'],
        'OPTION_3' => $poll['option_3'],
        'OPTION_4' => $poll['option_4'],
        'OPTION_5' => $poll['option_5'],
        'OPTION_6' => $poll['option_6'],
        'OPTION_7' => $poll['option_7'],
        'OPTION_8' => $poll['option_8'],
        'OPTION_9' => $poll['option_9'],
        'CLOSE' => $closeDate->getLocalShort(),
        'FREQUENCY_ONCE_SELECTED' => '',
        'FREQUENCY_HOURLY_SELECTED' => '',
        'FREQUENCY_DAILY_SELECTED' => '',
        'FREQUENCY_' . strtoupper($poll['frequency']) . '_SELECTED' => 'selected="selected"',
    );

    $this->setVar($output);
} else if (is_numeric($this->arg[1])) {

    // check permission
    if ($groupHandler->getPermission($this->arg[1]) < WRITE_ACCESS) {
        error(19);
    }

    $this->setFile(TEMPLATE_content, "poll/modify.html");
    $this->setTitle('Add Poll');

    $output = array(
        'ACTION' => 'Add',
        'POLL_ID' => '',
        'CATEGORY_ID' => $this->arg[1],
        'COMPONENT_HOMEPAGE_ID' => Scrub::text($this->arg[2]),
        'POLL_TITLE' => '',
        'QUESTION' => '',
        'OPTION_0' => '',
        'OPTION_1' => '',
        'OPTION_2' => '',
        'OPTION_3' => '',
        'OPTION_4' => '',
        'OPTION_5' => '',
        'OPTION_6' => '',
        'OPTION_7' => '',
        'OPTION_8' => '',
        'OPTION_9' => '',
        'CLOSE' => date("d/m/Y", strtotime("+1 month")),
        'FREQUENCY_ONCE_SELECTED' => '',
        'FREQUENCY_HOURLY_SELECTED' => '',
        'FREQUENCY_DAILY_SELECTED' => '',
    );

    $this->setVar($output);
} else {
    error(2);
}
?>