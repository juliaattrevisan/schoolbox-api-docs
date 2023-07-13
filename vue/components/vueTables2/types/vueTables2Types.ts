/**
 * The propertes used within vue-tables-2
 * Refer to: https://matanya.gitbook.io/vue-tables-2/options-api
 */
export interface TableOptions {
    alwaysShowPerPageSelect?: boolean;
    columnsClasses?: object;
    debounce?: number;
    headings?: object;
    orderBy?: {
        ascending?: boolean;
        column?: string;
    };
    perPage?: number;
    perPageValues?: number[];
    resizableColumns?: boolean;
    sortable?: string[];
    tabbable?: boolean;
    texts?: {
        count?: string;
        filterPlaceholder?: string;
        noResults?: string;
    };
}
