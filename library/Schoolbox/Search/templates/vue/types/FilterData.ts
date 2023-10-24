/**
 * The filter data currently sent through
 */
export type FilterData = {
    date: {
        from: string;
        to: string;
    };
    creator: {
        id: number;
    };
    types: string[];
};
