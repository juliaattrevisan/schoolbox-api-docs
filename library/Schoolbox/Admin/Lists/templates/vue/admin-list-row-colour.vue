<script>

/**
 * A 'pop out' for a row, it's a list of options that animate out from the right side
 * Providing a clearer focus on the list of options available to the current row. 
 * Its intent is to minimise the clutter of a checkbox farm that can become pretty messy UI wise. 
 */

export default {

    props: {
        caption: { //
            type: String,
            required: true
        },
        colour: { //
            type: String,
            required: true
        },
    },

    mounted () {
        this.initColourPicker();
    },

    methods: {

        /**
         * 
         */
        initColourPicker () {
            $('[data-colour-picker]').spectrum({
                allowEmpty: false,
                showAlpha: false,
                showInput: true,
                preferredFormat: 'hex',
                clickoutFiresChange: true,
                showInitial: true,
                change: (color) => {
                    debugger;
                    // $('[data-colour-picker]').val(color.toHexString());
                }
            });
        }

    }

}
</script>

<template>
    <input type="text" :placeholder="caption" v-model="colour" data-colour-picker />
</template>