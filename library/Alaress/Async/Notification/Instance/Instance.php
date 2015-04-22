<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Notification\Instance;


use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Html2Text\Html2Text;
use InvalidArgumentException;
use JsonSerializable;
use OutOfBoundsException;
use RuntimeException;
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
     * @param string               $text
     * @param string               $namespace
     * @param string               $icon
     * @param string               $href
     * @param ParameterInterface[] $params
     * @param int                  $severity
     * @param array                $topics
     * @param int                  $time
     * @param array                $meta
     * @param array                $context
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
        $time = null,
        $meta = null,
        $context = null
    ) {

        if (preg_match_all('`:([A-Za-z_][A-Za-z0-9_]*)`', $text, $matches)) {
            $this->tokens = $matches[1];

        } else {
            $this->tokens = array();
        }

        // cast params to array to prevent errors
        if (!is_array($params)) {
            $params = array($params);
        }

        // test template vs tokens
        if (!$this->arraysIdentical(array_keys($params), $this->tokens)) {
            $a = json_encode($this->tokens);
            $b = json_encode(array_keys($params));
            throw new OutOfBoundsException("Parameters supplied must exactly match the :tokens in the message string: expected $a, got $b");
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

        $this->params  = (array)$params;
        $this->topics  = (array)$topics;
        $this->context = (array)$context ?: array();

        $this->meta = array();

        // cap the amount of data that can be stored in meta
        $meta = (array)$meta ?: array();

        foreach ($meta as $key => $value) {
            // check if it's out of bounds
            if (strlen($value) > self::MAX_FIELD_SIZE) {
                // convert to plaintext first as a precaution
                $plain = new Html2Text($value);
                $value = TextFormatter::cutSentence($plain->get_text(), self::MAX_FIELD_SIZE);
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
     * @throws InvalidArgumentException
     * @return self
     */
    public static function jsonUnserialize($json)
    {
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
        $severity      = isset($array['level']) ? $array['level'] : 0;

        // cast param array to objects
        $params = array();
        if (is_array($array['title']['params'])) {
            foreach ($array['title']['params'] as $token => $param) {
                $params[$token] = new Param($param['text'], $param['href'], $param['mode']);
            }
        }

        // these vars are lost on (and not relevant to) reconstitution
        $topics  = array();
        $context = array();

        return new self($messageString, $namespace, $icon, $href, $params, $severity, $topics, $time, $meta, $context);
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
        return array(
            'title'     => array(
                'text'   => $this->messageString,
                'params' => $this->params
            ),
            'namespace' => $this->namespace,
            'time'      => $this->time,
            'icon'      => $this->icon,
            'href'      => $this->href,
            'meta'      => $this->meta,
            'level'     => $this->severity,
        );
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
        return sha1(json_encode(array(
            'text' => $this->messageString,
            'href' => $this->href
        )));
    }


    /**
     * Return a SHA1 representation of the params of this instance
     *
     * @return string
     */
    public function getParamHash()
    {
        $list = array();
        foreach ($this->params as $p) {
            $list[] = $p->jsonSerialize();
        }
        foreach ($this->meta as $m) {
            $list[] = $m;
        }
        return sha1(json_encode($list));
    }

}
