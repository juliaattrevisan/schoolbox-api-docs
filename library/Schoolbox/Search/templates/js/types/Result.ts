import type { ResultTypes } from "./ResultTypes";
import type { ResultContext } from "./ResultContext";
import type { ResultLink } from "./ResultLink";
import type { Results } from "./Results";

/**
 * A search result
 */
export type Result = {
    id: number;
    type: ResultTypes;
    typeTitle: string; // the title of the type, with consideration for terminology settings
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
    _links: ResultLink[];
    _subResults?: Results; // further results within the result
};
