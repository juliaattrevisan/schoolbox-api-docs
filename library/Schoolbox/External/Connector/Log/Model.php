<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Log;

use Schoolbox\Mapper\Traits\Model as ModelTrait;

/**
 * Represents a log entry for the start of a suds run
 */
class Model
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;


    /**
     * A list of known scripts that can occur for suds
     *
     * TODO This probably should be an enum, but I'll do that when
     *  people have had a chance to rename my suggestions
     */
    public const KNOWN_SCRIPTS = [
        \Schoolbox\External\Importer\Endpoint\Scheduler::SCHEDULER_SIS_SCRIPT_NAME,
        \Schoolbox\External\Importer\Endpoint\Scheduler::SCHEDULER_CALENDAR_SCRIPT_NAME,
    ];


    /**
     * @var string
     */
    private string $script;


    /**
     * Script started running at this time
     *
     * @var \Alaress_Date_Format
     */
    private \Alaress_Date_Format $startAt;


    /**
     * Script ended (maybe!) at this time
     *
     * This remaining null will indicate that suds never finished
     * and somehow died during attempt
     *
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $finishAt = null;


    /* Getters/Setters
     */


    /**
     * @return string
     */
    public function getScript(): string
    {
        return $this->script;
    }


    /**
     * @param string $script
     * @return Model
     */
    public function setScript(string $script): self
    {
        $this->script = $script;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format
     */
    public function getStartAt(): \Alaress_Date_Format
    {
        return $this->startAt;
    }


    /**
     * @param \Alaress_Date_Format $startAt
     * @return Model
     */
    public function setStartAt(\Alaress_Date_Format $startAt): self
    {
        $this->startAt = $startAt;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getFinishAt(): ?\Alaress_Date_Format
    {
        return $this->finishAt;
    }


    /**
     * @param \Alaress_Date_Format|null $finishAt
     * @return Model
     */
    public function setFinishAt(?\Alaress_Date_Format $finishAt): self
    {
        $this->finishAt = $finishAt;
        return $this;
    }
}
