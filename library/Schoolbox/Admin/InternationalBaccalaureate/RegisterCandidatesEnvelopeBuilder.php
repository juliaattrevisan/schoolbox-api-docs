<?php

/**
 * IBIS register candidates SOAP envelope builder
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

use DOMDocument;
use DOMElement;

/**
 * Serialises register-candidates request data into the SOAP shape expected by
 * the IBIS candidate_registration.cfc endpoint.
 */
class RegisterCandidatesEnvelopeBuilder
{
    private const string NS_SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const string NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    private const string NS_XSD = 'http://www.w3.org/2001/XMLSchema';
    private const string NS_API = 'http://api.research';
    private const string NS_SOAPENC = 'http://schemas.xmlsoap.org/soap/encoding/';

    private const array CANDIDATE_FIELDS = [
        'date_of_birth',
        'firstname',
        'gender',
        'language1',
        'language2',
        'lastname',
        'nationality1',
        'nationality2',
        'reference_code',
        'school',
        'email',
    ];

    /**
     * Build the SOAP body containing one or more candidate profile rows.
     */
    public function build(RegisterCandidatesRequest $request): string
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

        $operation = $document->createElement('api:ws_register_candidates');
        $body->appendChild($operation);

        $this->appendTextElement($document, $operation, 'appID', $request->getAppId());
        $msgId = $this->appendTextElement($document, $operation, 'msgID', $request->getMsgId());
        $msgId->setAttribute('xsi:type', 'xsd:string');

        $candidates = $document->createElement('candidates');
        $operation->appendChild($candidates);

        foreach ($request->getCandidates() as $candidateValues) {
            $candidate = $document->createElement('candidate');
            $candidates->appendChild($candidate);
            $candidateValues['school'] = $request->getSchoolCode();

            foreach (self::CANDIDATE_FIELDS as $field) {
                $this->appendTextElement($document, $candidate, $field, (string)($candidateValues[$field] ?? ''));
            }
        }

        return (string)$document->saveXML($document->documentElement);
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
