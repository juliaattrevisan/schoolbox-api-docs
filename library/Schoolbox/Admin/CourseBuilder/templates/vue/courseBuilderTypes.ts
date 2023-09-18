export type PageTemplate = {
    id: number;
    name: string;
};

export type CourseTemplate = {
    id: number;
    name: string;
    courseTemplate: PageTemplate;
    unitTemplate: PageTemplate;
};
