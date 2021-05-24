<?php

/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 2016-05-21
 * Time: 15:16
 */

use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Assessment\Component;
use Schoolbox\UserMapper;
use Schoolbox\User;
use Predis\ClientInterface as Redis;
use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Assessment Return Notifications',
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

$kernel->run(function ($di, $loop, $log, $opts) {
    /**
     * @var Di\Container $di
     * @var LoopInterface $loop
     * @var LoggerInterface $log
     * @var array $opts
     */

    // waaaait
    wait($log, 10);

    // start by setting up

    // users
    $drhodes = UserMapper::getInstance()->findById(230);
    $prhodes = UserMapper::getInstance()->findById(74);
    $zsmith  = UserMapper::getInstance()->findById(151);

    // connections
    $redis = Alaress\Redis::getClientInstance();

    // mappers
    $workMap = Component\SubmissionBoxMapper::getInstance();
    $workMap->setSessionData(['user' => $zsmith]);
    $rtnMap  = Component\SubmissionBox\ReturnedWorkMapper::getInstance();
    $rtnMap->setSessionData(['user' => $zsmith]);

    // "jerry springer show"
    $work = $workMap->findById(54);
    $work->setMarkVisibility(0);

    // save that
    $log->info("Set Work visibility to 0");
    $workMap->save($work);


    //
    // now is the fun part... injecting marks (hint: not fun)
    //

    // work: just save a return
    $log->info("Save new Work Return record");
    $rtnMap->save($rtnMap->hydrate([
        'box_id'    => $work->getId(),
        'marker'    => $zsmith->getId(),
        'owner'     => $drhodes->getId(),
        'comment'   => 'INJECTION',
        'mark'      => 'INJECTION',
        'norm_mark' => 85,
    ]));


    for ($i = 1; $i < 3; ++$i) {
        // sleeeeeeeep...
        wait($log);

        stateCheck($log, $redis, $drhodes, $prhodes, $i - 1);

        $log->info("Set Work visibility to $i");
        $work->setMarkVisibility($i);
        $workMap->save($work);
    }

    // sleeeeeeeep...
    wait($log);

    stateCheck($log, $redis, $drhodes, $prhodes, 2);
});


/**
 * @param LoggerInterface $log
 * @param Redis           $redis
 * @param User            $student
 * @param User            $parent
 * @param int             $expected
 */
function stateCheck(LoggerInterface $log, Redis $redis, User $student, User $parent, $expected)
{
    $studentMsgs[] = ":teacher has marked your work for :boxname in :catname";
    $parentMsgs[]  = ":teacher has marked :student's work for :boxname in :catname";

    switch ($expected) {
        case 0: // staff only
            assertMessageReceipt($log, $redis, $student, []);
            assertMessageReceipt($log, $redis, $parent, []);
            break;
        case 1: // + students
            assertMessageReceipt($log, $redis, $student, $studentMsgs);
            assertMessageReceipt($log, $redis, $parent, []);
            break;
        case 2: // + parents
            assertMessageReceipt($log, $redis, $student, $studentMsgs);
            assertMessageReceipt($log, $redis, $parent, $parentMsgs);
            break;
    }
}

/**
 * @param LoggerInterface $log
 * @param Redis           $redis
 * @param User            $user
 * @param array           $expected
 */
function assertMessageReceipt(LoggerInterface $log, Redis $redis, User $user, array $expected)
{
    sort($expected);

    $userId   = $user->getId();
    $userName = $user->formatName();
    $str      = "User $userId ($userName)";

    $redisData = $redis->smembers("msg:vagrantx:q:$userId:listed");
    $actual    = messageTitles($redisData);

    $actualCount   = count($actual);
    $expectedCount = count($expected);

    $diff = array_diff($actual, $expected);

    if ($actualCount == $expectedCount) {
        // the counts match: so far so good

        if ($expectedCount == 0) {
            // don't interrogate contents for correctness
            $log->info("$str has no messages, as expected");
        } else {
            // check we actually received what we expected
            $log->info("$str has $actualCount message(s), as expected: checking contents");

            if (count($diff)) {
                // show the difference between the arrays
                $log->warning("Messages received did not meet expectation:");
                $log->warning("Diff: " . json_encode($diff));
            } else {
                $log->info("All ok!", ['char' => '+']);
            }
        }
    } elseif ($actualCount == 0) {
        // nothing received; courtesy of the elseif we know this is a mismatch
        $log->error("$str received no messages; $expectedCount expected");
    } elseif ($actualCount > $expectedCount) {
        // ...too many messages..? -_-'
        $log->error("$str somehow has $actualCount message(s); $expectedCount expected");
        $log->warning("Diff: " . json_encode($diff));
    } else {
        // most likely bug scenario: not all expected messages received
        $log->error("$str only received $actualCount message(s); $expectedCount expected: checking contents");
        $log->warning("Diff: " . json_encode($diff));
    }
}


/**
 * @param array $members
 * @return array
 */
function messageTitles($members)
{
    $titles = [];

    foreach ($members as $member) {
        $data = json_decode(gzuncompress(`redis-cli get $member`), true);
        $a = reset($data);
        $titles[] = reset($a);
    }

    sort($titles);

    return $titles;
}


/**
 * @param LoggerInterface $log
 * @param int             $ttl
 */
function wait(LoggerInterface $log, $ttl = 10)
{
    $log->debug("Waiting $ttl seconds to let async processes do their thing");

    $grades = [30,10,5,1];

    foreach ($grades as $g) {
        while ($ttl > $g) {
            sleep($g);
            $ttl -= $g;
            $log->debug($ttl);
        }
    }
}
