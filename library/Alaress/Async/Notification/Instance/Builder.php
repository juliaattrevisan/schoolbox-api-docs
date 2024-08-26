<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Instance;

use Alaress\Async\Notification\Cloud\TassOrbit\Service;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Alaress\Html\ToText as Html2Text;
use InvalidArgumentException;
use OutOfBoundsException;

/**
 * Builds instances of notifications. Factory pattern.
 *
 * Class Builder
 *
 * @package Alaress\Async\Notification
 */
class Builder implements BuilderInterface
{
    /** @var bool */
    protected $validate;

    /** @var array */
    protected $context;

    /** @var array */
    protected $topics;

    /** @var int */
    protected $severity;

    /** @var ParameterInterface[] */
    protected $params;

    /** @var string */
    protected $title;

    /** @var array */
    protected $meta;

    /** @var string */
    protected $icon;

    /** @var string */
    protected $href;

    /** @var int */
    protected $time;

    /** @var string */
    protected $namespace;

    /** @var int */
    protected $folderId;

    /**
     * Basic construction
     *
     * @param bool $validate optionally build without validation (debug only), if validation fails exception thrown
     */
    public function __construct($validate = true)
    {
        $this->validate = $validate;
        $this->clear();
    }


    /**
     * Sets up all vars
     * @return self
     */
    public function clear()
    {
        $this->context   = array();
        $this->topics    = array();
        $this->params    = array();
        $this->meta      = array();
        // Twig template requires body
        $this->setBody('');

        $this->namespace = '';
        $this->title     = '';
        $this->icon      = '';
        $this->href      = '';

        $this->severity  = 0;
        $this->time      = 0;
        $this->folderId  = 0;

        return $this;
    }


    /**
     * @param string $namespace
     * @return self
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }


    /**
     * @param string $href
     * @return self
     */
    public function setHref($href)
    {
        $this->href = $href;
        return $this;
    }

    /**
     * Set body, converting to plaintext as you go (we can't support markup in the body)
     *
     * @param string $body
     * @return self
     */
    public function setBody($body)
    {
        // totally strip links
        $plain = new Html2Text($body, ['do_links' => 'none']);
        return $this->putMeta('body', $plain->getText());
    }


    /**
     * @param string $icon
     * @return self
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;
        return $this;
    }


    /**
     * @param int $time
     * @return self
     */
    public function setTimestamp($time)
    {
        $this->time = $time;
        return $this;
    }


    /**
     * @param string $title
     * @return self
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @param string $type
     * @param mixed $ids
     * @return self
     */
    public function addFilter($type, $ids)
    {
        $this->context[$type] = $ids;
        return $this;
    }

    /**
     * @param array $filter
     * @return self
     */
    public function setFilter($filter)
    {
        $this->context = array_merge($this->context, $filter);
        return $this;
    }


    /**
     * @param string $byline
     * @return self
     */
    public function setByline($byline)
    {
        return $this->putMeta('byline', $byline);
    }


    /**
     * @param string $name
     * @param string $value
     * @return self
     */
    public function putMeta($name, $value)
    {
        $this->meta[$name] = $value;
        return $this;
    }


    /**
     * @param array $meta
     * @return self
     */
    public function setMeta($meta)
    {
        $this->meta = $meta;
        return $this;
    }


    /**
     * @param string $token
     * @param ParameterInterface $param
     * @return self
     */
    public function setParam($token, ParameterInterface $param)
    {
        $this->params[$token] = $param;
        return $this;
    }

    /**
     * The opposite of setParam()
     *
     * @param string $token
     *
     * @return self
     */
    public function unsetParam($token)
    {
        unset($this->params[$token]);
        return $this;
    }

    /**
     * @param ParameterInterface[] $params
     * @return self
     */
    public function setParams($params)
    {
        $this->params = array();
        foreach ($params as $t => $p) {
            $this->setParam($t, $p);
        }
        return $this;
    }


    /**
     * @param int $severity
     * @return self
     */
    public function setSeverity($severity)
    {
        $this->severity = $severity;
        return $this;
    }


    /**
     * @param string $topic
     * @return self
     */
    public function addTopic($topic)
    {
        $this->topics[] = $topic;
        return $this;
    }


    /**
     * @param array $topics
     * @return self
     */
    public function setTopics($topics)
    {
        $this->topics = (array)$topics;
        return $this;
    }

    /**
     * This allows the message to bypass the filter that prevents messages being delivered to the user themselves.
     * This should be used only when a message is being delivered some time after the action occurred.  Like reminders.
     *
     * Set to TRUE if you wish to deliver a message to the effector.
     *
     * @param bool $allowSelfSend
     * @return self
     */
    public function setAllowSelfSend($allowSelfSend)
    {
        $this->context['allowSelfSend'] = $allowSelfSend;
        return $this;
    }

    /**
     * Set to TRUE if you wish to retract existing related messages first and then send this message.
     * @param bool $retractFirst
     * @return Builder
     */
    public function setRetractFirst(bool $retractFirst): self
    {
        $this->meta['retractFirst'] = $retractFirst;
        return $this;
    }

    /**
     * Set user id, when need to explicitly set reply-to to other than effector
     */
    public function setReplyToUserIds(array $replyToUserIds): self
    {
        $this->meta['replyToUserIds'] = $replyToUserIds;
        return $this;
    }

    /**
     * Explicitly specify the folder id which this message involves
     * @param int $folderId
     * @return Builder
     */
    public function setFolderId(int $folderId): self
    {
        $this->folderId = $folderId;
        return $this;
    }

    /**
     * @param string|null $studentExternalId
     * @return Builder
     */
    public function setStudentExternalIdMeta(?string $studentExternalId): self
    {
        $this->meta[Service::PARENT_TASS_ORBIT_STUDENT_EXTERNAL_ID] = $studentExternalId;
        return $this;
    }

    /**
     * Builds an instance from the construction given
     *
     * @throws OutOfBoundsException     If severity is not set
     * @throws InvalidArgumentException If a required property was not set
     * @return Instance
     */
    public function build()
    {
        // check things are right

        if ($this->validate) {
            // mandatory strings
            $check = array('title', 'icon', 'namespace', 'href');

            foreach ($check as $prop) {
                if (!(trim($this->$prop ?? ''))) {
                    throw new InvalidArgumentException(ucfirst($prop) . " has not been set");
                }
            }

            if ($this->severity == 0) {
                throw new OutOfBoundsException("Severity has not been set");
            }

            if (!count($this->topics)) {
                throw new InvalidArgumentException("Topic has not been set");
            }

            // if no time was specified, set the time to now
            if (!$this->time) {
                $this->time = time();
            }
        }

        return new Instance(
            // mandatory args
            $this->title,
            $this->namespace,
            $this->icon,
            $this->href,
            $this->params,
            $this->severity,
            $this->topics,
            $this->folderId,
            // optional args
            $this->time,
            $this->meta,
            $this->context,
            $this->validate
        );
    }
}
