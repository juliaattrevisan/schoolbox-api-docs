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
     * Switch off and then reflow everything
     *  - Re-apply Foundation dropdowns
     *  - Re-apply equalizer
     *
     * The dropdowns need to be switched off, because applying a dropdown twice
     * to the same DOM node applies two click events; this causes the dropdown
     * to open and immediately close on click.
     */
    updated() {
        this.$nextTick(() => {
            this.jQuery(document)
                .foundation('dropdown', 'reflow')
                .foundation('equalizer', 'reflow');
        });
    },
}
