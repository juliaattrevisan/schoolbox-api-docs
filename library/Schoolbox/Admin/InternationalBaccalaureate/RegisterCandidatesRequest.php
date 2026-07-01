<?php

/**
 * IBIS register candidates request
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

use InvalidArgumentException;

/**
 * Immutable register-candidates request data before it is converted to SOAP.
 *
 * The constructor validates only the fields that IBIS requires for every
 * candidate. Optional fields are allowed through for the envelope builder to
 * serialise as empty SOAP elements.
 */
class RegisterCandidatesRequest
{
    private const int MSG_ID_LENGTH = 96;
    private const array REQUIRED_CANDIDATE_FIELDS = [
        'date_of_birth',
        'firstname',
        'gender',
        'language1',
        'lastname',
        'nationality1',
        'reference_code',
        'email',
    ];

    /**
     * @param array<int, array<string, string|null>> $candidates Candidate rows keyed by IBIS field name.
     */
    public function __construct(
        private string $appId,
        private string $msgId,
        private string $schoolCode,
        private array $candidates
    ) {
        if ($this->appId === '') {
            throw new InvalidArgumentException('IBIS app ID is required.');
        }
        if (strlen($this->msgId) !== self::MSG_ID_LENGTH || !ctype_xdigit($this->msgId)) {
            throw new InvalidArgumentException(sprintf('IBIS msgID must be a %d-character hexadecimal string.', self::MSG_ID_LENGTH));
        }
        if ($this->schoolCode === '') {
            throw new InvalidArgumentException('IBIS school code is required.');
        }

        foreach ($this->candidates as $candidate) {
            foreach (self::REQUIRED_CANDIDATE_FIELDS as $field) {
                if (!array_key_exists($field, $candidate) || $candidate[$field] === null || $candidate[$field] === '') {
                    throw new InvalidArgumentException(sprintf('IBIS candidate field "%s" is required.', $field));
                }
            }
        }
    }

    /**
     * Return the IBIS application identifier configured for the school.
     */
    public function getAppId(): string
    {
        return $this->appId;
    }

    /**
     * Return the generated IBIS msgID used to authenticate this request.
     */
    public function getMsgId(): string
    {
        return $this->msgId;
    }

    /**
     * Return the IB school code to include on each candidate payload.
     */
    public function getSchoolCode(): string
    {
        return $this->schoolCode;
    }

    /**
     * Return candidate rows ready to be serialised by the SOAP envelope builder.
     *
     * @return array<int, array<string, string|null>>
     */
    public function getCandidates(): array
    {
        return $this->candidates;
    }
}
