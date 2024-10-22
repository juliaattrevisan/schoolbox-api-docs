import type { Unit } from "./Unit";

/**
 * The start of a SBX Course type
 */
export type Course = {
    id: number;
    title: string;
    units?: Unit[];
};
