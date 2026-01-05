export type DialogAgentScenario = {
    title: string;
    description: string;
};

type DialogAgentFieldContext = {
    id: number;
    body: string;
    field: {
        id: number;
        title: string;
        meta: string | null;
    };
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
    rules: string | null;
    fieldContexts: DialogAgentFieldContext[];
    _routes: {
        postSandboxMessage: string | null;
    };
};

export type DialogAgentConfigureApiResponse = {
    _routes: {
        postSandboxMessage: string;
    };
};

export type DialogAgentConfigureApiRequest = Omit<DialogAgentAssessmentConfig, "fieldContexts" | "_routes"> & {
    fieldContexts: Record<DialogAgentFieldContext["id"], DialogAgentFieldContext["body"]>;
};
