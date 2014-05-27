<?php
use Alaress\Async\Notification\Parameter\Param;

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

$bob = new \Alaress\Async\Notification\Instance\Builder();
$decorator = new \Schoolbox\Realtime\Notification\Decorator();

switch($this->arg[0]) {
	case 'instant':
		$email = new \Schoolbox\Mail\TwigEmail('instantmail.twig');
		$email->setContext(array(
			'recipient' => $_SESSION['user']->getFirstname()
		));

		$cctx = $email->getCoreContext();

		$decorator->appendInstance($bob
			->setTitle(':author posted a Lab Report :name in :subj')
			->setParams(array(
				'author' => new Param('Mr Walter White'),
				'name'   => new Param('REDUX'),
				'subj'   => new Param('Yr 11 Chemistry B'),
			))
			->setTimestamp(strtotime('today'))
			->setNamespace('work')
			->setBody('You will conduct a chemistry lab experiment to test acid based compounds and their reactions to a range of other elements.')
			->setByline('Due 24th April, 2014')
			->setHref('#')
			->setIcon('/image/icon.php?size=square32&icon=work')
			->build()
		);
		break;

	case 'digest':
	default:
		$email = new \Schoolbox\Mail\TwigEmail('digestmail.twig');
		$cctx = $email->getCoreContext();

		$decorator->appendInstance($bob
				->setTitle(':author posted a Lab Report :name in :subj')
				->setParams(array(
					'author' => new Param('Mr Walter White'),
					'name'   => new Param('REDUX'),
					'subj'   => new Param('Yr 11 Chemistry B'),
				))
				->setTimestamp(strtotime('today'))
				->setNamespace('work')
				->setBody('You will conduct a chemistry lab experiment to test acid based compounds and their reactions to a range of other elements.')
				->setByline('Due 24th April, 2014')
				->setHref('#')
				->setIcon('/image/icon.php?size=square32&icon=work')
				->build()
		);

		$due = date('D, jS M', date('tomorrow'));
		$decorator->appendInstance($bob
				->setTitle(':author extended :name to :date in :subj')
				->setParams(array(
					'author' => new Param('Mrs Celia Glenn'),
					'name'   => new Param('Shakespeare Essay'),
					'date'   => new Param($due),
					'subj'   => new Param('Yr 10 English Literature'),
				))
				->setTimestamp(strtotime('-72 hours'))
				->setBody('')
				->setByline($due)
				->setIcon('/image/icon.php?size=square32&icon=work')
				->build()
		);

//		for($i = 0; $i < 6; ++$i) {
//			$msgFactory->setHref('/'.sha1(rand()));
//			$msgs[] = $msgFactory->build();
//		}
//
//		$msgs[] = $msgFactory
//			->setMsgBody(':name is due :when in :subj')
//				->bindParam('name', 'Shakespeare Essay', '#')
//				->bindParam('when', 'in 5 hours')
//				->bindParam('subj', 'Yr 11 Chemistry B', '#')
//			->setTimestamp(strtotime('-24 hours'))
//			->setNotifier(new \Schoolbox\Assessment\Component\SubmissionBoxNotifier(null))
//			->build();

		/** @var \Schoolbox\News\Article[] $articles */
		$articles = \Schoolbox\News\ArticleMapper::getInstance()->findAll(array('limit' => 3));

		foreach($articles as $article) {

			if($article->getImage()->getHash()) {
				$image ='/storage/image.php?size=square64&hash='.$article->getImage()->getHash();
			}
			else {
				$image = '/images/icon.php?size=64&icon=pin';
			}

			$decorator->appendInstance($bob
				->setTitle(":user posted :name under :topic")
				->setParams(array(
						'user'  => new Param($article->getOwner()->formatName()),
						'name'  => new Param($article->getHeading(), '/news/item/'.$article->getId()),
						'topic' => new Param($article->getTopic()->getName())
					))
				->setHref('/news/item/'.$article->getId())
				->setBody(TextFormatter::cutSentence(Scrub::html2text($article->getBody()), 300))
				->setByline("By {$article->getOwner()->formatName()} in {$article->getTopic()->getName()}")
				->setTimestamp($article->getPostDate()->getUnixTimestamp())
				->build()
			);
		}
		break;
}

// TODO or should it be the Bundle (in this case Decorator)?
$email->setNotifications($decorator->getAll());
//$email->buildContextFromMessageList($msgs);

if(isset($this->arg[1]) && $this->arg[1] == 'context') {
	header('Content-Type: text/plain');
	print_r($email->getContext());
	die();
}

echo $email->composeHTML();
die();