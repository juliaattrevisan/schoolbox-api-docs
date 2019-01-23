<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\Context;

use Schoolbox\Discussion\Thread;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Model for discussion thread context.
 */
class Model
{
    public const CONTEXT_COMMS = 'comms';


    use Id;


    /**
     * @var Thread\Model
     */
    private $thread;


    /**
     * @var string
     */
    private $contextType;


    /**
     * @var int
     */
    private $contextId;


    /**
     * @return Thread\Model
     */
    public function getThread(): ?Thread\Model
    {
        return $this->thread;
    }


    /**
     * @param Thread\Model $thread
     * @return static
     */
    public function setThread(Thread\Model $thread): self
    {
        $this->thread = $thread;
        return $this;
    }


    /**
     * @return string
     */
    public function getContextType(): ?string
    {
        return $this->contextType;
    }


    /**
     * @param string $contextType
     * @return static
     */
    public function setContextType(string $contextType): self
    {
        $this->contextType = $contextType;
        return $this;
    }


    /**
     * @return int
     */
    public function getContextId(): ?int
    {
        return $this->contextId;
    }


    /**
     * @param int $contextId
     * @return static
     */
    public function setContextId(int $contextId): self
    {
        $this->contextId = $contextId;
        return $this;
    }
}
