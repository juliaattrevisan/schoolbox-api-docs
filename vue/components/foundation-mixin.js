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
     */
    updated() {
        this.$nextTick(() => {
            this.jQuery(document)
                .foundation('dropdown', 'reflow')
                .foundation('equalizer', 'reflow');
        });
    },
}
