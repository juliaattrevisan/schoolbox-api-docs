import { ResultTypes } from "./ResultTypes";
import type { ResultContext } from "./ResultContext";
import type { Results } from "./Results";

/**
 * A search result
 */
export type Result = {
    id: number;
    type: ResultTypes;
    date: string;
    url: string;
    title: string;
    meta: string | null;
    image: string | null;
    description: string | null;
    creator?: {
        id: number;
        title?: string;
        firstName?: string;
        lastName?: string;
        fullName?: string;
        _links?: {
            profile?: string;
            avatar?: string;
        };
    };
    context: ResultContext[];
    icon?: string;
    _links: {
        subResults?: string; // url to load further results froms
    };
    _subResults?: Results; // further results within the result
};
