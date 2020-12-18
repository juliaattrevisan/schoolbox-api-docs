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
            this.jQuery(this.$el)
                .foundation('dropdown', 'off')
                // Equalizer doesn't have an 'off' event for some reason.
                // Attempting to call it raises an error.
                // .foundation('equalizer', 'off')
                .foundation('dropdown', 'reflow')
                .foundation('equalizer', 'reflow');
        });
    },
}
