<script>
/**
 * To initiate the Coloris colour picker and keep track of the colour changes
 * Make use of v-model to keep track of <input> changes
 */
import { mapActions } from "pinia";
import { useAdminListStore } from "../store/adminListStore";

export default {
    props: {
        field: {
            // field the lookup is linked to
            type: Object,
            required: true,
        },
        row: {
            // the data for the lookup
            type: Object,
            required: true,
        },
        colour: {
            // the colour chosen
            type: String,
            required: true,
        },
    },

    data() {
        return {
            uid: `coloris-${this.getNewColourPickerId()}`, // generate a unique id for the colour picker
        };
    },

    watch: {
        /**
         * Ensure the correct colour is always set on the row,
         * as the colour could also change if we delete a row above,
         * so we need to ensure the colour picker stays up to date
         *
         * @param { string } newValue - the new colour value
         */
        colour(newValue) {
            this.$el.value = newValue;
            // as we manually set the value, we need to manually trigger the input event: https://coloris.js.org/
            document.querySelector(`#${this.uid}`).dispatchEvent(new Event("input", { bubbles: true }));
        },
    },

    mounted() {
        this.initColourPicker();
    },

    methods: {
        ...mapActions(useAdminListStore, ["getNewColourPickerId"]),

        /**
         * Set up the Coloris colour picker, passing in the required parameters
         * Refer to https://coloris.js.org/ for further info
         */
        initColourPicker() {
            document.dispatchEvent(
                // dispatched to www/static/js/colour-picker/colour-picker.ts
                new CustomEvent("colourPickerInitialize", {
                    detail: {
                        target: `#${this.uid}`,
                        options: {
                            alpha: false,
                            clearButton: true,
                        },
                    },
                })
            );
        },
    },
};
</script>

<template>
    <input :id="uid" v-model="row[field.id]" type="text" :placeholder="field.caption" />
</template>
