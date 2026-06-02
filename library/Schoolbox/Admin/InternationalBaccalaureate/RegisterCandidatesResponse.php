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
    public function __construct(
        private string $rawXml,
        private ?string $operationName,
        private ?string $faultCode,
        private ?string $faultString
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
                self::firstChildText($xpath, $fault, 'faultstring')
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

        return new self($xml, $operationName, null, null);
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
        return !$this->isFault();
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

    private static function firstChildText(DOMXPath $xpath, DOMElement $parent, string $localName): ?string
    {
        $node = $xpath->query('*[local-name() = "' . $localName . '"]', $parent)->item(0);
        if (!$node instanceof DOMElement) {
            return null;
        }

        return trim($node->textContent);
    }
}
