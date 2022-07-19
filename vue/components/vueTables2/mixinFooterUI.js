/**
 * For a vue-table-2 to show the UI at the bottom right of the table
 * 
 */


export default {

    methods: {

        /**
         * To adjust the UI display, 
         * putting everything down the bottom right
         */
        footerUI_update () {
            this.footerUI_pushUIIntoFooterSlot();
        },

        /**
         * Move all the existing UI into the supplied 'afterTable' slot,
         * placing in order of display
         */
        footerUI_pushUIIntoFooterSlot () {
            const perPageControls = document.querySelector('.VueTables__limit');
            this.$refs.tableFooter.appendChild(perPageControls);
            const paginationCount = document.querySelector('.VuePagination__count');
            this.$refs.tableFooter.appendChild(paginationCount);
            const paginationControls = document.querySelector('.VuePagination');
            this.$refs.tableFooter.appendChild(paginationControls);
        },

    }

}