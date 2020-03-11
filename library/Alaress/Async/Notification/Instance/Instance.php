<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Instance;


use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Alaress\Html\ToText as Html2Text;
use InvalidArgumentException;
use JsonSerializable;
use OutOfBoundsException;
use RuntimeException;
use Schoolbox\Realtime\Notification\Parameter\SimpleParam;
use TextFormatter;

/**
 * Basically a really big container of specific things that get put together in a special way
 * to form a notification.
 *
 * Class Instance
 *
 * @package Alaress\Async\Notification
 */
class Instance implements JsonSerializable
{

    const MAX_FIELD_SIZE = 512;

    /**
     * Default life time of this instance in seconds.
     */
    const DEFAULT_TTL = 1209600; // 2 weeks

    /**
     * A type of action for this instance, as used by `$this->get/setAction`.
     *
     * This instance's notification is sent.
     */
    public const ACTION_SEND = 'send';

    /**
     * A type of action for this instance, as used by `$this->get/setAction`.
     *
     * This instance's notification is retracted.
     */
    public const ACTION_RETRACT_SINGLE = 'retract-single';

    /**
     * A type of action for this instance, as used by `$this->get/setAction`.
     *
     * This instance's notification is retracted, as are all with the same base
     * hash (`$this->getBaseHash`).
     */
    public const ACTION_RETRACT_ALL = 'retract-all';

    /**
     * Delivery: alert
     *
     * Set as part of this instance's topic: if it's there, the message will
     * bypass routing, and be upgraded to instant+email+app.
     */
    public const DELIVERY_ALERT = 'alert';

    /**
     * Delivery: instant
     *
     * Set as part of this instance's topic: if it's there, the message will
     * bypass routing, and be upgraded to instant delivery.
     */
    public const DELIVERY_INSTANT = 'instant';

    /**
     * Delivery: notify
     *
     * Set as part of this instance's topic: if it's there, the message will be
     * routed through the standard channels.
     */
    public const DELIVERY_NOTIFY = 'notify';

    /**
     * Array key to find event object identifier.
     */
    public const EVENT_OBJECT_ID = 'eventObjectId';

    /**
     * The :tokenised string that forms the base template of the message
     *
     * @var string
     */
    protected $messageString;

    /**
     * The namespace to which this message belongs; could be 'assessment', 'news', 'system', or anything
     *
     * @var string
     */
    protected $namespace;

    /**
     * The image that will be displayed alongside the text of this notification
     * Most frequently the avatar of the person who initiated the notification's creation, but can also be
     * anything else from anywhere.
     *
     * @var string
     */
    protected $icon;

    /**
     * The link that this message, as a whole, points to. Tokens can have separate hrefs, but this is the
     * call-to-action link.
     *
     * @var string
     */
    protected $href;

    /**
     * The :tokens extracted from the text given in construction
     *
     * @var array
     */
    protected $tokens;

    /**
     * The parameters to be bound to the tokens of this instance
     *
     * @var ParameterInterface[]
     */
    protected $params;

    /**
     * Additional data to be transmitted
     *
     * @var array
     */
    protected $meta;

    /**
     * The time at which this message was generated. Uses server time by default
     *
     * @var int
     */
    protected $time;

    /**
     * The default severity at which this should be sent
     *
     * @var int
     */
    protected $severity;

    /**
     * The list of topics on which it should be sent. Only one for 90% of use cases.
     *
     * @var array
     */
    protected $topics;

    /**
     * We use the AMQP context as a filtering mechanism
     *
     * @var array
     */
    protected $context;

    /**
     * What kind of notification is this?
     *
     * * self::ACTION_SEND:           Send this message.
     * * self::ACTION_RETRACT_SINGLE: Retract this message.
     *                                Base hash, params, context and meta must
     *                                match sent message.
     * * self::ACTION_RETRACT_ALL:    Retract this message, and any like it,
     *                                from everyone.
     *                                Base hash (message and href) must match
     *                                sent message.
     *
     * @var string
     */
    protected $action = self::ACTION_SEND;

    /**
     * Folder id of this message, if this needs to be muted by the homepage based message settings
     */
    protected $folderId;

    /**
     * TTL of this message.
     */
    protected $ttl = self::DEFAULT_TTL;

    /**
     * The identifier of event object.
     */
    protected $eventObjectId = '';

