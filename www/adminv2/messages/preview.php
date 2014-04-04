<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


if(!isset($this)) {
	require_once __DIR__ . '/../../../include/Bootstrap.php';
	SitePage::localRedir('/adminv2/messages/preview/digest');
}

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
	error(19);
}

/** @var SitePage $this */

if(empty($this->arg[0])) {
	$this->localRedir('/adminv2/messages/preview/digest');
}

$decor = new \Schoolbox\Notif\Message\Decorator(null);
$msgFactory = new \Schoolbox\Notif\Message\Factory();

/** @var \Schoolbox\Notif\Message\External\TargetedMessage[] $msgs */
$msgs = array();

switch($this->arg[0]) {
	case 'instant':
		$email = new \Schoolbox\Mail\TwigEmail('instantmail.twig');
		$email->setContext(array(
			'recipient' => $_SESSION['user']->getFirstname()
		));

		$cctx = $email->getCoreContext();

		$msgs[] = $msgFactory
			->setMsgBody(':author posted a Lab Report :name in :subj')
				->bindParam('author', 'Mr Walter White', '#')
				->bindParam('name', 'REDUX', '#')
				->bindParam('subj','Yr 11 Chemistry B', '#')
			->setTimestamp(strtotime('today'))
			->setMeta('subtitle', 'Due 24th April, 2014')
			->setMeta('abstract', 'You will conduct a chemistry lab experiment to test acid based compounds and their reactions to a range of other elements.')
			->setNotifier(new \Schoolbox\Assessment\Component\SubmissionBoxNotifier(null))
			->build();
		break;

	case 'digest':
	default:
		$email = new \Schoolbox\Mail\TwigEmail('digestmail.twig');
		$cctx = $email->getCoreContext();

		$msgs[] = $msgFactory
			->setMsgBody(':author posted a Lab Report :name in :subj')
				->bindParam('author', 'Mr Walter White', '#')
				->bindParam('name', 'REDUX', '#')
				->bindParam('subj', 'Yr 11 Chemistry B', '#')
			->setTimestamp(strtotime('now'))
			->setNotifier(new \Schoolbox\Assessment\Component\SubmissionBoxNotifier(null))
			->build();

		$msgFactory
			->setMsgBody(':author extended :name to :date in :subj')
				->bindParam('author', 'Mrs Celia Glenn', '#')
				->bindParam('name', 'Shakespeare Essay', '#')
				->bindParam('date', date('D, jS M', date('tomorrow')))
				->bindParam('subj', 'Yr 10 English Literature', '#')
			->setTimestamp(strtotime('-72 hours'))
			->setNotifier(new \Schoolbox\Assessment\Component\SubmissionBoxNotifier(null));

		for($i = 0; $i < 6; ++$i) {
			$msgFactory->setHref('/'.sha1(rand()));
			$msgs[] = $msgFactory->build();
		}

		$msgs[] = $msgFactory
			->setMsgBody(':name is due :when in :subj')
				->bindParam('name', 'Shakespeare Essay', '#')
				->bindParam('when', 'in 5 hours')
				->bindParam('subj', 'Yr 11 Chemistry B', '#')
			->setTimestamp(strtotime('-24 hours'))
			->setNotifier(new \Schoolbox\Assessment\Component\SubmissionBoxNotifier(null))
			->build();

		/** @var \Schoolbox\News\Article[] $articles */
		$articles = \Schoolbox\News\ArticleMapper::getInstance()->findAll(array('limit' => 3));

		foreach($articles as $article) {

			if($article->getImage()->getHash()) {
				$image ='/storage/image.php?size=square64&hash='.$article->getImage()->getHash();
			}
			else {
				$image = '/images/icon.php?size=64&icon=pin';
			}

			$msgs[] = $msgFactory
				->setMsgBody(":title")
					->bindParam('title', $article->getHeading(), '/news/item/'.$article->getId())
				->setHref('/news/item/'.$article->getId())
				->setIcon($image)
				->setMeta('abstract', TextFormatter::cutSentence(Scrub::html2text($article->getBody()), 300))
				->setMeta('author', $article->getOwner()->formatName())
				->setMeta('topic', $article->getTopic()->getName())
				->setTimestamp($article->getPostDate()->getUnixTimestamp())
				->setNotifier(new \Schoolbox\News\ArticleNotifier(null))
				->build();
		}
		break;
}

$email->buildContextFromMessageList($msgs);

if(isset($this->arg[1]) && $this->arg[1] == 'context') {
	header('Content-Type: text/plain');
	print_r($email->getContext());
	die();
}

echo $email->composeHTML();
die();