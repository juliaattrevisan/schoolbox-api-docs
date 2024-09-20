import { Unit } from "./Unit";

/**
 * The start of a SBX Course type
 */
export type Course = {
    id: number;
    name: string;
    units?: Unit[];
};
