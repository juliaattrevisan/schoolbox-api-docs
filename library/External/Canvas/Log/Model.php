<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace External\Canvas\Log;

use Alaress_Date_Format;
use External\Canvas\Importer\Cli\AnnouncementsTool;
use External\Canvas\Sync\CliImporter;
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
     * A list of known scripts that can occur for canvas
     *
     */
    public const KNOWN_SCRIPTS = [
        AnnouncementsTool::CANVAS_ANNOUNCEMENTS_SCRIPT_NAME,
        CliImporter::CANVAS_IMPORTER_DOWNLOAD_SCRIPT_NAME,
        CliImporter::CANVAS_IMPORTER_SYNC_SCRIPT_NAME
    ];


    /**
     * These consts explicitly indicate the status of an import
     * without having to infer meaning from a null finish_at
     */
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_ERROR = 'error';


    /**
     * @var string
     */
    private string $runId;


    /**
     * @var string
     */
    private string $script;


    /**
     * @var string
     */
    private string $status;


    /**
     * @var ?string
     */
    private ?string $errorMessage = null;


    /**
     * Script started running at this time
     *
     * @var Alaress_Date_Format|null
     */
    private ?Alaress_Date_Format $startAt = null;


    /**
     * Script ended (maybe!) at this time
     *
     * This remaining null will indicate that canvas never finished
     * and somehow died during attempt
     *
     * @var Alaress_Date_Format|null
     */
    private ?Alaress_Date_Format $finishAt = null;


    /**
     * Decide if this import completed successfully
     *
     * @return bool
     */
    public function isComplete(): bool
    {
        return $this->getStatus() === self::STATUS_COMPLETE;
    }


    /**
     * Decide if this import is still running
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->getStatus() === self::STATUS_RUNNING;
    }


    /**
     * Decide if this import is irrevocably dead
     *
     * @return bool
     */
    public function isError(): bool
    {
        return $this->getStatus() === self::STATUS_ERROR;
    }


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
     * @return Alaress_Date_Format|null
     */
    public function getStartAt(): ?Alaress_Date_Format
    {
        return $this->startAt;
    }


    /**
     * @param Alaress_Date_Format $startAt
     * @return Model
     */
    public function setStartAt(Alaress_Date_Format $startAt): self
    {
        $this->startAt = $startAt;
        return $this;
    }


    /**
     * @return Alaress_Date_Format|null
     */
    public function getFinishAt(): ?Alaress_Date_Format
    {
        return $this->finishAt;
    }


    /**
     * @param Alaress_Date_Format|null $finishAt
     * @return Model
     */
    public function setFinishAt(?Alaress_Date_Format $finishAt): self
    {
        $this->finishAt = $finishAt;
        return $this;
    }


    /**
     * @return string
     */
    public function getRunId(): string
    {
        return $this->runId;
    }


    /**
     * @param string $runId
     * @return Model
     */
    public function setRunId(string $runId): self
    {
        $this->runId = $runId;
        return $this;
    }


    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }


    /**
     * @param string $status
     * @return Model
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }


    /**
     * @param null|string $errorMessage
     * @return Model
     */
    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }
}
