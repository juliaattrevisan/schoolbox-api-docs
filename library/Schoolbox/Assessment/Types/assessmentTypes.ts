export type Activity = {
    id: number;
    title: string;
    unit: string;
    projectName: string;
    assessmentType: string;
    assessments: Activity[] | null;
    completionCount: number;
    studentCount: number;
    dueAt: string | null;
    dueAtFormatted: string | null;
    _links: {
        status: string;
    };
};
