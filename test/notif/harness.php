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

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Assessment Return Notifications', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, $log, $opts) {
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

    // mappers
    $workMap = Component\SubmissionBoxMapper::getInstance();
    $workMap->setSessionData(['user' => $zsmith]);
    $rtnMap  = Component\SubmissionBox\ReturnedWorkMapper::getInstance();
    $rtnMap->setSessionData(['user' => $zsmith]);
    $quizMap = Component\OnlineAssessmentMapper::getInstance();
    $quizMap->setSessionData(['user' => $zsmith]);
    $attMap  = Component\OnlineAssessment\AttemptMapper::getInstance();
    $attMap->setSessionData(['user' => $zsmith]);

    // connections
    $redis = Alaress\Redis::getClientInstance();

    // "jerry springer show"
    $work = $workMap->findById(54);

    // "lesson 1 - pretest"
    $quiz = $quizMap->findById(39);

    // "obfuscate!"
    $work->setMarkVisibility(0);
    $quiz->setMarkVisibility(0);

    // save that
    $log->info("Set Work visibility to 0");
    $workMap->save($work);
    $log->info("Set Quiz visibility to 0");
    $quizMap->save($quiz);

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

    // quiz: first create an attempt without a reviewed_at, then review it
    $attempt = $attMap->hydrate([
        'assess_id' => $quiz->getId(),
        'user_id'   => $drhodes->getId(),
        'start'     => Alaress_Date_Format::now()->addDays(-1)->getDatabase(),
        'finish'    => Alaress_Date_Format::now()->addDays(-1)->addHours(1)->getDatabase(),
    ]);

    $log->info("Save new Quiz Attempt record");
    $attMap->save($attempt);

    $attempt->setReviewed(Alaress_Date_Format::now());

    $log->info("Update Quiz Attempt record with review date");
    $attMap->save($attempt);

    for ($i = 1; $i < 3; ++$i) {
        // sleeeeeeeep...
        wait($log);

        stateCheck($log, $redis, $drhodes, $prhodes, $i-1);

        $log->info("Set Work visibility to $i");
        $work->setMarkVisibility($i);
        $workMap->save($work);

        $log->info("Set Quiz visibility to $i");
        $quiz->setMarkVisibility($i);
        $quizMap->save($quiz);
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
    switch ($expected) {
        case 0: // staff only
            assertNoMessages($log, $redis, $student);
            assertNoMessages($log, $redis, $parent);
            break;
        case 1: // + students
            assertMessages($log, $redis, $student);
            assertNoMessages($log, $redis, $parent);
            break;
        case 2: // + parents
            assertMessages($log, $redis, $student);
            assertMessages($log, $redis, $parent);
            break;
    }
}


/**
 * @param LoggerInterface $log
 * @param Redis           $redis
 * @param User            $user
 */
function assertMessages(LoggerInterface $log, Redis $redis, User $user)
{
    $userId   = $user->getId();
    $userName = $user->formatName();
    $str      = "User $userId ($userName)";

    $msgCount = count($members = $redis->smembers("msg:vagrantx:q:$userId:listed"));

    switch ($msgCount) {
        case 2:
            $log->info("$str has 2 messages, as expected");
            break;
        case 1:
            $log->error("$str only received 1 message; 2 expected: interrogation required");
            $log->warning($members[0]);

            $data = json_decode(gzuncompress(`redis-cli get {$members[0]}`), true);
            $a = reset($data);
            $title = reset($a);
            $log->warning($title);

            break;
        case 0:
            $log->alert("$str received no messages; 2 expected");
            break;
        default:
            $log->error("$str somehow has $msgCount messages; 2 expected");
    }

}


/**
 * @param LoggerInterface $log
 * @param Redis           $redis
 * @param User            $user
 */
function assertNoMessages(LoggerInterface $log, Redis $redis, User $user)
{
    $userId   = $user->getId();
    $userName = $user->formatName();
    $str      = "User $userId ($userName)";

    $msgCount = count($members = $redis->smembers("msg:vagrantx:q:$userId:listed"));

    switch ($msgCount) {
        case 2:
            $log->alert("$str received 2 messages; 0 expected");
            break;
        case 1:
            $log->alert("$str only received 1 message; 0 expected: interrogation required");
            $log->warning($members[0]);

            $data = json_decode(gzuncompress(`redis-cli get {$members[0]}`), true);
            $a = reset($data);
            $title = reset($a);
            $log->warning($title);

            break;
        case 0:
            $log->info("$str received no messages, as expected");
            break;
        default:
            $log->error("$str somehow has $msgCount messages; 0 expected");
    }

}


/**
 * @param LoggerInterface $log
 * @param int             $ttl
 */
function wait(LoggerInterface $log, $ttl = 30)
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
