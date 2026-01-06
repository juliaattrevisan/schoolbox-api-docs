<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Model as ResponseDataModel;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\User;

/**
 * Useful diagnostics:
 *
 * Check scheduled logs to see what happened at runtime
 *     docker:  sudo docker/logs schoolbox-scheduled -f
 *     vagrant: sudo journalctl -u schoolbox-cloud-scheduled@vagrantx -f
 *
 * Restart the scheduled
 *     docker:  sudo docker/compose restart schoolbox-scheduled
 *     vagrant: sudo /usr/share/schoolbox/tools/notif/allrestart
 *
 */
class Notifier extends AbstractModuleNotifier
{
    /**
     * These triggers dynamically compute to generate a callback method. Your trigger names must correspond exactly to
     * msg_source.key - any mismatch returns an error message about a trigger not having callbacks.
     *
     * e.g. Trigger for 'Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Model:1 reopen' has no callbacks
     *
     */
    public const TRIGGER_REOPENED = 'reopened';
    public const TRIGGER_RESET = 'reset';


    /** Text for each notification
     */
    private const PATTERN_SOMEONE_REOPENED_TITLE = ':effector reopened :action';


    /**
     * A link to the summary screen, for now
     */
    private const PATTERN_HREF = '/learning/assessments/%d/%d';


    /**
     * @inheritdoc
     */
    protected function getSlug()
    {
        return NSMapper::NS_DIALOG_AGENT;
    }


    /**
     * Attaches and detaches triggers properly depending on the dialog agent response status and transition
     *
     * @param TriggerBundleInterface $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        $crudMethod = $bundle->getMethod();

        if (\Schoolbox\Mapper::METHOD_UPDATE === $crudMethod) {
            $bundle->attach(self::TRIGGER_REOPENED, self::IMMEDIATELY);
            $bundle->attach(self::TRIGGER_RESET, self::IMMEDIATELY);
        }
    }


    /**
     * Notify students when a response was put into Reopened state:
     *     - submitted_at is null now, but previously wasn't
     *
     * @param ResponseDataModel $model
     * @param User $effector
     * @param array|null $diff
     * @return Envelope
     * @throws Exception
     * @throws Deleted
     */
    public function notifyStudentReopened(ResponseDataModel $model, User $effector, ?array $diff): Envelope
    {
        if ($diff['submittedAt'][0] === $diff['submittedAt'][1]) {
            print_r(sprintf(
                '%s is equivalent to %s; this was not reopened',
                $diff['submittedAt'][0],
                $diff['submittedAt'][1]
            ));
        }

        /* Models with deep hierarchies might not be fully hydrated, or hydrated enough for you to get the parts you need.
         * You can refetch using a mapper to get more hydrated parts if you needed them.
         */
        /** @var ResponseDataModel $response */
        $response = $this->mapper->findById($model->getId());

        /* Define the recipient; all we need is their id
         */
        $recipientId = $response->getLearner()->getId();

        /* Craft a url that sends the recipient somewhere meaningful when clicking the notification
         */
        $href = sprintf(self::PATTERN_HREF, $response->getDialogAgent()->getAssessmentModel()->getId(), $recipientId);

        /* Basically string replacements to compose the message in the notification
         */
        $bob = (new Builder())
            ->setTitle(self::PATTERN_SOMEONE_REOPENED_TITLE)
            ->setIcon($this->getUserIcon($effector))
            ->setHref($href)
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::INFO)
            ->addTopic("{$this->getSlug()}.{$response->getId()}." . self::TRIGGER_REOPENED)
            ->setTimestamp($model->getCreatedAt()->getUnixTimeStamp())
            /* Basically string replacements to compose the message in the notification
             */
            ->setParams([
                'action' => new Param('your discussion activity response', $href, Param::STACKING_REPLACE_NO_DECORATION),
                'effector' => new UserParam($effector)
            ]);

        /* Craft the message we're gonna send
         */
        $instance = $bob->build();

        /* Slide that $instance into the $envelope and send it to recipients
         */
        $envelope = new Envelope();
        $envelope->targetToUser($recipientId, $instance);

        return $envelope;
    }


    /**
     * Notify students when a response was reset:
     *     - authoritative is 0 now, but previously wasn't
     *
     * @param ResponseDataModel $model
     * @param User $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentReset(ResponseDataModel $model, User $effector, ?array $diff): Envelope
    {
//        print_r($diff);

        return new Envelope();
    }
}
