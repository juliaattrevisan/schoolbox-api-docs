/**
 * Filters form data to send to the backend
 */
export type FiltersFormData = {
    date: {
        from?: string;
        to?: string;
    };
    creator: {
        id?: number;
    };
    types: string[];
};
