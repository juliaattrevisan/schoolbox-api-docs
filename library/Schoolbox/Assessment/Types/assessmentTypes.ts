export type Activity = {
    id: number;
    title: string;
    unit: string;
    projectName: string;
    assessmentType: string;
    assessments: Activity[] | null;
    dueAt: string | null;
    dueAtFormatted: string | null;
    isSelfAssessableTask: boolean;
    _links: {
        status: string;
        summary: string;
        modify: string;
        modifyContent: string | null; // learnosity modify contents
    };
    completionCount: number | null;
    studentCount: number | null;
    progressTick: {
        url: string | null;
        progress: string | null;
    } | null;
};
