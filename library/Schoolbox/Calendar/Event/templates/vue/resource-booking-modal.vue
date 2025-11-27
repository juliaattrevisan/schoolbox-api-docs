<script>
/* eslint-disable eslint-comments/disable-enable-pair -- see below */
/* eslint-disable eslint-comments/no-unlimited-disable -- see below */
/* eslint-disable -- Ignore this file for ESLint checks for now (SA-18376) */
/* eslint-enable vue/no-v-html */
import RevealModal from "@schoolboxVue/components/reveal-modal";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import Toast from "@schoolboxVue/components/toast.vue";
import { ENDPOINTS } from "@schoolboxCalendarEvent/endpoints";
import { injectScript, isScriptAdded } from "@js/util/domUtils";
import { EditorEvent } from "@js/editor/event/editorEvent";
import { EditorToolbar } from "@js/editor/types/editorTypes";

export default {
    name: "resource-booking-modal",

    components: {
        RevealModal,
        SearchSelect,
        Toast,
    },

    props: ["bookingCreateUrl", "formatFormDatetime", "formatDisplayDatetime"],

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
            return this.booking.name && this.booking.name.length !== 0 && !this.isProcessing;
        },

        /**
         * The booking's date, as submitted via the form.
         *
         * @return {string}
         */
        formDate() {
            return this.booking.date ? dayjs(this.booking.date).format(this.formatFormDatetime) : "";
        },

        /**
         * The booking's date, as displayed on the modal.
         *
         * @return {string}
         */
        displayDate() {
            return this.booking.date ? dayjs(this.booking.date).format(this.formatDisplayDatetime) : "";
        },

        /**
         * Compile object ids into csv
         */
        compileAttendeeIds() {
            let stack = [];

            for (let i = 0; i < this.selected.length; i++) {
                stack.push(this.selected[i].id);
            }

            return stack.join(",");
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
        /**
         * Get the endpoint for the booking resource search.
         * @returns {String} The endpoint for the booking resource search.
         */
        getBookingEndpoint() {
            const searchUrl = new URL(this.ENDPOINTS.URL_BOOKING_SELECTIZE, window.location.href).toString();
            return `${searchUrl}?`;
        },

        /**
         * Open this modal.
         */
        open(booking) {
            this.booking = booking;
            this.$refs.modal.open();
            this.initCKE();
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
            formContent.set("detail", window.sbProps.editors["detail"].getData());
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
                    // check if errors exist?
                    if (responseData.errors && responseData.errors.length > 0) {
                        this.$refs.toast.timeout(
                            "Booking created with some issues: " + responseData.errors.join(", "),
                            false,
                            4000,
                        );
                    } else {
                        this.$refs.toast.timeout("Created", true, 2000);
                    }
                    // TODO: Refresh page here.
                    console.info("SUCCESS! TODO: Refresh the page.");
                } else {
                    this.$refs.toast.timeout("Unable to create booking. " + responseData.message, false, 3000);
                }
            } catch (error) {
                this.$refs.toast.timeout("Unable to create event / task. " + error.message, false, 3000);
                console.warn(error);
            } finally {
                this.isProcessing = false;
            }
        },

        /**
         * Initialise CKEditor
         */
        async initCKE() {
            const editorScript = `${window.cdnPrefix}/js/bundle/editor.js?v=${window.siteVersion}`;
            if (!isScriptAdded(editorScript)) {
                await injectScript(editorScript);
            }

            // If an editor already exists, just clear its content and avoid creating another.
            const detailEditor = window.sbProps.editors["detail"];
            if (detailEditor) {
                detailEditor.setData("");
                return;
            }

            // Otherwise set an editor up.
            document.dispatchEvent(
                new CustomEvent(EditorEvent.EDITOR__CREATE, {
                    detail: {
                        targetElements: ["#notes"],
                        toolbar: EditorToolbar.SIMPLE,
                        removeFromToolbar: ["link"],
                    },
                }),
            );
        },
    },
};
</script>

<template>
    <reveal-modal ref="modal">
        <form
            ref="createEventForm"
            method="post"
            :action="bookingCreateUrl"
            @submit.prevent="createNewBooking($refs.createEventForm)"
            class="top"
        >
            <h2>Add Resource Booking</h2>
            <section>
                <label class="required" for="addBookingPurpose">Purpose</label>
                <input id="addBookingPurpose" name="name" maxlength="255" type="text" v-model.trim="booking.name" />
                <search-select
                    class="l-pad-m--bottom"
                    v-model:selected="resources"
                    :endpoint="getBookingEndpoint()"
                    label="Resources"
                    label-id="resources"
                    placeholder=""
                >
                </search-select>
                <input type="hidden" name="resources" :value="compileResourceIds" />
                <label class="notes">
                    Notes
                    <textarea id="notes" name="notes"></textarea>
                </label>
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
