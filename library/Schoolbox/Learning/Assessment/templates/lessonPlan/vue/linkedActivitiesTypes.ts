export type Activity = {
    id: number;
    title: string;
    unit: string;
    projectName: string;
    assessmentType: string;
    assessments: Activity[];
    closeAt: string;
    closeAtFormatted: string;
    _links: {
        status: string;
    };
};

export type Unit = {
    id: number;
    title: string;
    openDate: string;
    closeDate: string;
    relativeOpenDateFormatted: string;
    relativeCloseDateFormatted: string;
    assessments: Activity[];
};

export type CourseAssessments = {
    units: Unit[];
};
