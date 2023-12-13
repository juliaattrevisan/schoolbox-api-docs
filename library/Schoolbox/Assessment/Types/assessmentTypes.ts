export type Activity = {
    id: number;
    title: string;
    unit: string;
    projectName: string;
    assessmentType: string;
    assessments: Activity[] | null;
    completionCount: number; // number of students who have completed the activity
    studentCount: number; // total number of students assigned the activity
    dueAt: string | null;
    dueAtFormatted: string | null;
    _links: {
        status: string;
    };
};
