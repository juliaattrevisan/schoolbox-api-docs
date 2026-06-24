/**
 * The highest risk level identified across the full interaction by the AI Interaction Moderation agent.
 * @see /library/Schoolbox/OpenAi/Moderation/Result/RiskLevel.php
 */
enum RiskLevel {
    NONE = "none",
    LOW = "low",
    MEDIUM = "medium",
    HIGH = "high",
    CRITICAL = "critical",
}

/**
 * The seriousness of an individual concern raised by the AI Interaction Moderation agent.
 * @see /library/Schoolbox/OpenAi/Moderation/Result/Severity.php
 */
enum Severity {
    NONE = "none",
    LOW = "low",
    MEDIUM = "medium",
    HIGH = "high",
    CRITICAL = "critical",
}

/**
 * How confident the AI Interaction Moderation agent is that a particular concern category is correctly identified.
 * @see /library/Schoolbox/OpenAi/Moderation/Result/Confidence.php
 */
enum Confidence {
    LOW = "low",
    MEDIUM = "medium",
    HIGH = "high",
}

/**
 * A type of concern identified by the AI Interaction Moderation agent.
 * @see /library/Schoolbox/OpenAi/Moderation/Result/ConcernCategory.php
 */
enum ConcernCategory {
    SELF_HARM = "self_harm",
    WELLBEING_CONCERN = "wellbeing_concern",
    BULLYING = "bullying",
    HARASSMENT = "harassment",
    HATE_SPEECH = "hate_speech",
    ABUSE = "abuse",
    VIOLENCE = "violence",
    THREAT = "threat",
    SEXUAL_CONTENT = "sexual_content",
    EXPLOITATION = "exploitation",
    GROOMING = "grooming",
    PRIVACY_RISK = "privacy_risk",
    DANGEROUS_BEHAVIOUR = "dangerous_behaviour",
    ACADEMIC_MISUSE = "academic_misuse",
    AI_MISUSE = "ai_misuse",
    GUARDRAIL_EVASION = "guardrail_evasion",
    DISCIPLINE_CONCERN = "discipline_concern",
    OTHER = "other",
}

/**
 * Suggested school response level from the AI Interaction Moderation agent.
 * @see /library/Schoolbox/OpenAi/Moderation/Result/ActionLevel.php
 */
enum ActionLevel {
    NONE = "none",
    MONITOR = "monitor",
    REVIEW = "review",
    URGENT_REVIEW = "urgent_review",
    IMMEDIATE_ESCALATION = "immediate_escalation",
}

/**
 * A supporting excerpt relating to a specific concern category.
 * @see /library/Schoolbox/OpenAi/Moderation/Result/Evidence.php
 */
type Evidence = {
    /** A short exact excerpt from the interaction. */
    quote: string;
    /** Where the quote came from in the interaction. */
    message_position: string;
    /** A short explanation of why this quote supports the concern. */
    why_it_matters: string;
};

/**
 * A dedicated concern category for an AI Interaction Moderation event.
 * @see /library/Schoolbox/OpenAi/Moderation/Result/Category.php
 */
type Category = {
    /** The closest matching concern type identified in the interaction. */
    category: ConcernCategory;
    /** The seriousness of this specific issue. */
    severity: Severity;
    /** How confident the model is that this category is correctly identified. */
    confidence: Confidence;
    /** A clear explanation of why this issue was flagged. */
    reason: string;
    /** Supporting excerpts from the interaction, if any. */
    evidence: Evidence[];
};

/**
 * Describes whether the interaction shows repeated, escalating, or evasive behaviour across multiple messages.
 * @see /library/Schoolbox/OpenAi/Moderation/Result/PatternAssessment.php
 */
type PatternAssessment = {
    /** true if behaviour is repeated, escalating, or shows attempts to bypass rules. */
    pattern_detected: boolean;
    /** An explanation of the detected pattern. If no concerning pattern exists, state that no concerning pattern was detected. */
    description: string;
};

/**
 * Suggested level of response for the school based on the interaction.
 * @see /library/Schoolbox/OpenAi/Moderation/Result/RecommendedAction.php
 */
type RecommendedAction = {
    action_level: ActionLevel;
    /** Explain why this action level is recommended. */
    why: string;
};

/**
 * A full AI Interaction Moderation agent result.
 * @see /library/Schoolbox/OpenAi/Moderation/Result/Result.php
 */
export type AiModerationResult = {
    overall_risk_level: RiskLevel;
    /** A short plain English summary explaining why the interaction was or was not flagged. */
    summary: string;
    /** A list of detected concern categories. An empty array if no concerns are found. */
    categories: Category[];
    pattern_assessment: PatternAssessment;
    recommended_school_action: RecommendedAction;
    /** true if the interaction relates to student safety or wellbeing, such as self-harm, threats, grooming, or exploitation. */
    safeguarding_priority: boolean;
    /** true if the interaction relates to behaviour or policy breaches, such as cheating, misuse of AI, guardrail evasion, or abuse. */
    discipline_priority: boolean;
};
