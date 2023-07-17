import { ResultTypes } from "./ResultTypes";
import type { ResultContext } from "./ResultContext";

/**
 * A search result
 */
export type Result = {
    id: number;
    type: ResultTypes;
    date: string;
    url: string;
    title: string;
    image: string | null;
    description: string | null;
    creator: {
        id: number;
        title?: string;
        firstName?: string;
        lastName?: string;
        fullName?: string;
    };
    context: ResultContext[] | null;
    icon?: string;
    _links: {
        subResults?: string; // url to load further results froms
        stream?: string; // when the result is a stream, this is the url to load the stream from
        data?: [];
        metadata?: {
            cursor: {
                current: string;
                next: string;
            };
        };
    };
};
