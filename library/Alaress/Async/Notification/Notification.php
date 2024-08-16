<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use InvalidArgumentException;

class Notification
{
    /** @var string */
    protected $hash;

    /** @var string */
    protected $baseParamHash;

    /** @var Instance[] */
    protected $instances;

    /**
     * Stores Params per token indexed by instance time. [<token> => <instance time> => Param[] ]
     * @var array
     */
    protected $params;

    /** @var array */
    protected $info;

    /**
     * For sequencing, timestamp always matches the MOST RECENT instance contained within
     * @var int
     */
    protected $timestamp;


    /**
     * Compile a notification from an array of instances
     *
     * @param Instance[] $instances
     */
    public function __construct(array $instances)
    {
        $this->timestamp = 0;

        // ignore indexing
        $instances = array_values((array)$instances);

        // set up initial condition
        $this->initialise($instances[0]);
        $count = count($instances);

        // append any subsequent instances given
        for ($i = 1; $i < $count; ++$i) {
            $this->appendInstance($instances[$i]);
        }
    }


    /**
     * Setup initial condition with the instance given
     *
     * @param Instance $instance
     * @return self
     */
    protected function initialise(Instance $instance): self
    {
        // set comparison hash (message string and href)
        $this->hash = $instance->getBaseHash();

        // clear existing instances
        $this->instances = [];

        // setup empty params array, indexed by token [token => value]
        $this->params = [];
        foreach (array_keys($instance->getParameters()) as $token) {
            $this->params[$token] = [];
        }

        return $this->appendInstance($instance);
    }


    /**
     * Append another instance to this notification
     *
     * @param Instance $instance
     * @return self
     * @throws InvalidArgumentException
     */
    public function appendInstance(Instance $instance): self
    {
        if ($instance->getBaseHash() != $this->hash) {
            throw new InvalidArgumentException("Instance to append does not match initial hash");
        }
        $paramHash = $instance->getParamHash();
        if (isset($this->instances[$paramHash])) {
            // we already have this instance, dismiss
            return $this;
        }

        // store instance
        $this->instances[$paramHash] = $instance;

        // we need a hash that's more relatively unique
        $this->baseParamHash = $instance->getBaseHash() . $paramHash;

        // take timestamp if it's more recent than the one we have already
        if ($instance->getTime() > $this->timestamp) {
            $this->timestamp = $instance->getTime();

            // cache basic information
            $array      = $instance->jsonSerialize();
            $this->info = [
                'title'     => $array['title']['text'],
                'namespace' => $array['namespace'],
                'icon'      => $array['icon'],
                'href'      => $array['href'],
                'meta'      => $array['meta'],
            ];
        }

        // collate params with existing
        foreach ($instance->getParameters() as $token => $param) {
            switch ($param->getMode()) {
                case ParameterInterface::STACKING_REPLACE:
                case ParameterInterface::STACKING_REPLACE_NO_DECORATION:
                case ParameterInterface::STACKING_APPEND:
                    if (!\key_exists($instance->getTime(), $this->params[$token])) {
                        $this->params[$token][$instance->getTime()] = [];
                    }
                    // set timestamp in param, which will be used in sorting params in instant notification
                    $param->setTimestamp($instance->getTime());
                    $this->params[$token][$instance->getTime()][] = $param;
                    break;

                // already set, so only set IF NOT ALREADY SET
                case ParameterInterface::STACKING_IGNORE:
                    if (!count($this->params[$token])) {
                        $this->params[$token] = [$instance->getTime() => [$param]];
                    }
            }
        }

        return $this;
    }


    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->hash;
    }


    /**
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }


    /**
     * Get the :tokenised string of this notification
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->info['title'];
    }


    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->info['namespace'];
    }


    /**
     * @return string
     */
    public function getIcon(): string
    {
        return $this->info['icon'];
    }


    /**
     * @return string
     */
    public function getHref(): string
    {
        return $this->info['href'];
    }


    /**
     * @param string|null $field
     * @return string|array
     */
    public function getMeta(?string $field = null)
    {
        if ($field) {
            return isset($this->info['meta'][$field]) ? $this->info['meta'][$field] : '';
        }
        return $this->info['meta'];
    }


    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->getMeta('body');
    }


    /**
     * @return string
     */
    public function getByline(): string
    {
        return $this->getMeta('byline');
    }


    /**
     * Returns parameters in this format: [<token> => <instance time> => Param[] ]
     * @return array
     */
    public function getParameters(): array
    {
        return $this->params;
    }

    /**
     * Returns user ids appropriate to set as reply-to
     * @return array
     */
    public function getReplyToIds(): array
    {
        $replyToIds = [];
        foreach ($this->instances as $instance) {
            if ($instance->getReplyToUserIds()) {
                $replyToIds = array_merge($replyToIds, $instance->getReplyToUserIds());
            }
        }
        $replyToIds = array_unique($replyToIds);
        if (count($replyToIds) > 0) {
            return $replyToIds;
        }
        // this ['user'] is set to effector user id in library/Schoolbox/Realtime/Schedule/Scheduler.php - handleResponse
        return array_filter(
            array_map(
                function (Instance $instance) {
                    return $instance->getContext()['user'] ?? null;
                },
                $this->instances
            )
        );
    }

    /**
     * Return hash with combination of base and params for more uniqueness
     * @return string
     */
    public function getBaseParamHash(): string
    {
        return $this->baseParamHash;
    }
}
