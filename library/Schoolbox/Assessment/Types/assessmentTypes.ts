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
    _links: {
        status: string;
        summary: string;
        modify: string;
        modifyContent: string | null; // learnosity modify contents
        delete: string;
        export: string | null; // Spec assessments can be exported
    };
    completionCount: number | null;
    studentCount: number | null;
    progressTick: {
        postRouteUrl: string | null;
        progressClass: string | null;
    } | null;
};
