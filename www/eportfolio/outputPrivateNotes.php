<?php

/* Profile card
 */
$profile = new Eportfolio_Profile_Card($this, $userID);
$profile->render();

/* Render private notes
 */
$privateNotes = new Eportfolio_Private_Notes($this, $userID);

if ($eportfolioHandler->canEditPrivateNotes($userID)) {
	$privateNotes->setCanEditPrivateNotes(true);
}
else {
    $privateNotes->setCanEditPrivateNotes(false);
}

$privateNotes->render();