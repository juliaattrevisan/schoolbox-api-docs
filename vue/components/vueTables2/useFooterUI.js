/**
 * For a vue-table-2 to show the UI at the bottom right of the table
 *
 * @TODO - we currently assume that there is only ever one vue-table on the page
 *         for now this is a safe assumption, but it could cause issues in the future
 *         if we ever put more than one vue-table on a page
 *
 */

/**
 * To adjust the UI display,
 * putting everything down the bottom right
 */
export async function updateFooterUI() {
    await window.Vue.nextTick(); //to ensure the footer is available in the DOM
    pushUIIntoFooterSlot();
}

/**
 * Move all the existing UI into the supplied 'afterTable' slot,
 * placing in order of display. Depending on the number of items in
 * the list and current pagination count settings, not all UI elements
 * will always be available, so we need to check for the existence of each.
 */
function pushUIIntoFooterSlot() {
    const perPageControls = document.querySelector(".VueTables__limit");
    const tableFooter = document.querySelector("[data-table-footer]");
    if (perPageControls && tableFooter) {
        //may not exist depending on how many results are currently available / filtered
        tableFooter.appendChild(perPageControls);
        const paginationCount = document.querySelector(".VuePagination__count");
        tableFooter.appendChild(paginationCount);
        const paginationControls = document.querySelector(".VuePagination");
        tableFooter.appendChild(paginationControls);
    }
}
