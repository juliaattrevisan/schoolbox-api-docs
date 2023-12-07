export type Activity = {
    id: number;
    title: string;
    unit: string;
    projectName: string;
    progressTick: { url: string | null; progress: string } | null;
    assessmentType: string;
    assessments: Activity[] | null;
    completionCount: number; // number of students who have completed the activity
    studentCount: number; // total number of students assigned the activity
    dueAt: string | null;
    dueAtFormatted: string | null;
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
