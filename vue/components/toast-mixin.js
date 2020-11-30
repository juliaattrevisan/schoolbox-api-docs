/**
 * These parts are needed for vue components to connect with our classical toaster
 * in twig town. You need a <div data-toast class='toast'> element in the twig template
 * which is the launch area for vue.
 */
export default {

    /**
     * Get access to jquery
     */
    computed: {
        jQuery: () => window.jQuery
    },

    /**
     * Alias some of the more common usages
     */
    methods: {

        /**
         * Start toaster with this text
         *
         * @param text
         */
        toastActivate(text) {
            this.jQuery('[data-toast]').toastActivate({
                text: text
            });
        },

        /**
         * Toast Success
         *
         * @param text
         */
        toastSuccess(text) {
            this.jQuery('[data-toast]').toastTimeout({
                text: text,
                css: 'success'
            });
        },


        /**
         * Toast Error
         *
         * @param text
         */
        toastError(text) {
            this.jQuery('[data-toast]').toastTimeout({
                text: text,
                css: 'alert'
            });
        }
    }
}