    /**
     * @param string               $text
     * @param string               $namespace
     * @param string               $icon
     * @param string               $href
     * @param ParameterInterface[] $params
     * @param int                  $severity
     * @param array                $topics
     * @param int                  $folderId
     * @param int                  $time
     * @param array                $meta
     * @param array                $context
     * @param bool                 $validate
     * @throws RuntimeException
     * @throws OutOfBoundsException
     */
    public function __construct(
        $text,
        $namespace,
        $icon,
        $href,
        $params,
        $severity,
        $topics,
        $folderId,
        $time = null,
        $meta = null,
        $context = null,
        $validate = true
    ) {

        if (preg_match_all('`:([A-Za-z_]\w*)`', $text, $matches)) {
            $this->tokens = $matches[1];

        } else {
            $this->tokens = [];
        }

        // cast params to array to prevent errors
        if (!is_array($params)) {
            $params = [$params];
        }

        // test template vs tokens
        if (!$this->arraysIdentical(array_keys($params), $this->tokens)) {
            if ($validate) {
                $a = json_encode($this->tokens);
                $b = json_encode(array_keys($params));
                throw new OutOfBoundsException("Parameters supplied must exactly match the :tokens in the message string: expected $a, got $b");
            } else {
                // filling in missing parameters with empty string.
                foreach (\array_diff($this->tokens, \array_keys($params)) as $missing) {
                    $params[$missing] = new SimpleParam('');
                }
            }
        }

        foreach ($params as $p) {
            if (!$p instanceof ParameterInterface) {
                throw new RuntimeException("Parameter must implement ParameterInterface");
            }
        }

        $this->messageString = (string)$text;
        $this->namespace     = (string)$namespace;
        $this->icon          = (string)$icon;
        $this->href          = (string)$href;

        $this->time     = (int)$time ?: time();
        $this->severity = (int)$severity;
        $this->folderId = (int)$folderId;

        $this->params  = (array)$params;
        $this->topics  = (array)$topics;
        $this->context = (array)$context ?: [];

        $this->meta = [];

        // cap the amount of data that can be stored in meta
        $meta = (array)$meta ?: [];

        foreach ($meta as $key => $value) {
            // check if it's out of bounds
            if (is_string($value) && ($key !== 'blurb') && strlen($value) > self::MAX_FIELD_SIZE) {
                // convert to plaintext first as a precaution
                $plain = new Html2Text($value);
                $value = TextFormatter::cutSentence($plain->getText(), self::MAX_FIELD_SIZE);
            }

            $this->meta[$key] = $value;
        }

    }


    /**
     * @param array $a
     * @param array $b
     * @return bool
     */
    protected function arraysIdentical($a, $b)
    {
        sort($a);
        sort($b);
        return $a == $b;
    }


    /**
     * @param string $json
     * @param string $topic
     * @return self
     * @throws InvalidArgumentException
     */
    public static function jsonUnserialize($json, $topic = '')
    {
        if (!is_string($json)) {
            throw new InvalidArgumentException("Could not JSON decode given " . gettype($json));
        }

        if (!($array = json_decode($json, true))) {
            throw new InvalidArgumentException("Could not JSON decode given " . gettype($json));
        }

        if (!is_array($array)) {
            throw new InvalidArgumentException("Decoded JSON not an array, is of type " . gettype($array));
        }

        // basic vars
        $messageString = $array['title']['text'];
        $namespace     = $array['namespace'];
        $icon          = $array['icon'];
        $href          = $array['href'];
        $time          = $array['time'];
        $meta          = $array['meta'];
        $action        = $array['action'] ?? self::ACTION_SEND;
        $severity      = isset($array['level']) ? $array['level'] : 0;
        $context       = isset($array['context']) ? $array['context'] : [];
        $folderId      = $array['folderId'];

        // override topic if relevant to do so (happens in QueueSorter::process())
        if ($topic) {
            $topics = [$topic];
        } else {
            $topics = isset($array['topics']) ? $array['topics'] : [];
        }

        // cast param array to objects
        $params = [];
        if (is_array($array['title']['params'])) {
            foreach ($array['title']['params'] as $token => $param) {
                $params[$token] = new Param($param['text'], $param['href'], $param['mode']);
            }
        }

        $instance = new self(
            $messageString,
            $namespace,
            $icon,
            $href,
            $params,
            $severity,
            $topics,
            $folderId,
            $time,
            $meta,
            $context
        );
        $instance->setAction($action);

        if (\array_key_exists(self::EVENT_OBJECT_ID, $array)) {
            $instance->setEventObjectId($array[self::EVENT_OBJECT_ID]);
        }

        return $instance;
    }


