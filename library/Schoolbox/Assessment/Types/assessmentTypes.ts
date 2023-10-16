export type Activity = {
    id: number;
    title: string;
    unit: string;
    projectName: string;
    assessmentType: string;
    assessments: Activity[] | null;
    closeAt: string | null;
    closeAtFormatted: string | null;
    _links: {
        status: string;
    };
};
