<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$newsHandler = new News();
$groupHandler = new Group(NEWS_SERVICE);

if (($permission = $groupHandler->getPermission(0)) < WRITE_ACCESS) {
    error(19);
}

if ($_POST['heading']) {
    $publishedDate = new Alaress_Date_Format($_POST['item_datetime_published'], 'd/m/y');
    $expiryDate = new Alaress_Date_Format($_POST['item_datetime_expires'], 'd/m/y');

    // Error out if there's campi that this user can't post to in $_POST[campus]
    $campusList = array();
    foreach ($newsHandler->getCampusList() as $campus) {
        $campusList[] = $campus['CAMPUS_ID'];
    }
    foreach ($_POST['campus'] as $campus) {
        if (!in_array($campus, $campusList)) {
            error(19);
        }
    }

    $news = array(
        'item_heading' => $_POST['heading'],
        'item_body' => $_POST['body'],
        'groupList' => $_POST['group'],
        'campusList' => $_POST['campus'],
        'userList' => (is_array($_POST['userList']) ? $_POST['userList'] : array()),
        'item_topic_id' => $_POST['item_topic_id'],
        'item_datetime_published' => $publishedDate->getDatabase(),
        'item_datetime_expires' => $expiryDate->getDatabase()
    );

    if (is_readable($_FILES['file']['tmp_name'])) {

	    $allowedFiles = new \Schoolbox\File\MimetypeMapper();
	    $mime = $allowedFiles->getByFilename($_FILES['file']['name']);

	    if(is_object($mime) && $mime->getEnable() == 'true' && stristr($mime->getMime(),'image/')) {
            try {
                $imageResource = Schoolbox\StorageMapper::getInstance()->upload(
                    $_FILES['file']['name'], $_FILES['file']['tmp_name'], $_SESSION['id']
                );
                $news['item_image'] = $imageResource->getHash();
            }
	        catch(Exception $e) { //Silently continue if we cannot load image
            }
        }
    }

    /* Check user is news owner
      */
    if (is_numeric($_POST['newsID'])) {
        $current = $newsHandler->getByID($_POST['newsID']);

        if ($current['item_owner'] != $_SESSION['id'] && $permission < FULL_ACCESS) {
            error(23);
        }

        $newsID = $news['item_id'] = $_POST['newsID'];
        $newsHandler->update($news);
    } else {
        $newsID = $newsHandler->create($news);
    }

    SitePage::localRedir("/news/item/$newsID");
} else if (is_numeric($_GET['removeImage'])) {
   $newsHandler->update(array('item_id'=>$_GET['removeImage'], 'item_image'=>''));
    SitePage::localRedir('/news/item/'.$_GET['removeImage']);
} else {
    if (is_numeric($_GET['delete'])) {
        $news = $newsHandler->getByID($_GET['delete']);

        if ($news['item_owner'] != $_SESSION['id'] && $permission < FULL_ACCESS) {
            error(23);
        }

        $newsHandler->delete($_GET['delete']);

        SitePage::localRedir("/news/");
    }
}
