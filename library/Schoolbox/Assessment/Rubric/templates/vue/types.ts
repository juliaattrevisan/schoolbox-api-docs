export type RubricGrid = {
    id: number; // currently the assessment ID, or 0 for new rubrics
    levels: RubricGridLevel[];
};

export type RubricGridLevel = {
    id: number;
    name: string;
};
