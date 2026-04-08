<?php

/**
 * SOCS import log entry model.
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Log;

use Alaress_Date_Format;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

/**
 * Represents a log entry for a SOCS importer run.
 */
class Model
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;

    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_ERROR = 'error';

    private string $runId;

    private string $script;

    private string $status;

    private ?string $errorMessage = null;

    private ?Alaress_Date_Format $startAt = null;

    private ?Alaress_Date_Format $finishAt = null;

    public function isComplete(): bool
    {
        return $this->getStatus() === self::STATUS_COMPLETE;
    }

    public function isRunning(): bool
    {
        return $this->getStatus() === self::STATUS_RUNNING;
    }

    public function isError(): bool
    {
        return $this->getStatus() === self::STATUS_ERROR;
    }

    public function getScript(): string
    {
        return $this->script;
    }

    public function setScript(string $script): self
    {
        $this->script = $script;
        return $this;
    }

    public function getStartAt(): ?Alaress_Date_Format
    {
        return $this->startAt;
    }

    public function setStartAt(Alaress_Date_Format $startAt): self
    {
        $this->startAt = $startAt;
        return $this;
    }

    public function getFinishAt(): ?Alaress_Date_Format
    {
        return $this->finishAt;
    }

    public function setFinishAt(?Alaress_Date_Format $finishAt): self
    {
        $this->finishAt = $finishAt;
        return $this;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function setRunId(string $runId): self
    {
        $this->runId = $runId;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }
}
