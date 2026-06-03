<?php

/**
 * IBIS register candidates SOAP response
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

class RegisterCandidatesResponse
{
    /**
     * @param array<int, array{candidateId: string|null, referenceId: string|null, text: string|null, successful: bool}> $candidateResponses
     * @param string[] $messages
     */
    public function __construct(
        private string $rawXml,
        private ?string $operationName,
        private ?string $faultCode,
        private ?string $faultString,
        private array $candidateResponses = [],
        private array $messages = []
    ) {
    }

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

        $candidateResponses = [];
        $messages = [];
        $responseNodes = $xpath->query('//*[local-name() = "ws_register_candidatesReturn"]/*[local-name() = "ws_register_candidatesReturn"]');

        foreach ($responseNodes as $responseNode) {
            if (!$responseNode instanceof DOMElement) {
                continue;
            }

            $candidateId = self::firstChildText($xpath, $responseNode, 'candidateId');
            $referenceId = self::firstChildText($xpath, $responseNode, 'referenceId');
            $text = self::firstChildText($xpath, $responseNode, 'text');
            $successful = $candidateId !== null && $referenceId !== null && strcasecmp((string)$text, 'Success') === 0;

            if ($candidateId !== null || $referenceId !== null) {
                $candidateResponses[] = [
                    'candidateId' => $candidateId,
                    'referenceId' => $referenceId,
                    'text' => $text,
                    'successful' => $successful,
                ];
                continue;
            }

            if ($text !== null && $text !== '') {
                $messages[] = $text;
            }
        }

        return new self($xml, $operationName, null, null, $candidateResponses, $messages);
    }

    public function getRawXml(): string
    {
        return $this->rawXml;
    }

    public function getOperationName(): ?string
    {
        return $this->operationName;
    }

    public function isSuccess(): bool
    {
        return !$this->isFault() && $this->messages === [] && $this->candidateResponses !== []
            && count($this->getFailedCandidateResponses()) === 0;
    }

    public function isFault(): bool
    {
        return $this->faultCode !== null || $this->faultString !== null;
    }

    public function getFaultCode(): ?string
    {
        return $this->faultCode;
    }

    public function getFaultString(): ?string
    {
        return $this->faultString;
    }

    /**
     * @return array<int, array{candidateId: string|null, referenceId: string|null, text: string|null, successful: bool}>
     */
    public function getCandidateResponses(): array
    {
        return $this->candidateResponses;
    }

    /**
     * @return array<int, array{candidateId: string|null, referenceId: string|null, text: string|null, successful: bool}>
     */
    public function getFailedCandidateResponses(): array
    {
        return array_values(array_filter(
            $this->candidateResponses,
            fn(array $candidateResponse): bool => !$candidateResponse['successful']
        ));
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getErrorSummary(): ?string
    {
        if ($this->faultString !== null && $this->faultString !== '') {
            return $this->faultString;
        }

        if ($this->faultCode !== null && $this->faultCode !== '') {
            return $this->faultCode;
        }

        $messages = $this->messages;

        foreach ($this->getFailedCandidateResponses() as $candidateResponse) {
            if ($candidateResponse['text'] !== null && $candidateResponse['text'] !== '') {
                $messages[] = $candidateResponse['text'];
            }
        }

        if ($messages === []) {
            return null;
        }

        return implode('; ', array_unique($messages));
    }

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
