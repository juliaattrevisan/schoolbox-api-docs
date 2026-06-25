<?php

/**
 * IBIS register sessions SOAP envelope builder
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

use DOMDocument;
use DOMElement;

/**
 * Serialises register-sessions request data into the SOAP shape expected by
 * the IBIS candidate_registration.cfc endpoint.
 */
class RegisterSessionsEnvelopeBuilder
{
    private const string NS_SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const string NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    private const string NS_XSD = 'http://www.w3.org/2001/XMLSchema';
    private const string NS_API = 'http://api.research';
    private const string NS_SOAPENC = 'http://schemas.xmlsoap.org/soap/encoding/';

    private const array SESSION_FIELD_ORDER = [
        'IBCC_school_name',
        'candidate',
        'category',
        'exclude_stats',
        'main_school',
        'month',
        'english_proficiency',
        'social_security',
        'programme',
        'myp_2years',
        'cas_completed',
        'race',
        'school_year_grade',
        'socio_economic',
        'special_case',
        'sub_category',
        'year',
        'email',
    ];

    /**
     * Build the SOAP body containing one or more candidate session registrations.
     */
    public function build(RegisterSessionsRequest $request): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $envelope = $document->createElementNS(self::NS_SOAP, 'soapenv:Envelope');
        $envelope->setAttribute('xmlns:xsi', self::NS_XSI);
        $envelope->setAttribute('xmlns:xsd', self::NS_XSD);
        $envelope->setAttribute('xmlns:api', self::NS_API);
        $envelope->setAttribute('xmlns:soapenc', self::NS_SOAPENC);
        $document->appendChild($envelope);

        $envelope->appendChild($document->createElementNS(self::NS_SOAP, 'soapenv:Header'));
        $body = $document->createElementNS(self::NS_SOAP, 'soapenv:Body');
        $envelope->appendChild($body);

        $operation = $document->createElement('api:ws_register_sessions');
        $body->appendChild($operation);

        $this->appendTextElement($document, $operation, 'appID', $request->getAppId());
        $msgId = $this->appendTextElement($document, $operation, 'msgID', $request->getMsgId());
        $msgId->setAttribute('xsi:type', 'xsd:string');

        $sessions = $document->createElement('sessions');
        $operation->appendChild($sessions);

        foreach ($request->getSessions() as $sessionValues) {
            $session = $document->createElement('session');
            $sessions->appendChild($session);

            foreach (self::SESSION_FIELD_ORDER as $field) {
                if (!$this->hasNonEmptyValue($sessionValues, $field)) {
                    continue;
                }

                $this->appendTextElement($document, $session, $field, (string)$sessionValues[$field]);
            }

            $this->appendSubjects($document, $session, 'subjects', $sessionValues['subjects'] ?? []);
            if (!empty($sessionValues['extra_subjects']) && is_array($sessionValues['extra_subjects'])) {
                $this->appendSubjects($document, $session, 'extra_subjects', $sessionValues['extra_subjects']);
            }
        }

        return (string)$document->saveXML($document->documentElement);
    }

    /**
     * Append the subject list that IBIS expects beneath each session row.
     *
     * @param array<int, array<string, string>> $subjects
     * @throws \DOMException
     */
    private function appendSubjects(DOMDocument $document, DOMElement $session, string $name, array $subjects): void
    {
        $subjectsElement = $document->createElement($name);
        $session->appendChild($subjectsElement);

        foreach ($subjects as $subjectValues) {
            $subjectName = $subjectValues['subject_name'] ?? '';
            /* CAS is tracked in Schoolbox but is not submitted as an IBIS exam subject.
             */
            if (!$subjectName || 'Creativity, Activity, Service (CAS)' === $subjectName) {
                continue;
            }
            $subject = $document->createElement('subject_details');
            $subjectsElement->appendChild($subject);
            $this->appendTextElement($document, $subject, 'subject_name', (string)($subjectValues['subject_name'] ?? ''));
            $this->appendTextElement($document, $subject, 'subject_language', (string)($subjectValues['subject_language'] ?? ''));
            $this->appendTextElement($document, $subject, 'subject_level', (string)($subjectValues['subject_level'] ?? ''));
        }
    }

    /**
     * Check whether an optional IBIS field should be emitted into the SOAP XML.
     *
     * @param array<string, mixed> $values
     */
    private function hasNonEmptyValue(array $values, string $field): bool
    {
        return array_key_exists($field, $values) && $values[$field] !== null && $values[$field] !== '';
    }

    /**
     * Append an element and preserve empty strings as empty XML elements.
     */
    private function appendTextElement(
        DOMDocument $document,
        DOMElement $parent,
        string $name,
        string $value
    ): DOMElement {
        $element = $document->createElement($name);
        if ($value !== '') {
            $element->appendChild($document->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }
}
