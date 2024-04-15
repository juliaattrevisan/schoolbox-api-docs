export type Activity = {
    id: number;
    title: string;
    unit: string;
    projectName: string;
    assessmentType: string;
    assessments: Activity[] | null;
    dueAt: {
        atom: string;
        long: string;
        relative: string;
    } | null;
    isSelfAssessableTask: boolean;
    // _links is itself null if the user does not have access to any actions
    // Individual links are null if the user does not have access to the action, or if the action is not available
    _links: {
        status: string | null;
        summary: string | null;
        modify: string | null;
        modifyContent: string | null; // learnosity modify contents
        delete: string | null;
        export: string | null; // Spec assessments can be exported
    } | null;
    completionCount: number | null;
    studentCount: number | null;
    progressTick: {
        postRouteUrl: string | null;
        progressClass: string | null;
    } | null;
};

export type ActivityTypeLocale = {
    project: {
        proper: string;
    };
    dueWork: {
        proper: string;
    };
};

export type ActivitiesListRoutes = {
    create: string | null;
    reorder: string | null;
};
