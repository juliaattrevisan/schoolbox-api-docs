<?php
/**
 *
 * Make the headlines! Be famous!
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Alaress\Application\Kernel;
use Schoolbox\CampusMapper;
use Schoolbox\News\Article;
use Schoolbox\News\ArticleMapper;
use Schoolbox\News\TopicMapper;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Schedule\Event;
use Schoolbox\Resource\Category\News;
use Schoolbox\Resource\Category\NewsMapper;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

/** @var Kernel $kernel */
$kernel = require_once __DIR__ . '/../../../include/Bootstrap.php';

class SchoolNewsMap extends ArticleMapper
{

    public function fakeNotify($title, $body, $authorId = 151)
    {
        $this->sessionData['user'] = UserMapper::getInstance()->findById($authorId);

        /** @var Article $model */
        $model = $this->createModel();

        $model->setHeading($title);
        $model->setBody($body);
        $model->setCampuses(CampusMapper::getInstance()->findAll());
        $model->setRoles(RoleMapper::getInstance()->findAll());
        $model->setTopic(TopicMapper::getInstance()->findById(1));
        $model->setPublishDate(Alaress_Date_Format::now());
        $model->setExpiryDate(Alaress_Date_Format::now()->addDays(1));
        $model->setModifiedDate(Alaress_Date_Format::now());
        $model->setOwner($this->sessionData['user']);
        $model->setPostDate(Alaress_Date_Format::now());

        $this->save($model);
    }

    protected function raiseNotification($object, $verb, $previous = null)
    {
        if (!isset($this->sessionData['user'])) {
            return false;
        }
        $diff = $previous && ($verb != self::METHOD_DELETE) ? $this->diff($object, $previous) : null;
        // create a new event, and send it to the scheduler
        $event = new Event($this->sessionData['user'], $verb, $object, ArticleMapper::getInstance(), $diff);
        $this->getNotifier()->setTopic(Notif::TOPIC_APPLICATION)->notice(serialize($event));
        return true;
    }


}

class HomepageNewsMap extends NewsMapper
{

    public function fakeNotify($title, $body, $authorId = 151, $folderId = 48)
    {
        $this->sessionData['user'] = UserMapper::getInstance()->findById($authorId);

        /** @var News $model */
        $model = $this->createModel();

        $model->setHeading($title);
        $model->setBody($body);
        $model->setPublishDate(Alaress_Date_Format::now());
        $model->setExpiryDate(Alaress_Date_Format::now()->addDays(1));
        $model->setCategory(CategoryMapper::getInstance()->findById($folderId));
        $model->setOwner($this->sessionData['user']);
        $model->setPostDate(Alaress_Date_Format::now());

        $this->save($model);

    }

    protected function raiseNotification($object, $verb, $previous = null)
    {
        if (!isset($this->sessionData['user'])) {
            return false;
        }
        $diff = $previous && ($verb != self::METHOD_DELETE) ? $this->diff($object, $previous) : null;
        // create a new event, and send it to the scheduler
        $event = new Event($this->sessionData['user'], $verb, $object, NewsMapper::getInstance(), $diff);
        $this->getNotifier()->setTopic(Notif::TOPIC_APPLICATION)->notice(serialize($event));
        return true;
    }

}

$sm = SchoolNewsMap::getInstance();
$hm = HomepageNewsMap::getInstance();

$body = <<<EOL
<p>Bacon ipsum dolor amet ipsum eiusmod consectetur pastrami in meatball.  Voluptate in prosciutto biltong.  Pork belly eiusmod biltong in short loin, deserunt pancetta sunt pariatur leberkas incididunt nisi dolore kevin sed.  Jowl adipisicing porchetta alcatra voluptate kevin pastrami magna irure frankfurter boudin ham turducken non.  Porchetta kielbasa ut doner.</p>
<p>Brisket t-bone ex, occaecat boudin ball tip ullamco ut cow commodo filet mignon tongue aliqua tail.  Non fugiat et, aliqua nostrud hamburger dolor.  Tenderloin aute tongue et.  Spare ribs meatloaf salami porchetta, biltong shankle tongue consectetur jowl non dolore.  Frankfurter hamburger pork belly nulla, beef ribs kevin deserunt exercitation.</p>
<p>Tail non ball tip, pancetta corned beef voluptate magna eu deserunt laborum cupidatat.  Reprehenderit ham hock veniam salami magna, elit irure flank proident.  Ball tip cow mollit turkey ground round.  Velit laboris ball tip ut ullamco adipisicing veniam pork belly.</p>
<p>Pork loin fatback officia dolore pig.  Cupidatat reprehenderit eiusmod tongue labore do brisket turkey jowl adipisicing dolore deserunt in mollit.  Venison labore pork chop picanha.  Pig sed pork tenderloin, doner sint qui corned beef swine.  Sunt reprehenderit irure, ball tip velit dolore nostrud tenderloin jowl.  Beef ad meatball officia pig doner pork loin in.</p>
<p>Filet mignon boudin ground round, corned beef cupidatat veniam nulla qui andouille.  Veniam laboris short ribs, consectetur commodo elit picanha pork chop irure.  Andouille magna incididunt, deserunt tongue shoulder hamburger occaecat.  Porchetta shankle jerky shank corned beef in venison sausage.  Pastrami flank anim turducken aliquip hamburger strip steak ullamco brisket dolore ea.  Mollit excepteur pork picanha.</p>
EOL;

// randomise the paragraphs
$body = explode("\n", $body);

for ($i = 0; $i < 5; ++$i) {
    shuffle($body);
    $body1 = implode("\n", $body);

    shuffle($body);
    $body2 = implode("\n", $body);

    $title = TextFormatter::cutSentence($body[0], 80);

    $sm->fakeNotify($title, $body1);
    $hm->fakeNotify($title, $body2);

    echo '.';
}

echo "\n";

