<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Async\Notification\Parameter\Param;
use Alaress\Routing\Dictionary\SubscriptionDictionary;
use Alaress\Routing\Router;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Quiz;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Routing\Cache\Redis\ReadCache;
use Schoolbox\Realtime\Test\AbstractNotificationTest;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

/**
 * Class QuizNotifications
 *
 * It checks quiz notifications.
 */
class QuizNotifications extends AbstractNotificationTest
{

    /** @var Folder\Model */
    protected $folder;

    /** @var  WorkType */
    protected $quizWorkType;

    /** @var Collection|User[] */
    protected $subscribers;

    /** @var int */
    protected $folderId;

    /** @var int */
    protected $adminId;

    /** @var array */
    protected $markGivenCache;

    /**
     * How many mark notifs have been sent to students?
     *
     * Terminate the test when all students have been notified about all quizzes they should be.
     * @see $this->onWorkerComplete
     *
     * @var int
     */
    protected $sentMarkNotifs = 0;

    /**
     * Provide the list of daemons that need to be attached for this test
     *
     * @return array
     */
    public function requiredDaemons()
    {
        return [ self::DAEMON_SCHEDULE, self::DAEMON_ROUTE ];
    }

    /**
     * The test is complete when all students have received notifications for
     * all quizzes that they should receive notifications for.
     *
     * @return bool
     */
    protected function isTestComplete()
    {
        $maxNotifsSendable = count($this->subscribers) * 2;
        return $this->sentMarkNotifs >= $maxNotifsSendable;
    }

    /**
     * This test has no stages.
     *
     * @param int $stage
     */
    protected function goToStage($stage)
    {
    }

