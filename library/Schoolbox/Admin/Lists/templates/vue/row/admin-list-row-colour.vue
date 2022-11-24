<script>

/**
 * Pass in the jQuery spectrum colour picker
 * Make use of Using v-model on Components to simulate v-model like input change
 * See: https://vuejs.org/v2/guide/components.html#Using-v-model-on-Components
 */

export default {

    props: {
        caption: { //just shown prior to load
            type: String,
            required: true
        },
        colour: { //the colour chosen
            type: String,
            required: false
        },
    },

    mounted () {
        this.setDefaultColour();
        this.initColourPicker();
    },

    watch: {

        /**
         * Ensure the correct colour is always set on the row,
         * as the colour could also change if we delete a row above,
         * so we need to ensure the colour picker stays up to date
         */
        colour (newValue) {
            $(this.$el).spectrum('set', newValue);
        }

    },

    methods: {

        /**
         * We don't want to save a row without ensuring a default colour is set
         * As then references that may expect a colour would be blank instead
         */
        setDefaultColour () {
            if (this.colour === '') {
                this.$emit('input', '#000000');
            }
        },

        /**
         * Set up the jQuery spectrum colour picker and emit changes
         */
        initColourPicker () {
            $(this.$el).spectrum(
                $.extend(
                    {},
                    window.sbProps.spectrum,
                    {
                        change: (color) => {this.$emit('input', color ? color.toHexString() : null);}
                    }
                )
            );
        }

    }

}
</script>

<template>
    <input type="text" :placeholder="caption" :value="colour" data-colour-picker/>
</template>
