<?php

/**
 * IBIS register sessions request
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

use InvalidArgumentException;

/**
 * Immutable register-sessions request data before it is converted to SOAP.
 *
 * The request validates candidate/session identity fields and the minimum
 * subject details IBIS needs before the envelope builder emits XML.
 */
class RegisterSessionsRequest
{
    private const int MSG_ID_LENGTH = 96;
    private const array REQUIRED_SESSION_FIELDS = [
        'candidate',
        'category',
        'main_school',
        'month',
        'programme',
        'year',
        'subjects',
    ];
    private const array REQUIRED_SUBJECT_FIELDS = [
        'subject_name',
        'subject_language',
    ];

    /**
     * @param array<int, array<string, mixed>> $sessions Session rows keyed by IBIS field name.
     */
    public function __construct(
        private string $appId,
        private string $msgId,
        private array $sessions
    ) {
        if ($this->appId === '') {
            throw new InvalidArgumentException('IBIS app ID is required.');
        }
        if (strlen($this->msgId) !== self::MSG_ID_LENGTH || !ctype_xdigit($this->msgId)) {
            throw new InvalidArgumentException(sprintf('IBIS msgID must be a %d-character hexadecimal string.', self::MSG_ID_LENGTH));
        }

        foreach ($this->sessions as $session) {
            foreach (self::REQUIRED_SESSION_FIELDS as $field) {
                if (!array_key_exists($field, $session) || $session[$field] === null || $session[$field] === '') {
                    throw new InvalidArgumentException(sprintf('IBIS session field "%s" is required.', $field));
                }
            }

            if (!is_array($session['subjects']) || $session['subjects'] === []) {
                throw new InvalidArgumentException('IBIS session field "subjects" is required.');
            }

            foreach ($session['subjects'] as $subject) {
                if (!is_array($subject)) {
                    throw new InvalidArgumentException('IBIS session subject must be an array.');
                }

                foreach (self::REQUIRED_SUBJECT_FIELDS as $field) {
                    if (!array_key_exists($field, $subject) || $subject[$field] === null || $subject[$field] === '') {
                        throw new InvalidArgumentException(sprintf('IBIS session subject field "%s" is required.', $field));
                    }
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
     * Return session rows ready to be serialised by the SOAP envelope builder.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSessions(): array
    {
        return $this->sessions;
    }
}
