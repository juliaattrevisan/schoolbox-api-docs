<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\DialogAgent;

use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\Dialogagent\Response\Model as ResponseModel;

class Notifier extends AbstractModuleNotifier
{
    public const TRIGGER_REOPENED = 'reopen';
    public const TRIGGER_RESET = 'reset';


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
        /** @var ResponseModel $response */
        $response = $bundle->getEvent()->getObject();

        $crudMethod = $bundle->getMethod();

        if (\Schoolbox\Mapper::METHOD_UPDATE === $crudMethod) {
            $bundle->attach(self::TRIGGER_REOPENED, self::IMMEDIATELY);
            $bundle->attach(self::TRIGGER_RESET, self::IMMEDIATELY);
        }
    }


    /**
     * Notify relevant users when a response was put into Reopened state:
     *     - submitted_at is null now, but previously wasn't
     *
     * @param string $roleType
     * @param ResponseModel $model
     * @param User $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyReopened(string $roleType, ResponseModel $model, User $effector, ?array $diff): Envelope
    {
        print_r($diff);
    }


    /**
     * Notify relevant users when a response was reset:
     *     - authoritative is 0 now, but previously wasn't
     *
     * @param string $roleType
     * @param ResponseModel $model
     * @param User $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyReset(string $roleType, ResponseModel $model, User $effector, ?array $diff): Envelope
    {
        print_r($diff);
    }
}
