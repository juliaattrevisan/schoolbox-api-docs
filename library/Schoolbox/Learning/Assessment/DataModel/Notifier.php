<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Alaress_Date_Format;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\ChildParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;

/**
 * Class Notifier
 */
class Notifier extends AbstractModuleNotifier
{
    // Only trigger I think I need.
    // Will proxy through to plan notifier
    private const TRIGGER_PERSISTED = 'persisted';

    private const TRIGGER_DELETED = 'deleted';

//    private const TRIGGER_DUEWORK_OPEN     = 'dueWorkOpen';
//    private const TRIGGER_DUEWORK_UPDATED  = 'dueWorkUpdated';
//    private const TRIGGER_DUEWORK_UPCOMING = 'dueWorkUpcoming';
//    private const TRIGGER_DUEWORK_OVERDUE  = 'dueWorkOverdue';
//    private const TRIGGER_DUEWORK_CLOSED   = 'dueWorkClosed';
//
//    // Don't think these are even used
////    private const TRIGGER_DUEWORK_DATECHANGED = 'dueWorkDatechanged';
////    private const TRIGGER_DUEWORK_RENAMED     = 'dueWorkRenamed';
//
//    /**
//     * Days in advance to inform a user that due work is upcoming.
//     *
//     * TODO make this configurable
//     */
//    private const DUEWORK_UPCOMING_WINDOW = 2;
//
////    private const TRIGGER_QUIZ_CLOSED   = 'quizClosed';
////    private const TRIGGER_QUIZ_OPEN     = 'quizOpen';
////    private const TRIGGER_QUIZ_OVERDUE  = 'quizOverdue';
////    private const TRIGGER_QUIZ_UPCOMING = 'quizUpcoming';
////    private const TRIGGER_QUIZ_UPDATED  = 'quizUpdated';

    /**
     * @var AssessmentMapper
     */
    private $assessmentMapper;

    /**
     * Irrelevant as this could notify about different things, but must exist.
     *
     * @return string
     */
    protected function getSlug(): string
    {
        return NSMapper::NS_DUEWORK;
    }

    /**
     * Instantiate all your dependencies within yourself.
     *
     * @param mixed $dependencies
     */
    public function loadDependencies($dependencies = null): void
    {
        parent::loadDependencies($dependencies);
    }

    /**
     * Copypasted from pastoral comment notifier.
     *
     * I prefer this way anyway, it divorces notifiers from role type.
     *
     * TODO try to remove this and play within rules of the standard callback system
     *
     * @param string $label
     *
     * @return array
     */
    public function retrieveCallbacks($label): array
    {
        return [
            [$this, "notify" . ucfirst($label)],
        ];
    }

    /**
     * If created, raise a notification. No conditions, no other functions. That's it.
     *
     * @param TriggerBundleInterface $bundle
     */
    public function bundleTriggers($bundle): void
    {
        $bundle->attach(
            $bundle->getMethod() === Mapper::METHOD_DELETE ? self::TRIGGER_DELETED : self::TRIGGER_PERSISTED,
            self::IMMEDIATELY
        );
    }


    /**
     * Notify on create/update of an assessment.
     *
     * This raises no notifications itself, but instead proxies through to the
     * notifiers for sub-object classes.
     *
     * @param Model $assessment
     * @return Envelope
     */
    public function notifyPersisted(Model $assessment): Envelope
    {
        $envelope = new Envelope();

        return $envelope;
    }


    /**
     * Notify on delete of an assessment.
     *
     * This raises no notifications itself, but instead proxies through to the
     * notifiers for sub-object classes.
     *
     * @param Model $assessment
     * @return Envelope
     */
    public function notifyDeleted(Model $assessment): Envelope
    {
        $envelope = new Envelope();

        return $envelope;
    }


    /**
     * Fetches the assessment domain model for this plan.
     *
     * @param Model $assessment
     * @return IAssessable
     * @throws \Aura\Di\Exception\ServiceNotFound  if for whatever reason I couldn't find the data source?
     *                                             Should never happen.
     * @throws \Schoolbox\Mapper\Exception\Deleted if item I'm getting notifs for is deleted
     */
    private function retrieveAssessmentDomainModel(Model $assessment): IAssessable
    {
        //                             ,-.
        //        ___,---.__          /'|`\          __,---,___
        //     ,-'    \`    `-.____,-'  |  `-.____,-'    //    `-.
        //   ,'        |           ~'\     /`~           |        `.
        //  /      ___//              `. ,'          ,  , \___      \
        // |    ,-'   `-.__   _         |        ,    __,-'   `-.    |
        // |   /          /\_  `   .    |    ,      _/\          \   |
        // \  |           \ \`-.___ \   |   / ___,-'/ /           |  /
        //  \  \           | `._   `\\  |  //'   _,' |           /  /
        //   `-.\         /'  _ `---'' , . ``---' _  `\         /,-'
        //      ``       /     \    ,='/ \`=.    /     \       ''
        //              |__   /|\_,--.,-.--,--._/|\   __|
        //              /  `./  \\`\ |  |  | /,//' \,'  \
        //             /   /     ||--+--|--+-/-|     \   \
        //            |   |     /'\_\_\ | /_/_/`\     |   |
        //             \   \__, \_     `~'     _/ .__/   /
        //              `-._,-'   `-._______,-'   `-._,-'
        //
        //          +--------------------------------------+
        //          |       \Schoolbox\Mapper::getDi       |
        //          | Under pain of death DO NOT USE THIS! |
        //          +--------------------------------------+
        //
        // ... hold my beer
        /** @var MapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->mapper::getDi()->get('assessmentDataSource');
        return $assessmentDataSource->findByIdForReview($assessment->getId());
    }


    /**
     * Fetches the assessment data model for this plan.
     *
     * @param Model $assessment
     * @return AssessmentModel
     */
    private function retrieveAssessmentDataModel(Model $assessment): AssessmentModel
    {
        return $this->assessmentMapper->findById($assessment->getId());
    }
}
