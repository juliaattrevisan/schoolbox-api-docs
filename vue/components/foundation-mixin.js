/**
 * Restart all of foundation's stuff to avoid its bs
 */
export default {
    /**
     * Get access to jquery
     */
    computed: {
        jQuery: () => window.jQuery
    },

    /**
     * Reflow everything in the entire document:
     *  - Re-apply Foundation dropdowns
     *  - Re-apply equalizer
     *
     * The entire document needs to be reflowed, because:
     * * `this.$el` may not yet contain the dropdowns
     * * `this.$el` may be replaced with another DOM node entirely?
     *
     * According to Foundation's docs it is appropriate to reflow the entire doc
     * when re-applying listeners when the DOM changes:
     * see https://get.foundation/sites/docs-v5/javascript.html#adding-new-content-after-page-load
     */
    updated() {
        this.$nextTick(() => {
            this.jQuery(document)
                .foundation('dropdown', 'reflow')
                .foundation('equalizer', 'reflow');
        });
    },
}
