<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Notification\Instance;

use Alaress\Async\Notification\Instance\Builder as InternalBuilder;
use Alaress\Async\Notification\Instance\BuilderInterface;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\User as UserModel;
use Schoolbox\SportsFixtures\Sport\Model as SportModel;

/**
 * Sports fixtures customized wrapper for the notification instance builder.
 *
 * @package Schoolbox\SportsFixtures\Notification\Instance
 */
class Builder implements BuilderInterface
{
    /**
     * Internal builder instance that this class wraps around.
     *
     * @var InternalBuilder
     */
    protected $internalBuilder;

    public function __construct(UserModel $effector, $validate = true)
    {
        $this->internalBuilder = new InternalBuilder($validate);
        // Set some defaults into the builder
        $this->internalBuilder
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size=square64")
            ->setSeverity(Logger::INFO);
    }

    /**
     * @param SportModel $sport
     * @return self
     */
    public function setSportParam(SportModel $sport)
    {
        $this->internalBuilder->setParam('sport', new Param($sport->getName(), $sport->getUrl()));
        return $this;
    }

    /** Pass-through to internal builder's setters */

    /**
     * @param string             $token
     * @param ParameterInterface $param
     * @return self
     */
    public function setParam($token, ParameterInterface $param)
    {
        $this->internalBuilder = $this->internalBuilder->setParam($token, $param);
        return $this;
    }

    /**
     * @param string $title
     * @return self
     */
    public function setTitle($title)
    {
        $this->internalBuilder = $this->internalBuilder->setTitle($title);
        return $this;
    }

    /**
     * @param string $icon
     * @return self
     */
    public function setIcon($icon)
    {
        $this->internalBuilder = $this->internalBuilder->setIcon($icon);
        return $this;
    }

    /**
     * @param int $timestamp
     * @return self
     */
    public function setTimestamp($timestamp)
    {
        $this->internalBuilder = $this->internalBuilder->setTimestamp($timestamp);
        return $this;
    }

    /**
     * @param int $severity
     * @return self
     */
    public function setSeverity($severity)
    {
        $this->internalBuilder = $this->internalBuilder->setSeverity($severity);
        return $this;
    }

    /**
     * @param string $namespace
     * @return self
     */
    public function setNamespace($namespace)
    {
        $this->internalBuilder = $this->internalBuilder->setNamespace($namespace);
        return $this;
    }

    /**
     * @param string $href
     * @return self
     */
    public function setHref($href)
    {
        $this->internalBuilder = $this->internalBuilder->setHref($href);
        return $this;
    }

    /** Interface methods */

    /**
     * @inheritdoc
     */
    public function setTopics($topics)
    {
        $this->internalBuilder = $this->internalBuilder->setTopics($topics);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function build()
    {
        return $this->internalBuilder->build();
    }
}
