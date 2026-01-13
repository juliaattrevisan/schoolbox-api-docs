export type DialogAgentScenario = {
    title: string;
    description: string;
};

export type DialogAgentScenarioField = {
    id: number;
    title: string;
    meta: string | null;
};

type DialogAgentFieldContext = {
    id: number;
    body: string;
    field: DialogAgentScenarioField;
};

/** @see /library/Schoolbox/Learning/Assessment/DialogAgent/Lookup/Helpfulness.php */
export enum DialogAgentHelpfulness {
    EXPLORATORY = "exploratory",
    GUIDED = "guided",
    DIRECTED = "directed",
}

export type DialogAgentAssessmentConfig = {
    helpfulness: DialogAgentHelpfulness | null;
    language: string | null;
    useDifferentiationProfile: boolean;
    openingMessage: string | null;
    openingMessageScrubbed: string | null;
    rules: string | null;
    fieldContexts: DialogAgentFieldContext[];
    prompt: string | null;
    _routes: {
        postSandboxMessage: string | null;
    };
};

export type DialogAgentConfigureApiResponse = {
    prompt: string | null;
    openingMessageScrubbed: string | null;
    _routes: {
        postSandboxMessage: string;
    };
};

export type DialogAgentConfigureApiRequest = Omit<
    DialogAgentAssessmentConfig,
    "fieldContexts" | "openingMessageScrubbed" | "prompt" | "_routes"
> & {
    fieldContexts: Record<DialogAgentFieldContext["id"], DialogAgentFieldContext["body"]>;
};
