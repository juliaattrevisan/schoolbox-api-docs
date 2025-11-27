<template>
    <reveal-modal ref="modal">
        <h3>Add Resource Booking</h3>
        <p>Modal content goes here...</p>
        <!-- Add your modal content here -->
    </reveal-modal>
</template>

<script>
/* eslint-disable eslint-comments/disable-enable-pair -- see below */
/* eslint-disable eslint-comments/no-unlimited-disable -- see below */
/* eslint-disable -- Ignore this file for ESLint checks for now (SA-18376) */
/* eslint-enable vue/no-v-html */
import RevealModal from "@schoolboxVue/components/reveal-modal";

export default {
    name: "resource-booking-modal",

    components: { RevealModal },

    methods: {
        open() {
            this.$refs.modal.open();
        },
    },
};
</script>
