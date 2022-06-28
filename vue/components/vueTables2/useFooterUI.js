/**
 * For a vue-table-2 to show the UI at the bottom right of the table
 * 
 */

 import { nextTick  } from 'vue';

 /**
  * To adjust the UI display, 
  * putting everything down the bottom right
  */
export async function updateFooterUI (tableFooterEl) {
    await nextTick();
    pushUIIntoFooterSlot(tableFooterEl);
    onlyShowNextPrevPagination();
}

/**
 * Move all the existing UI into the supplied 'afterTable' slot,
 * placing in order of display
 */
function pushUIIntoFooterSlot (tableFooterEl) {
    const perPageControls = document.querySelector('.VueTables__limit');
    if (perPageControls) { //may not exist depending on how many results are currently available / filtered
        tableFooterEl.value.appendChild(perPageControls);
    }
    const paginationCount = document.querySelector('.VuePagination__count');
    tableFooterEl.value.appendChild(paginationCount);
    const paginationControls = document.querySelector('.VuePagination');
    tableFooterEl.value.appendChild(paginationControls);
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