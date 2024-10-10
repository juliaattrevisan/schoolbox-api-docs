import type { Result } from "./Result";

/**
 * The returned search data
 */
export type Results = {
    data: Result[];
    metadata: {
        cursor: {
            next: string | null;
        };
        url?: string;
    };
};
