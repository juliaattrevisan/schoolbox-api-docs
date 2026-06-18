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
 * A full AI Interaction Moderation agent result.
 * MUST match the response schema defined in the agent's structured response format.
 *
 * @see /library/Schoolbox/Editor/OpenAI/Payload/InteractionModeration.php RESPONSE_FORMAT
 */
class Result implements JsonSerializable
{
    /**
     * Constructor
     *
     * @param RiskLevel         $overallRiskLevel        The highest risk level identified across the full interaction.
     * @param string            $summary                 A short plain English summary explaining why the interaction was or was not flagged.
     * @param GenericCollection<Category> $categories    A list of detected concern categories. An empty array if no concerns are found.
     * @param PatternAssessment $patternAssessment       Describes whether the interaction shows repeated, escalating, or evasive behaviour across multiple messages.
     * @param RecommendedAction $recommendedSchoolAction Suggested level of response for the school based on the interaction.
     * @param bool              $needsHumanReview        true if any concern exists that should be reviewed by a staff member.
     * @param bool              $safeguardingPriority    true if the interaction relates to student safety or wellbeing, such as self-harm, threats, grooming, or exploitation.
     * @param bool              $disciplinePriority      true if the interaction relates to behaviour or policy breaches, such as cheating, misuse of AI, guardrail evasion, or abuse.
     */
    public function __construct(
        private RiskLevel $overallRiskLevel,
        private string $summary,
        private GenericCollection $categories,
        private PatternAssessment $patternAssessment,
        private RecommendedAction $recommendedSchoolAction,
        private bool $needsHumanReview,
        private bool $safeguardingPriority,
        private bool $disciplinePriority,
    ) {
    }

    /**
     * @return RiskLevel
     */
    public function getOverallRiskLevel(): RiskLevel
    {
        return $this->overallRiskLevel;
    }

    /**
     * @return string
     */
    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * @return GenericCollection<Category>
     */
    public function getCategories(): GenericCollection
    {
        return $this->categories;
    }

    /**
     * @return PatternAssessment
     */
    public function getPatternAssessment(): PatternAssessment
    {
        return $this->patternAssessment;
    }

    /**
     * @return RecommendedAction
     */
    public function getRecommendedSchoolAction(): RecommendedAction
    {
        return $this->recommendedSchoolAction;
    }

    /**
     * @return bool
     */
    public function getNeedsHumanReview(): bool
    {
        return $this->needsHumanReview;
    }

    /**
     * @return bool
     */
    public function isSafeguardingPriority(): bool
    {
        return $this->safeguardingPriority;
    }

    /**
     * @return bool
     */
    public function isDisciplinePriority(): bool
    {
        return $this->disciplinePriority;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'overall_risk_level' => $this->overallRiskLevel->value,
            'summary' => $this->summary,
            'categories' => $this->categories->jsonSerialize(),
            'pattern_assessment' => $this->patternAssessment->jsonSerialize(),
            'recommended_school_action' => $this->recommendedSchoolAction->jsonSerialize(),
            'needs_human_review' => $this->needsHumanReview,
            'safeguarding_priority' => $this->safeguardingPriority,
            'discipline_priority' => $this->disciplinePriority,
        ];
    }

    /**
     * Build a result from an array.
     *
     * @param array $data
     * @return self
     * @throws \ValueError for a mismatch in expected data types (such as an invalid enum value)
     */
    public static function fromArray(array $data): self
    {
        $categories = new GenericCollection(Category::class);
        foreach ($data['categories'] ?? [] as $categoryItem) {
            $categories->add(Category::fromArray($categoryItem));
        }

        return new self(
            overallRiskLevel: RiskLevel::from((string)($data['overall_risk_level'] ?? '')),
            summary: (string)($data['summary'] ?? ''),
            categories: $categories,
            patternAssessment: PatternAssessment::fromArray($data['pattern_assessment'] ?? []),
            recommendedSchoolAction: RecommendedAction::fromArray($data['recommended_school_action'] ?? []),
            needsHumanReview: (bool)($data['needs_human_review'] ?? false),
            safeguardingPriority: (bool)($data['safeguarding_priority'] ?? false),
            disciplinePriority: (bool)($data['discipline_priority'] ?? false),
        );
    }
}
