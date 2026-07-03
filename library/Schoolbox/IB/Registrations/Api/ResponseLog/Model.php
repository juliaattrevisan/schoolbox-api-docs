<?php

/**
 * IB registration API response log model
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Api\ResponseLog;

use Schoolbox\IB\Registrations\Sessions\Model as RegistrationSessionModel;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Schoolbox\Mapper\Traits\Model as ModelTraits;

class Model
{
    use ModelTraits\Id;
    use ModelTraits\CreatedAtDate;

    public const string REQUEST_TYPE_REGISTER_CANDIDATES = 'register_candidates';
    public const string REQUEST_TYPE_REGISTER_SESSIONS = 'register_sessions';
    public const string STATUS_SUCCESS = 'success';
    public const string STATUS_ERROR = 'error';
    public const string SOURCE_IBIS_API = 'ibis_api';
    public const string SOURCE_LOCAL_VALIDATION = 'local_validation';

    private ?RegistrationSessionModel $registrationSession = null;
    private ?RegistrationSessionStudentModel $registrationSessionStudent = null;
    private ?string $msgId = null;
    private string $requestType = self::REQUEST_TYPE_REGISTER_CANDIDATES;
    private string $status = self::STATUS_ERROR;
    private string $source = self::SOURCE_IBIS_API;
    private ?string $message = null;

    public function getRegistrationSession(): ?RegistrationSessionModel
    {
        return $this->registrationSession;
    }

    public function setRegistrationSession(?RegistrationSessionModel $registrationSession): self
    {
        $this->registrationSession = $registrationSession;
        return $this;
    }

    public function getRegistrationSessionStudent(): ?RegistrationSessionStudentModel
    {
        return $this->registrationSessionStudent;
    }

    public function setRegistrationSessionStudent(?RegistrationSessionStudentModel $registrationSessionStudent): self
    {
        $this->registrationSessionStudent = $registrationSessionStudent;
        return $this;
    }

    public function getMsgId(): ?string
    {
        return $this->msgId;
    }

    public function setMsgId(?string $msgId): self
    {
        $this->msgId = $msgId;
        return $this;
    }

    public function getRequestType(): string
    {
        return $this->requestType;
    }

    public function setRequestType(string $requestType): self
    {
        $this->requestType = $requestType;
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

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }
}
