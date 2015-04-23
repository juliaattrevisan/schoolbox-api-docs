<?php
use Alaress\Async\Notification\Parameter\Param;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Realtime\Message\NSMapper;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


if (!isset($this)) {
    require_once __DIR__ . '/../../../include/Bootstrap.php';
    SitePage::localRedir('/adminv2/messages/preview/digest');
}

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

/** @var SitePage $this */

if (empty($this->arg[0])) {
    $this->localRedir('/adminv2/messages/preview/digest');
}

$bob       = new \Alaress\Async\Notification\Instance\Builder(false);
$decorator = new \Schoolbox\Realtime\Notification\Decorator();

$coreContext = new \Schoolbox\Mail\CoreContext(new \Schoolbox\Config\Loader());

switch ($this->arg[0]) {
    case 'instant':
        $email = new \Schoolbox\Mail\TwigEmail('instantmail.twig');
        $email->setCoreContext($coreContext->getContext());
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
            ->setNamespace(NSMapper::NS_DUEWORK)
            ->setBody('You will conduct a chemistry lab experiment to test acid based compounds and their reactions to a range of other elements.')
            ->setByline('Due 24th April, 2014')
            ->setHref('#')
            ->setIcon('/images/icon.php?size=32&icon=work')
            ->build()
        );
        break;

    case 'digest':
    default:
        $email = new \Schoolbox\Mail\TwigEmail('digestmail.twig');
        $email->setCoreContext($coreContext->getContext());
        $cctx = $email->getCoreContext();

        $decorator->appendInstance($bob
            ->setTitle(':author posted a Lab Report :name in :subj')
            ->setParams(array(
                'author' => new Param('Mr Walter White'),
                'name'   => new Param('REDUX'),
                'subj'   => new Param('Yr 11 Chemistry B'),
            ))
            ->setTimestamp(strtotime('today'))
            ->setNamespace(NSMapper::NS_DUEWORK)
            ->setBody('You will conduct a chemistry lab experiment to test acid based compounds and their reactions to a range of other elements.')
            ->setByline('Due 24th April, 2014')
            ->setHref('#')
            ->setIcon('/images/icon.php?size=32&icon=work')
            ->build()
        );

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
            ->setIcon('/images/icon.php?size=32&icon=work')
            ->build()
        );

        $decorator->appendInstance($bob
            ->setTitle(':author marked your Oral Presentation :name in :subj')
            ->setParams(array(
                'author' => new Param('Mrs Celia Glenn'),
                'name'   => new Param("Star-cross'd Lovers: "),
                'subj'   => new Param('Yr 10 English Literature'),
            ))
            ->build()
        );

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
            ->build()
        );

        $articles = \Schoolbox\News\ArticleMapper::getInstance()->findAll(array(
            'limit' => 3,
            'order' => array(
                'item_datetime_published' => 'DESC'
            )
        ));

        foreach ($articles as $article) {
            /** @var \Schoolbox\News\Article $article */
            $article = \Schoolbox\News\ArticleMapper::getInstance()->findById($article->getId());

            try {
                $image = '/storage/image.php?size=square64&hash=' . $article->getImage()->getHash();
                $thumb = '/storage/image.php?size=square200&hash=' . $article->getImage()->getHash();
            } catch (\Schoolbox\Mapper\Exception $e) {
                $image = '/images/icon.php?size=64&icon=pin';
                $thumb = '/images/emails/spacer.gif';
            }

            $decorator->appendInstance($bob
                ->setTitle(":user posted :name under :topic")
                ->setParams(array(
                    'user'  => new Param($article->getOwner()->formatName()),
                    'name'  => new Param($article->getHeading(), '/news/item/' . $article->getId()),
                    'topic' => new Param($article->getTopic()->getName())
                ))
                ->setHref('/news/item/' . $article->getId())
                ->setIcon($image)
                ->setMeta(array(
                    'title'  => $article->getHeading(),
                    'author' => $article->getOwner()->formatName(),
                    'date'   => $article->getPostDate()->getLong(),
                    'thumb'  => $thumb
                ))
                ->setBody($article->getBody())
                ->setByline("{$article->getPostDate()->getDateOnly()} by {$article->getOwner()->formatName()} in {$article->getTopic()->getName()}")
                ->setTimestamp($article->getPostDate()->getUnixTimestamp())
                ->setNamespace(NSMapper::NS_NEWS)
                ->build()
            );
        }


        $bob->clear()
            ->setTitle(':user commented on your blog :title in :subj')
            ->setParams(array(
                'title' => new Param('The Big Rock'),
                'subj'  => new param('Uluru')
            ))
            ->setHref('#')
            ->setNamespace(NSMapper::NS_BLOG);

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
            ->build()
        );

        break;
}

$direction = ConfigMapper::getInstance()->get('digest_oldest_first') ? SORT_DESC : SORT_ASC;

$email->setContext($decorator->renderAsTwigContext($cctx, 0, $direction));

if (isset($this->arg[1]) && $this->arg[1] == 'context') {
    header('Content-Type: text/plain');
    print_r($email->getContext());
    die();
}

echo $email->composeHTML();
die();