<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Async\Notification\Parameter\Param;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Message\NSMapper;

// Are we not in SitePage context?
if (!isset($this)) {
    require_once __DIR__ . '/../../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    SitePage::localRedir('/adminv2/notifications/preview/digest');
}

if (!$_SESSION['user']->isSuperuser()) {
    error(19);
}

/** @var SitePage $this */

if (empty($this->arg[0])) {
    $this->localRedir('/adminv2/notifications/preview/digest');
}

/** @var IConfigLoader $config */
$config      = $this->getDi()->get('config');
$bob         = new \Alaress\Async\Notification\Instance\Builder(false);
$decorator   = new \Schoolbox\Realtime\Notification\Decorator();
$emailFactory = new \Schoolbox\Mail\EmailFactory($config, \Schoolbox\Admin\EmailDKIM\Mapper::getInstance());

switch ($this->arg[0]) {
    case 'instant':
        $email = $emailFactory->buildTwigEmail('instantmail.twig', '', null);
        $email->setContext(array(
            'recipient' => $_SESSION['user']->getGivenName()
        ));

        $cctx = $email->getCoreContext();

        $decorator->appendInstance($bob
            ->setTitle(':author posted a Lab Report :name in :subj')
            ->setParams(array(
                'author' => new Param('Mr Thomas'),
                'name'   => new Param('REDUX'),
                'subj'   => new Param('Yr 11 Chemistry B'),
            ))
            ->setTimestamp(strtotime('today'))
            ->setNamespace(NSMapper::NS_DUEWORK)
            ->setBody('You will conduct a chemistry lab experiment to test acid based compounds and their reactions to a range of other elements.')
            ->setByline('Due 24th April, 2014')
            ->setHref('#')
            ->setIcon('/images/icon.php?size=192&icon=' . NSMapper::NS_DUEWORK)
            ->build());
        break;

    case 'digest':
    default:
        $email = $emailFactory->buildTwigEmail('digestmail.twig', '', null);
        $cctx = $email->getCoreContext();

        $decorator->appendInstance($bob
            ->setTitle(':author posted a Lab Report :name in :subj')
            ->setParams(array(
                'author' => new Param('Mr Thomas'),
                'name'   => new Param('REDUX'),
                'subj'   => new Param('Yr 11 Chemistry B'),
            ))
            ->setTimestamp(strtotime('today'))
            ->setNamespace(NSMapper::NS_DUEWORK)
            ->setBody('You will conduct a chemistry lab experiment to test acid based compounds and their reactions to a range of other elements.')
            ->setByline('Due 24th April, 2014')
            ->setHref('#')
            ->setIcon('/images/icon.php?size=192&icon=' . NSMapper::NS_DUEWORK)
            ->build());

        $due = date('D, jS M', strtotime('tomorrow'));
        $decorator->appendInstance($bob
            ->setTitle(':author extended :name to :date in :subj')
            ->setParams(array(
                'author' => new Param('Mrs Celia Glenn'),
                'name'   => new Param('Romeo and Juliet Essay'),
                'date'   => new Param($due),
                'subj'   => new Param('Yr 10 English Literature'),
            ))
            ->setTimestamp(strtotime('-3 days'))
            ->setBody('')
            ->setByline("Due $due")
            ->setIcon('/images/icon.php?size=192&icon=' . NSMapper::NS_DUEWORK)
            ->build());

        $decorator->appendInstance($bob
            ->setTitle(':author marked your presentation :name in :subj')
            ->setParams(array(
                'author' => new Param('Mrs Celia Glenn'),
                'name'   => new Param("Star-cross'd Lovers: "),
                'subj'   => new Param('Yr 10 English Literature'),
            ))
            ->build());

        $decorator->appendInstance($bob->clear()
            ->setTitle(':name is due :when in :subj')
            ->setParams(array(
                'name' => new Param('Shakespeare Essay'),
                'when' => new Param('in 5 hours'),
                'subj' => new Param('Yr 10 English Literature')
            ))
            ->setTimestamp(strtotime('yesterday'))
            ->setNamespace(NSMapper::NS_DUEWORK)
            ->setHref('#')
            ->build());

        $articles = \Schoolbox\Comms\News\Mapper::getInstance()->findAll(array(
            'limit' => 3,
            'order' => array(
                'publish_at' => 'DESC'
            )
        ));

        foreach ($articles as $article) {
            /** @var \Schoolbox\Comms\News\Model $article */
            try {
                if (!$article->getFeatureImage()) {
                    throw new \Schoolbox\Mapper\Exception("No image");
                }
                $hash = $article->getFeatureImage()->getHash();
                if (!$hash) {
                    throw new \Schoolbox\Mapper\Exception("No hash");
                }
                $image = "/storage/image.php?size=square192&hash=$hash";
                $thumb = "/storage/image.php?size=square200&hash=$hash";
            } catch (\Schoolbox\Mapper\Exception $e) {
                $image = '/images/icon.php?size=192&icon=' . NSMapper::NS_NEWS;

                $fallbackSelected = $this->getDi()->get('config')->get('comms_news_article_list_fallback_image');
                if ($fallbackSelected == 'school-logo') {
                    $thumb = "/images/logo.php?logo=skin_logo_large&size=normal";
                } else if ($fallbackSelected == 'author') {
                    $user = $article->getAuthor();
                    $thumb = "/portrait.php?id={$user->getId()}&size=square200";
                } else {
                    /** No fallback image, fill the empty space to maintain layout */
                    /** @var PrefixGenerator $prefixGenerator */
                    $prefixGenerator = $this->getDi()->get('cdnPrefixGenerator');
                    $thumb           = $prefixGenerator->generate() . '/images/emails/spacer.gif';
                }
            }

            $decorator->appendInstance($bob
                ->setTitle(":user posted :name under :topic")
                ->setParams(array(
                    'user'  => new Param($article->getAuthor()->formatName()),
                    'name'  => new Param($article->getTitle(), '/news/' . $article->getId())
                ))
                ->setHref('/news/' . $article->getId())
                ->setIcon($image)
                ->setMeta(array(
                    'title'  => $article->getTitle(),
                    'author' => $article->getAuthor()->formatName(),
                    'date'   => $article->getPublishAt()->getLong(),
                    'thumb'  => $thumb,
                    'blurb'  => $article->getBlurb(),
                ))
                ->setBody($article->getBody())
                ->setByline("{$article->getPublishAt()->getDateOnly()} by {$article->getAuthor()->formatName()}")
                ->setTimestamp($article->getPublishAt()->getUnixTimeStamp())
                ->setNamespace(NSMapper::NS_NEWS)
                ->build());
        }


        $bob->clear()
            ->setTitle(':user commented on your blog :title in :subj')
            ->setParams(array(
                'title' => new Param('The Big Rock'),
                'subj'  => new param('Uluru')
            ))
            ->setHref('#')
            ->setNamespace(NSMapper::NS_HOMEPAGE_BLOG);

        $time = time();

        $comments = array(
            'Anita Saxon'     => 'But what makes it red?',
            'Bobby Salem'     => "I'm pretty sure it's because it's made of sandstone.",
            'David Rhodes'    => "But sandstone is usually a yellow colour...?",
            'Scott Berryhill' => "It's red because the sandstone is a specific kind called akrose."
        );

        foreach ($comments as $name => $comment) {
            $decorator->appendInstance(
                $bob->setParam('user', new Param($name, '', Param::STACKING_APPEND))
                    ->setTimestamp($time++)
                    ->setBody(sprintf('%s wrote: "%s"', substr($name, 0, strpos($name, ' ')), $comment))
                    ->build()
            );
        }

        $decorator->appendInstance($bob->clear()
            ->setTitle(':author revised the wiki :title in :catname')
            ->setParams(array(
                'author'  => new Param('David Rhodes'),
                'title'   => new Param('The History of the Linux Kernel'),
                'catname' => new Param('Information Technology'),
            ))
            ->setNamespace(NSMapper::NS_WIKI)
            ->setHref('#')
            ->build());

        break;
}

$direction = $config->get('digest_oldest_first') ? SORT_DESC : SORT_ASC;

$email->setContext($decorator->renderAsTwigContext($cctx, 0, $direction));

if (isset($this->arg[1]) && $this->arg[1] == 'context') {
    header('Content-Type: text/plain');
    print_r($email->getContext());
    die();
}

echo $email->composeHTML();
die();
