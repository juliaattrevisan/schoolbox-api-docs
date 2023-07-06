/**
 * Returns the default options for vue-tables-2
 * https://matanya.gitbook.io/vue-tables-2/options-api
 * @returns {object} default options for vue-tables-2
 */
export default function getDefaultOptions() {
    return {
        alwaysShowPerPageSelect: true, // show per page select even when there is only one value in the list
        debounce: 100, // shorter debounce time as data is stored locally
        perPage: 50,
        perPageValues: [50, 100, 200],
        resizableColumns: false,
        tabbable: false,
    };
}
