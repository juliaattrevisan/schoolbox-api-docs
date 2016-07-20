<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Notification\Instance;
use Alaress\Async\Notification\Instance\Builder as InternalBuilder;
use Alaress\Async\Notification\Instance\BuilderInterface;

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

    /**
     * Constructor
     *
     * @param bool $validate
     */
    public function __construct($validate = true)
    {
        $this->internalBuilder = new Builder($validate);
    }

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
