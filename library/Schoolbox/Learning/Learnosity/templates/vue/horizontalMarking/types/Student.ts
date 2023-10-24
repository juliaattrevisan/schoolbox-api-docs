/**
 * Student type
 */

import type { StudentItem } from "./StudentItem";

export type Student = {
    id: string;
    firstname: string;
    lastname: string;
    name: string;
    elementId: string;
    hasReport: boolean;
    isReportLoaded: boolean;
    isExpanded: boolean;
    items: StudentItem[];
};
