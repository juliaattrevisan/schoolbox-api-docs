<?php

/**
 * @author      Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Moderation\Result;

use JsonSerializable;
use Schoolbox\GenericCollection;

/**
 * A dedicated concern category for an AI Interaction Moderation event.
 */
class Category implements JsonSerializable
{
    /**
     * Constructor
     *
     * @param ConcernCategory             $category   The closest matching concern type identified in the interaction.
     * @param Severity                    $severity   The seriousness of this specific issue.
     * @param Confidence                  $confidence How confident the model is that this category is correctly identified.
     * @param string                      $reason     A clear explanation of why this issue was flagged.
     * @param GenericCollection<Evidence> $evidence   Supporting excerpts from the interaction, if any.
     */
    public function __construct(
        private ConcernCategory $category,
        private Severity $severity,
        private Confidence $confidence,
        private string $reason,
        private GenericCollection $evidence,
    ) {
    }

    /**
     * @return ConcernCategory
     */
    public function getCategory(): ConcernCategory
    {
        return $this->category;
    }

    /**
     * @return Severity
     */
    public function getSeverity(): Severity
    {
        return $this->severity;
    }

    /**
     * @return Confidence
     */
    public function getConfidence(): Confidence
    {
        return $this->confidence;
    }

    /**
     * @return string
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * @return GenericCollection<Evidence>
     */
    public function getEvidence(): GenericCollection
    {
        return $this->evidence;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'category' => $this->category->value,
            'severity' => $this->severity->value,
            'confidence' => $this->confidence->value,
            'reason' => $this->reason,
            'evidence' => $this->evidence->jsonSerialize(),
        ];
    }

    /**
     * Build a Category from an array.
     *
     * @param array $data
     * @return self
     * @throws \ValueError for a mismatch in expected data types (such as an invalid enum value)
     */
    public static function fromArray(array $data): self
    {
        $evidence = new GenericCollection(Evidence::class);
        foreach ($data['evidence'] ?? [] as $evidenceItem) {
            $evidence->add(Evidence::fromArray($evidenceItem));
        }

        return new self(
            category: ConcernCategory::from((string)($data['category'] ?? '')),
            severity: Severity::from((string)($data['severity'] ?? '')),
            confidence: Confidence::from((string)($data['confidence'] ?? '')),
            reason: (string)($data['reason'] ?? ''),
            evidence: $evidence,
        );
    }
}
