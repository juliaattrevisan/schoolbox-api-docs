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

switch($this->arg[0]) {
	case 'instant':
		$email = new \Schoolbox\Mail\TwigEmail('instantmail.twig');
		$cctx = $email->getCoreContext();

		$decor->setMessage(
			$msgFactory
			->setMsgBody(':author posted a Lab Report :name in :subj')
				->bindParam('author', 'Mr Walter White', '#')
				->bindParam('name', 'REDUX', '#')
				->bindParam('subj','Yr 11 Chemistry B', '#')
			->setTimestamp(strtotime('today'))
			->setNotifier(new \Schoolbox\Assessment\Component\SubmissionBoxNotifier(null))
			->build()
		);

		$ctx = $decor->formatContext($cctx);
		$date = array_values($ctx['groups']['work']['dates']);
		$msg = array_values($date[0]);
		$msg[0]['abstract'] = <<< EOT
<p><em>Due 24th April, 2014</em></p>
<p>You will conduct a chemistry lab experiment to test acid based compounds and their reactions to a range of other elements.</p>
EOT;

//		unset($msg[0]['abstract']);

		$context = array(
			'recipient' => $_SESSION['user']->getFirstname(),
			'msg'       => $msg[0]
		);
		break;

	case 'digest':
	default:
		$email = new \Schoolbox\Mail\TwigEmail('digestmail.twig');
		$cctx = $email->getCoreContext();

		/** @var \Schoolbox\Notif\Message\External\TargetedMessage[] $msgs */
		$msgs = array();

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
		$articles = \Schoolbox\News\ArticleMapper::getInstance()->findAll(array('limit' => 10));

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

		$context = array();

		foreach($msgs as $msg) {
			$ns = in_array($msg->getNamespace(), array('duework', 'assess')) ? 'work' : $msg->getNamespace();
			$context = array_merge_recursive($context, $decor->setMessage($msg)->formatContext($cctx));
		}

		$headings = array(
			'work' => 'Schoolwork',
			'news' => 'News'
		);

		foreach($headings as $ns => $heading) {
			if(isset($context['groups'][$ns])) {
				$context['groups'][$ns]['heading'] = $heading;
			}
		}

		$oldestFirst = Cfg::get('digest_oldest_first');

		// sorting
		foreach($context['groups'] as $ns => &$group) {
			switch($ns) {
				case 'work':
					// $group is prefixed dates
					if($oldestFirst) {
						ksort($group['dates']);
					}
					else {
						krsort($group['dates']);
					}
					break;
				default:
					if(isset($group['messages'])) {
						usort($group['messages'], function($a, $b) use ($oldestFirst) {
							$i = $oldestFirst ? 1 : -1;
							return $a['timestamp'] == $b['timestamp']
								? 0
								: $a > $b ? ($i) : ($i * -1);
						});
					}
					break;
			}
		}

		break;

}

if(isset($this->arg[1]) && $this->arg[1] == 'context') {
	header('Content-Type: text/plain');
	print_r($context);
	die();
}

$email->setContext($context);

echo $email->composeHTML();
die();