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
        footerUI_update() {
            this.footerUI_pushUIIntoFooterSlot();
        },

        /**
         * Move all the existing UI into the supplied 'afterTable' slot,
         * placing in order of display. Depending on the number of items in
         * the list and current pagination count settings, not all UI elements
         * will always be available, so we need to check for the existence of each.
         */
        footerUI_pushUIIntoFooterSlot() {
            const perPageControls = document.querySelector(".VueTables__limit");
            if (perPageControls) {
                this.$refs.tableFooter.appendChild(perPageControls);
            }
            const paginationCount = document.querySelector(".VuePagination__count");
            if (paginationCount) {
                this.$refs.tableFooter.appendChild(paginationCount);
            }
            const paginationControls = document.querySelector(".VuePagination");
            if (paginationControls) {
                this.$refs.tableFooter.appendChild(paginationControls);
            }
        },
    },
};
