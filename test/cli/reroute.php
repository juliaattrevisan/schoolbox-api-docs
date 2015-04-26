<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Lumberjack\Logger;
use React\Stomp\Protocol\Frame;
use Schoolbox\Notif\Silent\SilentQueueFactory;
use Schoolbox\Notif\SilentNotif;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Rerouter\QueueSorter;
use Schoolbox\Realtime\Rerouter\Filter;
use Schoolbox\UserMapper;
use Schoolbox\User;

require_once __DIR__ . '/../../include/Bootstrap.php';

$rerouteFilters = [
    new Filter\AlreadyReroutedFilter(),
//    new Filter\SelfSendFilter(),
//    new Filter\CampusMembershipFilter(),
//    new Filter\RoleMembershipFilter(),
//    new Filter\RoletypeFilter(),
];

$qf   = new SilentQueueFactory();
$log  = new Logger(STDOUT, STDERR, 100);
$not  = new SilentNotif($log);
$rpm  = RoutePermissionMapper::getInstance();
$sort = new QueueSorter($rerouteFilters, $log, $not, $qf, $rpm);

/* now the fun part..... :( */

$bob   = new Builder(false);

$user  = 'alaress';
$topic = 'notify.user.19.blog.534.followed';

$bob->setHref('/foobar')
    ->setTitle('You gon bin foobar\'ded')
    ->setSeverity(\Monolog\Logger::WARNING)
    ->setTopics([ $topic ]);

// construct a target instance into a Frame...just so it can be unwrapped?? -_-
$frame = new Frame(null, [], json_encode([
    'message' => json_encode($bob->build()),
    'channel' => $topic,
    'context' => []
]));

/** @var User $user */
$user = UserMapper::getInstance()->findOneByColumn('username', $user);

$sort->process($user, $frame);
