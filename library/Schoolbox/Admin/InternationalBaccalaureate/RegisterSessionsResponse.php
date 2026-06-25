<?php

/**
 * IBIS register sessions SOAP response
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

use DOMDocument;
use DOMElement;
use DOMXPath;
use UnexpectedValueException;

/**
 * Parsed response from the IBIS register-sessions SOAP operation.
 *
 * IBIS can return SOAP faults, structured session rows, or free-form validation
 * messages without session rows. Keeping those separate lets the CLI decide
 * whether to update an individual candidate or log a request-level failure.
 */
class RegisterSessionsResponse
{
    /**
     * @param array<int, array{
     *     candidateId: string|null,
     *     month: string|null,
     *     year: string|null,
     *     text: string|null,
     *     successful: bool
     * }> $sessionResponses Structured per-session response rows.
     * @param string[] $messages Free-form validation messages returned without session identifiers.
     */
    public function __construct(
        private string $rawXml,
        private ?string $operationName,
        private ?string $faultCode,
        private ?string $faultString,
        private array $sessionResponses = [],
        private array $messages = []
    ) {
    }

    /**
     * Parse the raw SOAP XML returned by IBIS.
     */
    public static function fromXml(string $xml): self
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new UnexpectedValueException('IBIS response was not valid XML.');
        }

        $xpath = new DOMXPath($document);
        $fault = $xpath->query('//*[local-name() = "Fault"]')->item(0);
        if ($fault instanceof DOMElement) {
            return new self(
                $xml,
                null,
                self::firstChildText($xpath, $fault, 'faultcode'),
                self::firstChildText($xpath, $fault, 'faultstring'),
            );
        }

        $body = $xpath->query('//*[local-name() = "Body"]')->item(0);
        $operationName = null;
        if ($body instanceof DOMElement) {
            foreach ($body->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $operationName = $child->localName;
                    break;
                }
            }
        }

        $sessionResponses = [];
        $messages = [];
        $responseNodes = $xpath->query('//*[local-name() = "ws_register_sessionsReturn"]/*[local-name() = "ws_register_sessionsReturn"]');

        foreach ($responseNodes as $responseNode) {
            if (!$responseNode instanceof DOMElement) {
                continue;
            }

            $candidateId = self::firstChildText($xpath, $responseNode, 'candidateId');
            $month = self::firstChildText($xpath, $responseNode, 'month');
            $year = self::firstChildText($xpath, $responseNode, 'year');
            $text = self::firstChildText($xpath, $responseNode, 'text');
            $successful = $candidateId !== null && self::isSuccessText($text);

            /* Rows with candidate/session identifiers are tied to a Schoolbox
             * candidate. Rows with only text are request-level validation
             * messages.
             */
            if ($candidateId !== null || $month !== null || $year !== null) {
                $sessionResponses[] = [
                    'candidateId' => $candidateId,
                    'month' => $month,
                    'year' => $year,
                    'text' => $text,
                    'successful' => $successful,
                ];
                continue;
            }

            if ($text !== null && $text !== '') {
                $messages[] = $text;
            }
        }

        return new self($xml, $operationName, null, null, $sessionResponses, $messages);
    }

    /**
     * A response is successful only when IBIS returns at least one session row
     * and every row is successful.
     */
    public function isSuccess(): bool
    {
        return !$this->isFault() && $this->messages === [] && $this->sessionResponses !== []
            && count($this->getFailedSessionResponses()) === 0;
    }

    /**
     * Return the original response XML for diagnostic logging.
     */
    public function getRawXml(): string
    {
        return $this->rawXml;
    }

    /**
     * Return the first SOAP body element name, if one could be identified.
     */
    public function getOperationName(): ?string
    {
        return $this->operationName;
    }

    /**
     * Check whether the response used the SOAP Fault format.
     */
    public function isFault(): bool
    {
        return $this->faultCode !== null || $this->faultString !== null;
    }

    /**
     * Return the SOAP fault message when IBIS returned a fault.
     */
    public function getFaultString(): ?string
    {
        return $this->faultString;
    }

    /**
     * Return structured session response rows from IBIS.
     *
     * @return array<int, array{candidateId: string|null, month: string|null, year: string|null, text: string|null, successful: bool}>
     */
    public function getSessionResponses(): array
    {
        return $this->sessionResponses;
    }

    /**
     * Return session rows that IBIS explicitly rejected.
     *
     * @return array<int, array{candidateId: string|null, month: string|null, year: string|null, text: string|null, successful: bool}>
     */
    public function getFailedSessionResponses(): array
    {
        return array_values(array_filter(
            $this->sessionResponses,
            fn(array $sessionResponse): bool => !$sessionResponse['successful']
        ));
    }

    /**
     * Return the most useful human-readable error details for logs/UI.
     */
    public function getErrorSummary(): ?string
    {
        if ($this->faultString !== null && $this->faultString !== '') {
            return $this->faultString;
        }

        if ($this->faultCode !== null && $this->faultCode !== '') {
            return $this->faultCode;
        }

        $messages = $this->messages;

        foreach ($this->getFailedSessionResponses() as $sessionResponse) {
            if ($sessionResponse['text'] !== null && $sessionResponse['text'] !== '') {
                $messages[] = $sessionResponse['text'];
            }
        }

        if ($messages === []) {
            return null;
        }

        return implode('; ', array_unique($messages));
    }

    /**
     * Interpret the IBIS text field convention for session row success.
     */
    private static function isSuccessText(?string $text): bool
    {
        return $text === null || $text === '' || stripos($text, 'Success') === 0;
    }

    /**
     * Return trimmed text from the first child with the requested local name.
     */
    private static function firstChildText(DOMXPath $xpath, DOMElement $parent, string $localName): ?string
    {
        $node = $xpath->query('*[local-name() = "' . $localName . '"]', $parent)->item(0);
        if (!$node instanceof DOMElement) {
            return null;
        }

        $value = trim($node->textContent);

        return $value === '' ? null : $value;
    }
}
