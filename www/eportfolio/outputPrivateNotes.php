<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio-private-notes.php';

$privateNotesHandler = new ePortfolioPrivateNotes;

$privateNotes = $privateNotesHandler->getByID($userID);

$this->setBlock(TEMPLATE_content, 'privateNotes', 'privateNotes_block');
$this->setBlock(TEMPLATE_content, 'year_level', 'year_level_block');

$user = $userHandler->getByID($userID);

//$profile = $user_handler->getProfile($userID);

if ($profile['year'] > 0) {
    $this->setVar('YEAR', $profile['year']);
    $this->parse('year_level_block', 'year_level');
} else {
    $this->setVar('year_level_block', '');
}

$this->setVar(array(
    'PRIVATE_NOTES' => $privateNotes['privateNotes'],
    'HOUSE' => $profile['house'],
    'CAMPUS' => $profile['campus'],
));

if ($eportfolioHandler->canEditPrivateNotes($userID)) {
    $this->parse('privateNotes_block', 'privateNotes');
}
else {
    $this->setVar('privateNotes_block', '');
}
?>