export type PageTemplate = {
    id: number;
    name: string;
    deleted: boolean;
};

export type CourseTemplate = {
    id: number;
    name: string;
    courseTemplate: PageTemplate;
    unitTemplate: PageTemplate;
    nameHasValidationError?: true;
    sequence: number;
};

export type Role = {
    id: number;
    type: string;
    name: string;
    student: boolean;
    staff: boolean;
    parent: boolean;
    guest: boolean;
};

export type Permissions = {
    [roleId: string]: -1 | 0 | 1 | 2;
};
