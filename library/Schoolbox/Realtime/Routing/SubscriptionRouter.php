<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing;


use Psr\Log\LoggerInterface;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Protocol\Frame;

class SubscriptionRouter
{

    /** @var SubscriptionManager */
    protected $manager;

    /** @var Client */
    protected $stomp;

    /** @var LoggerInterface */
    protected $log;


    /**
     * @param SubscriptionManager $manager
     * @param Client              $stomp
     * @param LoggerInterface     $log
     */
    public function __construct(SubscriptionManager $manager, Client $stomp, LoggerInterface $log)
    {
        $this->manager = $manager;
        $this->stomp   = $stomp;
        $this->log     = $log;
    }


    /**
     * Start listening to a particular instance
     *
     * @param string $instanceId
     * @return void
     */
    public function listen($instanceId)
    {
        $this->stomp->subscribeWithAck("$instanceId.route", 'client-individual', function ($frame, $resolver) {
            $this->onRoute($frame, $resolver);
        });
    }


    /**
     * @param Frame       $frame
     * @param AckResolver $resolver
     * @return void
     */
    protected function onRoute(Frame $frame, AckResolver $resolver)
    {
        try {
            if ($this->handle(json_decode($frame->body, true))) {
                $resolver->ack();
                return;
            }

        } catch (\Exception $e) {
            $this->log->error($e->getMessage());
        }

        $resolver->nack();
    }


    /**
     * @param array  $data
     * @return bool
     */
    protected function handle(array $data)
    {
        $topic = "{$data['level_name']}.{$data['channel']}";

        try {
            $userIds = $this->manager->getSubscribers($topic);

            $this->log->info("Routing message via '$topic' to " . json_encode($userIds));

            // TODO dispatch!

        } catch (\InvalidArgumentException $e) {
            $this->log->info("Nobody subscribed to '$topic'");
        } catch (\Exception $e) {
            // assume we did not successfully route all the things
            return false;
        }

        return true;
    }

}
