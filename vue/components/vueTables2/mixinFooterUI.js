/**
 * For a vue-table-2 to show the UI at the bottom right of the table
 * 
 */


export const mixinFooterUI = {

    methods: {

        /**
         * To adjust the UI display, 
         * putting everything down the bottom right
         * 
         */
        $_footerUI_update () {
            this.$_footerUI_pushUIIntoFooterSlot();
            this.$_footerUI_onlyShowNextPrevPagination();
        },

        /**
         * Move all the existing UI into the supplied 'afterTable' slot,
         * placing in order of display
         * 
         */
        $_footerUI_pushUIIntoFooterSlot () {
            const perPageControls = document.querySelector('.VueTables__limit');
            if (perPageControls) { //may not exist depending on how many results are currently available / filtered
                this.$refs.tableFooter.appendChild(perPageControls);
            }
            const paginationCount = document.querySelector('.VuePagination__count');
            this.$refs.tableFooter.appendChild(paginationCount);
            const paginationControls = document.querySelector('.VuePagination');
            this.$refs.tableFooter.appendChild(paginationControls);
        },

        /**
         * Keeping it simple, only showing next/previous buttons
         * 
         */
        $_footerUI_onlyShowNextPrevPagination () {
            document.querySelectorAll('.VuePagination__pagination-item').forEach( (item) => {
                if (item.className.includes('prev-page') && !item.className.includes('prev-chunk')) {
                    item.style.display = 'block';  
                } else if (item.className.includes('next-page') && !item.className.includes('next-chunk')) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        },

    }

}