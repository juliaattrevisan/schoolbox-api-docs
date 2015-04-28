<?php
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$newsHandler = new News();
$groupHandler = new Group(NEWS_SERVICE);

if (($permission = $groupHandler->getPermission(0)) < WRITE_ACCESS) {
    error(19);
}

if ($_POST['heading']) {
    $publishedDate = Alaress_Date_Format::newLocalShort($_POST['item_datetime_published']);
    $expiryDate = Alaress_Date_Format::newLocalShort($_POST['item_datetime_expires']);

    if($_POST['publish_hour'] && $_POST['publish_minute']) {
        $publishedDate->set12Hour($_POST['publish_hour'], $_POST['publish_period']);
        $publishedDate->setMinute($_POST['publish_minute']);
    }

    if($_POST['expires_hour'] && $_POST['expires_minute']) {
        $expiryDate->set12Hour($_POST['expires_hour'], $_POST['expires_period']);
        $expiryDate->setMinute($_POST['expires_minute']);
    }

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

	if(is_numeric($_POST['newsID'])) {
		// get a pre-existing article if it exists
		try {
			$article = \Schoolbox\News\ArticleMapper::getInstance()->findById($_POST['newsID']);
			// permission check
			if($article->getOwner()->getId() != $_SESSION['id'] && $permission < FULL_ACCESS) {
				 error(23);
				 exit;
			}
		}
		// if nothing matches, blow up
		catch(\Schoolbox\Mapper\Exception $e) {
//			die($e);
			error(41);
			exit;
		}
	}
	else {
		$article = new \Schoolbox\News\Article();
		$article->setPostDate(new Alaress_Date_Format());
	}


	$topic = new \Schoolbox\News\Topic();
	$topic->setId($_POST['item_topic_id']);

	$campuses = new \Schoolbox\Collection();
	foreach($_POST['campus'] as $c) {
		$obj = new \Schoolbox\Campus();
		$obj->setId($c);
		$campuses->add($obj);
	}
	unset($c);
	unset($obj);

	$roles = new \Schoolbox\Collection();
	foreach($_POST['group'] as $r) {
		$obj = new \Schoolbox\Role();
		$obj->setId($r);
		$roles->add($obj);
	}
	unset($r);
	unset($obj);

	$users = new Schoolbox\Collection();
	$userList = isset($_POST['userList']) ? $_POST['userList'] : array();
	foreach($userList as $u) {
		$obj = new \Schoolbox\User();
		$obj->setId($u);
		$users->add($obj);
	}
	unset($u);
	unset($obj);

	$article
		->setHeading($_POST['heading'])
		->setBody($_POST['body'])
		->setTopic($topic)
		->setPublishDate($publishedDate)
		->setExpiryDate($expiryDate)
		->setCampuses($campuses)
		->setUsers($users)
		->setRoles($roles);

	// if this is a new article, add some extra bits
	if(!$article->getId()) {
		$article->setOwner($_SESSION['user']);
		$article->setPostDate(Alaress_Date_Format::now());
	}
	else {
		$article->setModifiedDate(Alaress_Date_Format::now());
	}

    if (count($_POST['attachment']) && is_array($_POST['attachment'])) {
        try {
            $collectorFactory = new CollectorFactory();
            $collector = $collectorFactory->build($_POST['attachment']);
            $article->setImage($collector->getFirst());
        } catch (\Schoolbox\Resource\Exception $e) {
            error(41); // no any device exists
        }
    }
    // retained case for browsers that require fallback
    // TODO replace this with async iframe windows that upload the files elsewhere
    elseif (is_readable($_FILES['file']['tmp_name'])) {

	    $allowedFiles = new \Schoolbox\File\MimetypeMapper();
	    $mime = $allowedFiles->getByFilename($_FILES['file']['name']);

	    if(is_object($mime) && $mime->getEnable() == 'true' && stristr($mime->getMime(),'image/')) {
            try {
                $imageResource = Schoolbox\Resource\Storage\Mapper::getInstance()->accept(
                    $_FILES['file']['name'], $_FILES['file']['tmp_name']
                );
                $article->setImage($imageResource);
            }
	        catch(Exception $e) {
	            // silently continue if we cannot load image
            }
        }
    }
    else {
        // remove the image
        $article->setImage(null);
    }

	$affectedIds = \Schoolbox\News\ArticleMapper::getInstance()->save($article);
	$newsId = $affectedIds[0];

    SitePage::localRedir("/news/item/$newsId");
}

// request to delete the news item
elseif (is_numeric($_GET['delete'])) {
	$newsId = $_GET['delete'];

	try {
		$article = \Schoolbox\News\ArticleMapper::getInstance()->findById($newsId);
	}
	catch(\Schoolbox\Mapper\Exception $e) {
		error(41);
		exit;
	}

	// permission check
	if($article->getOwner()->getId() != $_SESSION['id'] && $permission < FULL_ACCESS) {
		error(23);
	}

	\Schoolbox\News\ArticleMapper::getInstance()->delete($article);
}

// if/when we fall out the bottom, just redirect to the newsstand
SitePage::localRedir("/news/");