    /**
     * Flatten this notification instance
     *
     * @return string
     */
    public function __toString()
    {
        try {
            $json = json_encode($this);
            if (is_string($json)) {
                return $json;
            }

        } catch (\Exception $e) {
            // noop
        }
        return "";
    }


    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *       which is a value of any type other than a resource.
     */
    public function jsonSerialize()
    {
        return [
            'title'               => [
                'text'   => $this->messageString,
                'params' => $this->params,
            ],
            'namespace'           => $this->namespace,
            'time'                => $this->time,
            'icon'                => $this->icon,
            'href'                => $this->href,
            'meta'                => $this->meta,
            'level'               => $this->severity,
            'topics'              => $this->topics,
            'context'             => $this->context,
            'action'              => $this->action,
            'folderId'            => $this->folderId,
            self::EVENT_OBJECT_ID => $this->eventObjectId,
        ];
    }


    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }


    /**
     * @return array
     */
    public function getTopics()
    {
        return $this->topics;
    }


    /**
     * @return int
     */
    public function getSeverity()
    {
        return $this->severity;
    }


    /**
     * @return array
     */
    public function getContext()
    {
        return $this->context;
    }


    /**
     * @return int
     */
    public function getTime()
    {
        return $this->time;
    }


    /**
     * @param int $time
     * @return self
     */
    public function setTime($time)
    {
        $this->time = (int)$time;
        return $this;
    }


    /**
     * @return ParameterInterface[]
     */
    public function getParameters()
    {
        return $this->params;
    }


    /**
     * Return a SHA1 representation of the base of this instance
     *
     * @return string
     */
    public function getBaseHash()
    {
        // encoding is effectively a salt
        return sha1(json_encode([
            'text' => $this->messageString,
            'href' => $this->href,
        ]));
    }


    /**
     * Return a SHA1 representation of the params of this instance
     *
     * @return string
     */
    public function getParamHash()
    {
        $list = [];
        foreach ($this->params as $p) {
            $list[] = $p->jsonSerialize();
        }
        foreach ($this->meta as $m) {
            $list[] = $m;
        }
        foreach ($this->context as $c) {
            $list[] = $c;
        }
        return sha1(json_encode($list));
    }


    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }


    /**
     * @param string $action
     * @return static
     * @throws InvalidArgumentException if `$action` isn't one of the
     *                                  `self::ACTION_*` constants
     */
    public function setAction(string $action): self
    {
        if (!\in_array($action, [self::ACTION_SEND, self::ACTION_RETRACT_SINGLE, self::ACTION_RETRACT_ALL], true)) {
            throw new InvalidArgumentException('$action must be a ACTION_* constant of this class');
        }
        $this->action = $action;
        return $this;
    }


    /**
     * Is this a send notification?
     *
     * @return bool
     */
    public function isActionSend(): bool
    {
        return $this->action === self::ACTION_SEND;
    }


    /**
     * Is this a retract message (either single or all) notification?
     *
     * @return bool
     */
    public function isActionRetract(): bool
    {
        return $this->isActionRetractSingle() || $this->isActionRetractAll();
    }


    /**
     * Is this a retract single message notification?
     *
     * @return bool
     */
    public function isActionRetractSingle(): bool
    {
        return $this->action === self::ACTION_RETRACT_SINGLE;
    }


    /**
     * Is this a retract all instances of message notification?
     *
     * @return bool
     */
    public function isActionRetractAll(): bool
    {
        return $this->action === self::ACTION_RETRACT_ALL;
    }


    /**
     * Retrieve the delivery method for this message.
     *
     * Should be one of the `self::DELIVERY_*` constants, and will fall back to
     * `self::DELIVERY_NOTIFY` if not defined at all.
     *
     * @return string
     */
    public function retrieveDeliveryMethod(): string
    {
        // TODO consider inspecting all topics, not just the first
        // But what if the message is delivered to multiple users differently?
        $topic = $this->topics[0];
        [, $delivery] = explode('.', $topic);
        return $delivery ?? self::DELIVERY_NOTIFY;
    }

    /**
     * Gets folder id
     *
     * @return int
     */
    public function getFolderId(): int
    {
        return $this->folderId;
    }

    /**
     * Sets ttl
     *
     * @param int $ttl
     */
    public function setTTL(int $ttl): void
    {
        $this->ttl = $ttl;
    }

    /**
     * Gets ttl
     *
     * @return int ttl
     */
    public function getTTL(): int
    {
        return $this->ttl;
    }


    /**
     * Sets event object identifier
     *
     * @param string $eventObjectId
     */
    public function setEventObjectId(string $eventObjectId): void
    {
        $this->eventObjectId = $eventObjectId;
    }


    /**
     * Gets event object identifier
     *
     * @return string
     */
    public function getEventObjectId(): string
    {
        return $this->eventObjectId;
    }


    /**
     * Checks whether to retract related object before sending out the message
     * CAUTION: This works only for a message to a single user.
     *
     * @return bool
     */
    public function retractFirst(): bool
    {
        return isset($this->meta['retractFirst']) && $this->meta['retractFirst'];
    }
}
