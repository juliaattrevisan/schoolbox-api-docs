import type { Unit } from "./Unit";

/**
 * The start of a SBX Course type
 */
export type Course = {
    id: number;
    name: string;
    subjectCode?: string;
    units?: Unit[];
    folder?: {
        _links: {
            view: string; // URL to view the folder
        };
    };
};
