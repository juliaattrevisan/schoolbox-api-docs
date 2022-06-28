/**
 * For a vue-table-2 to show the UI at the bottom right of the table
 * 
 * @TODO - we currently assume that there is only ever one vue-table on the page
 *         for now this is a safe assumption, but it could cause issues in the future
 *         if we ever put more than one vue-table on a page
 * 
 */

 import { nextTick } from 'vue';

 /**
  * To adjust the UI display, 
  * putting everything down the bottom right
  */
export async function updateFooterUI () {
    await nextTick(); //to ensure the footer is available in the DOM
    pushUIIntoFooterSlot();
    onlyShowNextPrevPagination();
}

/**
 * Move all the existing UI into the supplied 'afterTable' slot,
 * placing in order of display
 */
function pushUIIntoFooterSlot () {
    const perPageControls = document.querySelector('.VueTables__limit');
    const tableFooter = document.querySelector('[data-table-footer]');
    if (perPageControls) { //may not exist depending on how many results are currently available / filtered
        tableFooter.appendChild(perPageControls);
    }
    const paginationCount = document.querySelector('.VuePagination__count');
    tableFooter.appendChild(paginationCount);
    const paginationControls = document.querySelector('.VuePagination');
    tableFooter.appendChild(paginationControls);
}

/**
 * Keeping it simple, only showing next/previous buttons
 */
function onlyShowNextPrevPagination () {
    document.querySelectorAll('.VuePagination__pagination-item').forEach( (item) => {
        if (item.className.includes('prev-page') && !item.className.includes('prev-chunk')) { // this is the previous button
            item.style.display = 'block';  
        } else if (item.className.includes('next-page') && !item.className.includes('next-chunk')) { //this is the next button
            item.style.display = 'block';
        } else { //and this is all others, which are hidden
            item.style.display = 'none';
        }
    });
}