    /**
     * Perform any setup required
     *
     * @return void
     */
    public function prepare()
    {
        $this->weaponsToMaximum();
        $this->clearRedis();
        $this->clearRabbit();

        // fetch the group with the largest number of subscribers
        $this->folderId = Database::getInstance()->query('
            SELECT sid
            FROM userAccess ua
            JOIN folder f ON f.id = ua.sid AND ua.service = 1
            WHERE f.deleted_at IS NULL
            AND ua.type > 0
            GROUP BY sid
            ORDER BY COUNT(sid) DESC
            LIMIT 1
        ')->fetchColumn();

        $this->adminId = Database::getInstance()->query('
            SELECT uid
            FROM userAccess
            WHERE `type` = 3
            AND service = 1
            AND sid = ?
        ', [ $this->folderId ])->fetchColumn();

        $this->folder = Folder\Mapper::getInstance()->findById($this->folderId);

        $this->subscribers = $this->getSubscribers($this->folderId);

        // erase any existing quizzes in the target folder
        Quiz\Mapper::getInstance()->deleteByColumnList([
            'category_id'  => $this->folderId,
            'availability' => 'assess'
        ]);

        // erm... "quiz"
        $this->quizWorkType = WorkTypeMapper::getInstance()->findById(11);
    }


    /**
     * @routed    Listening on redis://routed
     * @scheduled Listening on redis://scheduled
     */
    public function onReady()
    {
        // start generating quizzes
        $this->log->notice('Gettin yo quiz on', [ 'char' => '>' ]);

        // load up the quiz instances
        $quizzes = [];

        // set these up 5 seconds apart
        $quizzes[] = $this->generateQuiz(IAssessable::MARK_VISIBILITY_PRIVATE, '1 second', '5 second');
        $quizzes[] = $this->generateQuiz(IAssessable::MARK_VISIBILITY_STUDENT, '6 second', '10 second');
        $quizzes[] = $this->generateQuiz(IAssessable::MARK_VISIBILITY_PARENT_STUDENT, '11 second', '15 second');

        foreach ($quizzes as $quiz) {
            $this->saveQuiz($quiz, $this->getUser($this->adminId));
        }
    }


    /**
     * @routed SEND (?P<messageId>[0-9a-f]{40}:[0-9a-f]{40}) -> user (?P<recipientId>\d+)
     *
     * @param string $messageId
     * @param int    $recipientId
     */
    public function onReceipt($messageId, $recipientId)
    {
        $message   = $this->fetchMessageInstance($messageId)->jsonSerialize();
        $recipient = $this->getUser($recipientId);

        if (preg_match('`/assess/[A-Za-z]+/(\d+)`', $message['href'], $matches)) {
            list (, $quizId) = $matches;
        } else {
            // This message is targeted to the parent, and therefore doesn't reference the quiz.
            // Extract quiz ID out of topics
            foreach ($message['topics'] as $topic) {
                if (preg_match('`INFO\\.notify\\.parent\\.\\d+\\.assess\\.(\\d+).updated`', $topic, $matches)) {
                    list (, $quizId) = $matches;
                    break;
                }
            }

            if (empty($quizId)) {
                $this->log->notice("For some reason, href '{$message['href']}' doesn't match expectation");
                return;
            }
        }

        switch ($message['title']['text']) {
            // new quiz
            case ':username opened a Quiz, :quizname, in :catname':
                // if the recipient is a student, attempt the quiz, otherwise ignore
                if (!$recipient->getRole()->isStudent()) {
                    $this->log->info('Not student; ignoring');
                    return;
                }

                $this->attemptQuiz(Quiz\Mapper::getInstance()->findById($quizId), $recipient);
                return;

            // new attempt
            case ':student attempted :quiz in :category':
                // if you staff, you handle this
                if (!$recipient->getRole()->isStaff()) {
                    $this->log->info('Not staff; ignoring');
                    return;
                }

                // extract the student
                if (!isset($message['title']['params']['student'])) {
                    $this->log->warning("Student param doesn't exist");
                    return;
                }

                $param = $message['title']['params']['student'];

                if (!$param instanceof Param) {
                    $this->log->warning("Param, uh, isn't a param...");
                    return;
                }

                if (!preg_match('`^/search/user/(\d+)$`', $param->getHref(), $matches)) {
                    $this->log->warning('Something went wrong extracting the student from the parameter');
                    return;
                }

                $attempts = Quiz\Attempt\Mapper::getInstance()->findByColumnList([
                    'user_id'   => $matches[1],
                    'assess_id' => $quizId
                ]);

                foreach ($attempts as $attempt) {
                    $this->markAttempt($attempt, $recipient);
                }

                return;

            case ":staff has marked :child's attempt of :quiz in :cat":
                // parent has received mark notification: verify that should have happened
                $this->assertTrue(Quiz\Mapper::getInstance()->findById($quizId)->isMarkShownToParents());
                $this->log->notice("Parent {$recipient->formatName()} has correctly received mark notification");

                return;

            case ':staff has marked your attempt of :quiz in :cat':
                // student has received mark notification: verify that should have happened
                $this->assertTrue(Quiz\Mapper::getInstance()->findById($quizId)->isMarkShownToStudents());
                $this->log->notice("Student {$recipient->formatName()} has correctly received mark notification");
                $this->sentMarkNotifs++;
                return;

            case ":child's Quiz :quizname was updated in :catname":
            case ':author updated :quizname in :catname':
                // as part of this test setting up the description gets altered, so these are expected and ignorable
                return;

            default:
                $this->log->error("Don't know how to handle: '{$message['title']['text']}'", [ 'char' => '?' ]);
                return;
        }
    }


    /**
     * @scheduled Quiz (?P<quizId>\d+) mark visibility is \d+: (?:suppressing|sending) (?P<roletype>\w+) messages
     *
     * @param int    $quizId
     * @param string $roletype
     */
    public function onMarkGiven($quizId, $roletype)
    {
        if (empty($this->markGivenCache[$quizId])) {
            $this->markGivenCache[$quizId] = [];
        }

        // alias for convenience
        $a =& $this->markGivenCache[$quizId];

        if (empty($a[$roletype])) {
            $a[$roletype] = 0;
        }

        $a[$roletype]++;

        $tgt = $this->subscribers->count();

        $this->log->debug(sprintf("Quiz %d -> %s: %d / %d", $quizId, $roletype, $a[$roletype], $tgt), [ 'char' => '<' ]);

        if (isset($a['parent']) && isset($a['student']) && $a['student'] == $a['parent'] && $a['student'] == $tgt) {
            // at this point, all the marks have been given.

            // drop the cache for this quiz
            unset($this->markGivenCache[$quizId]);

            $qm = Quiz\Mapper::getInstance();

            // raise the steaks. I mean visibility. But also, steak.
            $quiz = $qm->findById($quizId);

            if (!$quiz->isMarkShownToParents()) {
                $newVis = $quiz->getMarkVisibility() + 1;
                $this->log->notice("Raising visibility of quiz $quizId to $newVis", [ 'char' => '>' ]);
                $quiz->setMarkVisibility($newVis);

                $qm->setSessionData([ 'user' => $quiz->getUser() ]);
                $qm->save($quiz);
            }
        }
    }

    /**
     * @param int  $folderId
     * @param bool $recurseOnFail
     * @throws InvalidArgumentException
     * @throws UnderflowException
     * @return Collection
     */
    private function getSubscribers($folderId, $recurseOnFail = true)
    {
        $router = new Router(new SubscriptionDictionary('.'));

        // TODO replace with $di->get('subscriptionCache.readonly'), and set log
        $redisCache = new ReadCache($this->redis);
        $redisCache->setLog($this->log);
        $redisCache->readIntoRouter(INSTANCE_ID, $router);

        $subscribers = array_keys($router->get("*.notify.service.1.$folderId.1.*"));

        if (empty($subscribers) && $recurseOnFail) {
            $this->cacheSubscriptions();
            return $this->getSubscribers($folderId, false);
        }

        $users = [];

        // get references to all these people
        foreach ($subscribers as $userId) {
            $users[$userId] = $this->getUser($userId);
        }

        return (new Collection($users))->filter(
            function (User $user) {
                return $user->getRole()->isStudent();
            }
        );
    }


    /**
     * @param Quiz\Model $quiz
     * @param User $as
     */
    protected function attemptQuiz(Quiz\Model $quiz, User $as)
    {
        // 1. Know your quiz
        $this->log->notice("Attempting quiz {$quiz->getId()} as {$as->formatName()}", [ 'char' => '+' ]);

        // 2. get the questions for your quiz, you'll need them later
        $meta = json_decode($quiz->getDescription(), true);

        // 3. spawn an assess_attempt record
        $aMap = Quiz\Attempt\Mapper::getInstance();
        $aMap->setSessionData(['user' => $as]);

        $this->log->debug("Starting attempt");
        /** @var Quiz\Attempt\Model $attempt */
        $attempt = $this->mapperCreate(Quiz\Attempt\Mapper::getInstance(), [
            'assess_id' => $quiz->getId(),
            'user_id'   => $as->getId(),
            'start'     => Alaress_Date_Format::now()->getDatabase(),
        ]);

        // 4. spawn stub assess_response records for every question_id you know about from step 2
        $rHandler = new Assess_Response();
        $response = $this->handlerCreate($rHandler, [
            'attempt_id'  => $attempt->getId(),
            'section_id'  => $meta['sectionId'],
            'question_id' => $meta['questionId']
        ]);

        // 4.5 update the stubs with data
        $response['response'] = 'TEST RESPONSE';
        $rHandler->update($response);

        // let's just wait a little bit
        sleep(1);

        // 5. finalise the attempt by updating the end time
        $this->log->debug("Finishing attempt");
        $attempt->setFinish(Alaress_Date_Format::now());
        Quiz\Attempt\Mapper::getInstance()->save($attempt);
    }


    /**
     * @param Quiz\Attempt\Model $attempt
     * @param User $as
     */
    protected function markAttempt(Quiz\Attempt\Model $attempt, User $as)
    {
        $student = $this->getUser($attempt->getUser()->getId());

        $this->log->notice("Marking quiz {$attempt->getQuiz()->getId()} for {$student->formatName()} as {$as->formatName()}", [ 'char' => '+' ]);

        $attempt->setReviewed(Alaress_Date_Format::now());

        $aMap = Quiz\Attempt\Mapper::getInstance();
        $aMap->setSessionData([ 'user' => $as ]);

        $aMap->save($attempt);
    }


    /**
     * @param int                        $visibility
     * @param Alaress_Date_Format|string $creation
     * @param Alaress_Date_Format|string $start
     * @param Alaress_Date_Format|string $finish
     * @return Quiz\Model
     */
    protected function generateQuiz($visibility, $creation = 'now', $start = 'now', $finish = '2 days')
    {
        if (!$creation instanceof Alaress_Date_Format) {
            $creation = new Alaress_Date_Format(new DateTimeImmutable($creation));
        }

        if (!$start instanceof Alaress_Date_Format) {
            $start = new Alaress_Date_Format(new DateTimeImmutable($start));
        }

        if (!$finish instanceof Alaress_Date_Format) {
            $finish = new Alaress_Date_Format(new DateTimeImmutable($finish));
        }

        $quiz = Quiz\Mapper::getInstance()->createModel();

        $quiz
            ->setAvailability(Quiz\Model::AVAILABILITY_ASSESS)
            ->setWeight(0)
            ->setDescription(json_encode([ $visibility, $creation->getDatabase(), $start->getDatabase(), $finish->getDatabase() ]))
            ->setCategory($this->folder)
            ->setCreated($creation)
            ->setStart($start)
            ->setFinish($finish)
            ->setDuration(120)
            ->setMarkVisibility($visibility)
            ->setMaxAttempts(1)
            ->setNotifyAuthor(false)
            ->setTitle(
                sprintf(
                    'TEST QUIZ: visibility=%d; open=%s~%s; created=%s',
                    $visibility,
                    str_replace(' ', '_', $start->getDatabase()),
                    str_replace(' ', '_', $finish->getDatabase()),
                    str_replace(' ', '_', $creation->getDatabase())
                )
            )
            ->setWorkType($this->quizWorkType)
        ;

        return $quiz;
    }


    /**
     * Helper function for persisting a Quiz entity, requiring declaration of who is performing the action.
     *
     * @param Quiz\Model $quiz
     * @param User $as
     * @return void
     */
    protected function saveQuiz(Quiz\Model $quiz, User $as)
    {
        // define the effector as the author
        $quiz->setUser($as);

        // ensure the mapper thinks this is the effector
        $oam = Quiz\Mapper::getInstance();
        $oam->setSessionData([ 'user' => $as ]);
        $oam->save($quiz);

        // FUN FACT: THESE HAVEN'T BEEN MAPPERED
        $section  = $this->handlerCreate(new Assess_Section(), [
            'assess_id'   => $quiz->getId(),
            'sequence'    => 1,
            'title'       => 'TEST SECTION',
            'description' => '',
        ]);

        $question = $this->handlerCreate(new Assess_Question(), [
            'section_id' => $section['section_id'],
            'sequence'   => 1,
            'type_id'    => 1,
            'marks'      => 10,
            'question'   => '<p>TEST QUESTION</p>'
        ]);

        $meta = json_decode($quiz->getDescription(), true);
        $meta['sectionId']  = $section['section_id'];
        $meta['questionId'] = $question['question_id'];

        // keep tabs on this question ID so I can get it easily later
        $quiz->setDescription(json_encode($meta));
        $oam->save($quiz);
    }


    /**
     * Returns the given array but with the primary key populated
     *
     * @param DeprecatedModel $handler
     * @param array           $model
     * @return array
     */
    private function handlerCreate(DeprecatedModel $handler, array $model)
    {
        if ($id = $handler->create($model)) {
            $model[$handler->_index] = $id;
            return $model;
        }

        return null;
    }


    /**
     * @param Mapper $mapper
     * @param array  $model
     * @throws Mapper\Exception
     * @return object
     */
    private function mapperCreate(Mapper $mapper, array $model)
    {
        $ob = $mapper->hydrate($model);
        $mapper->create($ob);
        return $ob;
    }
}
