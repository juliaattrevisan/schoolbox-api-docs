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
            required: true
        },
    },

    mounted () {
        this.initColourPicker();
    },

    methods: {

        /**
         * Set up the jQuery spectrum colour picker and emit changes
         */
        initColourPicker () {
            $(this.$el).spectrum({
                allowEmpty: false,
                showAlpha: false,
                showInput: true,
                preferredFormat: 'hex',
                clickoutFiresChange: true,
                showInitial: true,
                change: (color) => {
                    this.$emit('input', color.toHexString())
                }
            });
        }

    }

}
</script>

<template>
    <input type="text" :placeholder="caption" :value="colour" data-colour-picker />
</template>