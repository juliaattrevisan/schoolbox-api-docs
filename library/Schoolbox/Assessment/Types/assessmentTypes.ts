export type Activity = {
    id: number;
    title: string;
    unit: string;
    projectName: string;
    assessmentType: string;
    assessments: Activity[] | null;
    dueAt: string | null;
    dueAtFormatted: string | null;
    _links: {
        status: string;
    };
};
