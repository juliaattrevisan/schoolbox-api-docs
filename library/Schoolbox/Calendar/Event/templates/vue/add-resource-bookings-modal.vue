<script lang="ts">
import { defineComponent } from "vue";
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import Toast from "@schoolboxVue/components/toast.vue";
import { ENDPOINTS } from "@schoolboxCalendarEvent/endpoints";

interface Resource {
    id: string | number;
    [key: string]: unknown;
}

interface BookingResource {
    id: number;
    [key: string]: unknown;
}

interface Booking {
    resource: BookingResource;
    [key: string]: unknown;
}

interface ToastComponent {
    activate: (message: string) => void;
    timeout: (message: string, success: boolean, duration: number) => void;
}

interface ModalComponent {
    open: () => void;
    close: () => void;
}

interface BookingResponse {
    errors?: string[];
    [key: string]: unknown;
}

export default defineComponent({
    name: "AddResourceBookingsModal",

    components: {
        RevealModal,
        SearchSelect,
        Toast,
    },

    props: {
        // This component should only be included if the resource bookings module is enabled.
        // So resourceBookings should always be set.
        resourceBookings: {
            type: Array,
            required: true,
        },
    },

    data(): { isProcessing: boolean; resources: Resource[] } {
        return {
            isProcessing: false, // To disable buttons on form submission to prevent rapid fire.
            resources: [],
        };
    },

    computed: {
        window: (): Window => window,

        /**
         * Can the booking form be submitted?
         * @returns whether the form is submittable.
         */
        isFormSubmittable(): boolean {
            return !this.isProcessing && this.resources.length > 0;
        },

        /**
         * Compile object ids into csv
         * @returns a comma-separated string of resourceIds.
         */
        compileResourceIds(): string {
            return this.resources.map((resource) => resource.id).join(",");
        },

        toast(): ToastComponent {
            return this.$refs.toast as ToastComponent;
        },

        modal(): ModalComponent {
            return this.$refs.modal as ModalComponent;
        },
    },

    methods: {
        getAddBookingsEndpoint(): string {
            return new URL(ENDPOINTS.URL_BOOKINGS_ADD as string, window.location.href).toString();
        },

        /**
         * Get the endpoint for the booking resource search.
         * @returns The endpoint for the booking resource search.
         */
        getResourceSearchEndpoint(): string {
            const searchUrl = new URL(ENDPOINTS.URL_BOOKING_SELECTIZE as string, window.location.href).toString();
            const excludeResourceIds = (this.resourceBookings as Booking[]).map((booking) => booking.resource.id);
            return `${searchUrl}?excludeResourceIds=${encodeURIComponent(excludeResourceIds.join(","))}&`;
        },

        /**
         * Open this modal.
         */
        open(): void {
            this.modal.open();
            this.resources = [];
        },

        /**
         * Post the booking creation form.
         * @param form The form that's being submitted
         */
        async createNewBooking(form: HTMLFormElement): Promise<void> {
            this.isProcessing = true;
            const formContent = new FormData(form);
            this.toast.activate("Saving...");

            try {
                const response = await fetch(form.action, {
                    method: form.method,
                    body: formContent,
                    headers: {
                        Accept: "application/json",
                    },
                });
                const responseData = (await response.json()) as BookingResponse;
                if (response.ok) {
                    this.modal.close();
                    if (responseData.errors && responseData.errors.length > 0) {
                        this.toast.timeout(
                            `Booking(s) created with some issues: ${responseData.errors.join(", ")}`,
                            false,
                            4000,
                        );
                    } else {
                        this.toast.timeout("Booking(s) created", true, 2000);
                    }
                    // The new booking was successfully added, so reload the page to make it appear.
                    window.location.reload();
                } else {
                    this.toast.timeout("Unable to create booking(s).", false, 3000);
                }
            } catch (error) {
                this.toast.timeout(
                    `Unable to create booking(s). ${error.message}`,
                    false,
                    3000,
                );
                console.warn(error);
            } finally {
                this.isProcessing = false;
            }
        },
    },
});
</script>

<template>
    <toast ref="toast"></toast>
    <reveal-modal ref="modal">
        <form
            ref="createBookingForm"
            method="post"
            :action="getAddBookingsEndpoint()"
            class="top"
            @submit.prevent="createNewBooking($refs.createBookingForm)"
        >
            <h2>Add Resource Bookings</h2>
            <section>
                <search-select
                    v-model:selected="resources"
                    class="l-pad-m--bottom"
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
