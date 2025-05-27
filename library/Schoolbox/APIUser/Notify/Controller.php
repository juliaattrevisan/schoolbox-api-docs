<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIUser\Notify;

use Alaress\Async\Notification\Instance\Builder as NotificationInstanceBuilder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter;
use Alaress\Async\Queue\Composite;
use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Monolog\Logger;
use Predis\ClientInterface;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\MessageList\Listed;
use Schoolbox\Realtime\MessageList\Unread;
use Schoolbox\User;

class Controller extends Base
{
    private ClientInterface $redisStorage;

    public function __construct(
        ClientInterface $redisStorage
    ) {
        $this->redisStorage = $redisStorage;
    }

    /**
     * Send a test notification to a user.
     *
     * @param User $user
     * @return void
     * @api POST /api/user/{userId}/notify
     */
    public function apiUserNotify(User $user): void
    {
        $data = $this->request->getParsedBody();
        //check for required fields
        if ('' === ($data['message'] ?? '')) {
            $problem        = (new ApiProblem("'message' is not set"))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return; //exit early the message field is not set...
        }
        try {
            $bob = new NotificationInstanceBuilder(validate: false);

            // Note that setting href to # has the effect of automarking the
            // notification as read: see SA-17727.
            // SA-19820 specifically works around this if the href is an empty
            // anchor.
            $bob->setTitle($data['message'] ?? '')
                ->setAllowSelfSend(true)
                ->setSeverity(Logger::INFO)
                ->setNamespace(NSMapper::NS_MESSAGE)
                ->setHref($data['href'] ?? '#')
                ->setIcon('/images/icon.php?size=192&icon=' . NSMapper::NS_MESSAGE)
                ->addTopic(sprintf('%s.user.%d', Instance::DELIVERY_INSTANT, $user->getId()))
                ->putMeta('title', 'Message from ' . ($data['from']['fullName'] ?? 'Unknown User'));

            // Attach any messageParams as well
            foreach ($data['messageParams'] ?? [] as $key => $value) {
                if (isset($value)) {
                    $bob->setParam(
                        $key,
                        new Parameter\Param(
                            $value,
                            stacking: Parameter\ParameterInterface::STACKING_REPLACE_NO_DECORATION,
                        ),
                    );
                }
            }

            $composite = new Composite(
                new Listed($this->redisStorage, INSTANCE_ID, $user->getId()),
                new Unread($this->redisStorage, INSTANCE_ID, $user->getId())
            );
            $composite->push($bob->build());
        } catch (\Exception $e) {
            $problem        = (new ApiProblem($e->getMessage()))
                ->setStatus(500)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(500);
            return;
        }
    }
}
