<script>
/* eslint-disable eslint-comments/disable-enable-pair -- see below */
/* eslint-disable eslint-comments/no-unlimited-disable -- see below */
/* eslint-disable -- Ignore this file for ESLint checks for now (SA-18376) */
/* eslint-enable vue/no-v-html */
import RevealModal from "@schoolboxVue/components/reveal-modal";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import Toast from "@schoolboxVue/components/toast.vue";
import { ENDPOINTS } from "@schoolboxCalendarEvent/endpoints";

export default {
    name: "add-resource-bookings-modal",

    components: {
        RevealModal,
        SearchSelect,
        Toast,
    },

    props: {
        // This component should only be included if the resource bookings module is enabled.
        // So resourceBookings should always be set.
        resourceBookings: Array,
    },

    data() {
        return {
            booking: {},
            isProcessing: false, // To disable buttons on form submission to prevent rapid fire.
            selected: [],
            resources: [],
        };
    },

    computed: {
        window: () => window,

        /**
         * Can the booking form be submitted?
         *
         * @return {boolean}
         */
        isFormSubmittable() {
            return !this.isProcessing;
        },

        /**
         * Compile object ids into csv
         */
        compileResourceIds() {
            let stack = [];

            for (let i = 0; i < this.resources.length; i++) {
                stack.push(this.resources[i].id);
            }

            return stack.join(",");
        },
    },

    created() {
        this.ENDPOINTS = ENDPOINTS;
    },

    methods: {
        getAddBookingsEndpoint() {
            return new URL(this.ENDPOINTS.URL_BOOKINGS_ADD, window.location.href).toString();
        },

        /**
         * Get the endpoint for the booking resource search.
         * @returns {String} The endpoint for the booking resource search.
         */
        getResourceSearchEndpoint() {
            const searchUrl = new URL(this.ENDPOINTS.URL_BOOKING_SELECTIZE, window.location.href).toString();
            const excludeResourceIds = this.resourceBookings.map((booking) => booking.resource.id);
            return `${searchUrl}?excludeResourceIds=${excludeResourceIds.join(",")}&`;
        },

        /**
         * Open this modal.
         */
        open() {
            this.$refs.modal.open();
            this.selected = []; // Clear selectize contents.
            this.resources = [];
        },

        /**
         * Post the booking creation form.
         *
         * @param form The form that's being submitted
         */
        async createNewBooking(form) {
            this.isProcessing = true;
            const formContent = new FormData(form);
            this.$refs.toast.activate("Saving...");

            try {
                const response = await fetch(form.action, {
                    method: form.method,
                    body: formContent,
                    responseType: "json",
                    headers: {
                        Accept: "application/json",
                    },
                });
                const responseData = await response.json();
                if (response.ok) {
                    this.$refs.modal.close();
                    if (responseData.errors && responseData.errors.length > 0) {
                        this.$refs.toast.timeout(
                            "Booking(s) created with some issues: " + responseData.errors.join(", "),
                            false,
                            4000,
                        );
                    } else {
                        this.$refs.toast.timeout("Booking(s) created", true, 2000);
                    }
                    // The new booking was successfully added, so reload the page to make it appear.
                    window.location.reload();
                } else {
                    this.$refs.toast.timeout("Unable to create booking(s). " + responseData.message, false, 3000);
                }
            } catch (error) {
                this.$refs.toast.timeout("Unable to create booking(s). " + error.message, false, 3000);
                console.warn(error);
            } finally {
                this.isProcessing = false;
            }
        },
    },
};
</script>

<template>
    <toast ref="toast"></toast>
    <reveal-modal ref="modal">
        <form
            ref="createBookingForm"
            method="post"
            :action="getAddBookingsEndpoint()"
            @submit.prevent="createNewBooking($refs.createBookingForm)"
            class="top"
        >
            <h2>Add Resource Bookings</h2>
            <section>
                <search-select
                    class="l-pad-m--bottom"
                    v-model:selected="resources"
                    :endpoint="getResourceSearchEndpoint()"
                    label="Resources"
                    label-id="resources"
                    placeholder=""
                >
                </search-select>
                <input type="hidden" name="resourceIds" :value="compileResourceIds" />
            </section>
            <ul class="flex-list buttons">
                <li>
                    <input
                        type="submit"
                        :disabled="!isFormSubmittable"
                        value="Submit"
                        data-test="create-booking-modal-add-button"
                    />
                </li>
            </ul>
        </form>
    </reveal-modal>
</template>
