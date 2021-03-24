/**
 * A mixin for the CDN prefix, as a component property.
 */
export default {
    props: {
        /**
         * The CDN prefix for this instance: used as the source from which
         * scripts are loaded.
         */
        cdnPrefix: {
            type: String,
            default: '',
        },
    },
}
