<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\DialogAgent;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Model as ResponseModel;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\MapperFactory;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Notifier extends AbstractModuleNotifier
{
    public const TRIGGER_REOPENED = 'reopen';
    public const TRIGGER_RESET = 'reset';


    /** Text for each notification
     */
    private const PATTERN_SOMEONE_REOPENED_TITLE = ':effector reopened :action';


    /**
     * A link to the summary screen, for now
     */
    private const PATTERN_HREF = '/learning/assessments/%d/%d';


    /**
     * @var UserMapper
     */
    private UserMapper $userMapper;


    /**
     * @inheritdoc
     */
    protected function getSlug()
    {
        return NSMapper::NS_DIALOG_AGENT;
    }


    /**
     * Define the mappers you need in this notifier. This presumably lets you bypass the implicit expectation in
     * AbstractModuleNotifier's setMapper() that Notifier.php be directly adjacent to a corresponding Mapper/Model.
     */
    public static function getSetters(ContainerInterface $di): array
    {
        /** @var MapperFactory $mf */
        $mf = $di->get('mapperFactory');

        return [
            /* We presumably got a Response domain objects whose corresponding mapper is actually a mapper proxy...
             * .............which sounds really weird here fyi
             */
            'setMapper'     => $di->get('dialogAgentResponseDataSource'),
            'setUserMapper' => $mf->get(UserMapper::class),
        ];
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
     * Notify students when a response was put into Reopened state:
     *     - submitted_at is null now, but previously wasn't
     *
     * @param ResponseModel $model
     * @param User $effector
     * @param array|null $diff
     * @return Envelope
     * @throws Exception
     * @throws Deleted
     */
    public function notifyStudentReopened(ResponseModel $model, User $effector, ?array $diff): Envelope
    {
        /* I guess we refetch this to ensure we got all the parts, but this seems either wrong or unnecessary
         *
         * TODO hopefully Amri can help me understand; for whatever reason this was necessary in Moment/Notifier.php
         *  but that makes me think that it shouldn't be considered the canonical way of making these notifiers
         */
        $response = $this->mapper->findById($model);

        /* Define the recipient
         */
        $student = $this->userMapper->findById($response->getCreatedBy());

        /* Craft a url that sends the recipient somewhere meaningful when clicking the notification
         */
        $href = sprintf(self::PATTERN_HREF, $response->getAssessable()->getId(), $student->getId());

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
        $envelope->targetToUser($student, $instance);

        return $envelope;
    }


    /**
     * Notify students when a response was reset:
     *     - authoritative is 0 now, but previously wasn't
     *
     * @param ResponseModel $model
     * @param User $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentReset(ResponseModel $model, User $effector, ?array $diff): Envelope
    {
        print_r($diff);
    }
}
