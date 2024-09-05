import type { AIAssistantConfig } from "@ckeditor/ckeditor5-ai/src/aiassistant";

interface SbxAIAssistantConfig {
    ai: {
        openAI: {
            apiUrl: string;
            requestParameters: {
                model: string | undefined;
            };
        };
        aiAssistant: AIAssistantConfig;
        useTheme: boolean;
    };
}

/**
 * Returns the settings for the AI assistant
 * @param model - the model to use for the AI assistant
 * @returns - the AI Assistant settings
 */
export function getAISettings(model: string | undefined): SbxAIAssistantConfig {
    return {
        ai: {
            openAI: {
                apiUrl: "/openai",
                requestParameters: {
                    model,
                },
            },
            aiAssistant: {
                // Define the commands list from scratch.
                commands: [
                    {
                        groupId: "editOrReview",
                        groupLabel: "Edit or review",
                        commands: [
                            {
                                id: "improveWriting",
                                label: "Improve writing",
                                prompt:
                                    "Rewrite this content with no spelling mistakes, proper grammar, " +
                                    "and with more descriptive language, using best writing practices without losing the original meaning.",
                            },
                            {
                                id: "makeShorter",
                                label: "Make shorter",
                                prompt:
                                    "Remove any repetitive, redundant, or non-essential writing in this content " +
                                    "without changing the meaning or losing any key information.",
                            },
                            {
                                id: "makeLonger",
                                label: "Make longer",
                                prompt:
                                    "Expand upon this content with descriptive language and more detailed explanations, " +
                                    "to make the writing easier to understand and increase the length of the content.",
                            },
                            {
                                id: "simplifyLanguage",
                                label: "Simplify language",
                                prompt:
                                    "Rewrite this content with simplified language " +
                                    "and reduce the complexity of the writing, so that the content is easier to understand.",
                            },
                        ],
                    },
                    {
                        groupId: "ageAppropriate",
                        groupLabel: "Appropriate Language",
                        commands: [
                            {
                                id: "junior",
                                label: "For Junior Students",
                                prompt: "Rewrite this content using age appropriate language that a junior school student could easily understand.",
                            },
                            {
                                id: "senior",
                                label: "For Senior Students",
                                prompt: "Rewrite this content using age appropriate language that a senior school student could easily understand.",
                            },
                            {
                                id: "teachers",
                                label: "For Teachers",
                                prompt: "Rewrite this content using professional teacher language",
                            },
                        ],
                    },
                    {
                        groupId: "generate",
                        groupLabel: "Generate from selection",
                        commands: [
                            {
                                id: "summarize",
                                label: "Summarize",
                                prompt: "Provide the key points and concepts in this content in a succinct summary.",
                            },
                            {
                                id: "continue",
                                label: "Continue",
                                prompt: "Continue this text.",
                            },
                        ],
                    },
                    {
                        groupId: "changeTone",
                        groupLabel: "Change tone",
                        commands: [
                            {
                                id: "professional",
                                label: "Professional",
                                prompt:
                                    "Rewrite this content using polished, formal, and respectful language " +
                                    "to convey professional expertise and competence.",
                            },
                            {
                                id: "casual",
                                label: "Casual",
                                prompt: "Rewrite this content with casual, informal language to convey a casual conversation with a real person.",
                            },
                            {
                                id: "direct",
                                label: "Direct",
                                prompt: "Rewrite this content with direct language using only the essential information.",
                            },
                            {
                                id: "confident",
                                label: "Confident",
                                prompt: "Rewrite this content using compelling, optimistic language to convey confidence in the writing.",
                            },
                            {
                                id: "friendly",
                                label: "Friendly",
                                prompt: "Rewrite this content using friendly, comforting language, to convey understanding and empathy.",
                            },
                        ],
                    },
                    {
                        groupId: "translate",
                        groupLabel: "Translate",
                        commands: [
                            {
                                id: "translateEnglish",
                                label: "Translate to English",
                                prompt: "Translate to English language.",
                            },
                            {
                                id: "translateSpanish",
                                label: "Translate to Spanish",
                                prompt: "Translate to Spanish language.",
                            },
                            {
                                id: "translatePortuguese",
                                label: "Translate to Portuguese",
                                prompt: "Translate to Portuguese language.",
                            },
                            {
                                id: "translateGerman",
                                label: "Translate to German",
                                prompt: "Translate to German language.",
                            },
                            {
                                id: "translateFrench",
                                label: "Translate to French",
                                prompt: "Translate to French language.",
                            },
                            {
                                id: "translateChinese",
                                label: "Translate to Simplified Chinese",
                                prompt: "Translate to Simplified Chinese language.",
                            },
                            {
                                id: "translateHindi",
                                label: "Translate to Hindi",
                                prompt: "Translate to Hindi language.",
                            },
                            {
                                id: "translateArabic",
                                label: "Translate to Arabic",
                                prompt: "Translate to Arabic language.",
                            },
                        ],
                    },
                ],
            },
            // Disable the default feature's theme.
            useTheme: false,
        },
    };
}
