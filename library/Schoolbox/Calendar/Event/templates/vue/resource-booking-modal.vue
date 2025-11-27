<template>
    <reveal-modal ref="modal">
        <h3>Add Resource Booking</h3>
        <p>Modal content goes here...</p>
        <!-- Add your modal content here -->
    </reveal-modal>
</template>

<script>
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
