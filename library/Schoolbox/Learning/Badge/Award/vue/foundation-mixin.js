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
     * Reflow everything
     *  - Re-apply Foundation dropdowns
     */
    updated() {
        this.$nextTick(() => this.jQuery(this.$el).foundation('dropdown', 'reflow'));
    },
}
