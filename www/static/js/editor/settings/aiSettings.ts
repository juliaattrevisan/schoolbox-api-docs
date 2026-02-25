import type { AIAssistantConfig } from "ckeditor5-premium-features";

interface SbxAIAssistantConfig {
    ai: {
        openAI: {
            apiUrl: string;
            // requestParameters: {
                // max_completion_tokens: number;
                // temperature: number;
                // top_p: number;
                // stream: boolean;
                // model: string | undefined;
            // };
        };
        aiAssistant: AIAssistantConfig;
        useTheme: boolean;
    };
}

/**
 * Returns the settings for the AI assistant
 * @returns - the AI Assistant settings
 */
export function getAISettings(): SbxAIAssistantConfig {
    return {
        ai: {
            openAI: {
                apiUrl: "/openai",
                // requestParameters: {
                    // max_completion_tokens: 10000,
                    // temperature: 1,
                    // top_p: 1,
                    // stream: true,
                    // model,
                // },
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
                                    "Proofread and rewrite the following content to correct spelling and grammar errors, " +
                                    "and enhance clarity, flow, and descriptiveness. " +
                                    "Maintain the original meaning. Use a clear, concise, and engaging writing style.",
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
                                    "Expand the following content by using more descriptive language, elaborating on key ideas, " +
                                    "and adding relevant context or examples. " +
                                    "Ensure all additions are logically derived from the original text. " +
                                    "The goal is to make the writing clearer, more engaging, and longer, " +
                                    "without introducing new or unrelated information.",
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
                                prompt:
                                    "Rewrite the following content for students aged 6–11 (Grades 1–5). " +
                                    "Use simple, age-appropriate vocabulary, short and clear sentences, " +
                                    "and a supportive, instructional tone. " +
                                    "Keep all information accurate and easy to understand.",
                            },
                            {
                                id: "senior",
                                label: "For Senior Students",
                                prompt:
                                    "Rewrite the following content for a senior school audience (ages 13–18, Grades 7–12). " +
                                    "Use more advanced vocabulary, varied sentence structures, and a formal, academic tone. " +
                                    "Ensure the meaning remains clear and the information accurate.",
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
                                prompt:
                                    "Continue the following text by extending its ideas in a way that matches the existing tone, " +
                                    "style, and subject matter. Maintain coherence and flow with what has already been written.",
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
