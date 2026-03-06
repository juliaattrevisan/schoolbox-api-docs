<script lang="ts" setup>
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { ENDPOINTS } from "@schoolboxCalendarEvent/endpoints";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type { Toast } from "@schoolboxVue/components/toast/sbxToastTypes";
import type { Booking, Resource } from "./resourceBookingTypes";
import { EFFICACY_SELF, EFFICACY_SERIES } from "./eventTypes";

// TODO: Lots of items in this file need renaming.
//  * function 'createNewBooking' should be 'postAddBookingsForm'
//  * 'booking' should be 'bookings' in lots of places
//  * 'create' bookings should be 'add' bookings for consistency
//  * compare with resource-booking-row.vue.

interface Props {
    resourceBookings: Booking[];
    eventId: number;
    eventSeriesId: number | null;
}

const props = defineProps<Props>();

/**
 * This defines the server response to an ENDPOINTS.addBookings request.
 */
type AddBookingsToEventResponse = {
    message: string;
    isSuccess: boolean;
};

const isModalOpen = window.Vue.ref<boolean>(false);
const isProcessing = window.Vue.ref<boolean>(false);
const resources = window.Vue.ref<Resource[]>([]);
const efficacy = window.Vue.ref<string>("");
const isFormSubmittable = window.Vue.computed(() => {
    return !isProcessing.value && resources.value.length > 0 && (!props.eventSeriesId || efficacy.value !== "");
});
const createBookingFormEl = window.Vue.useTemplateRef<HTMLFormElement>("createBookingForm");

/**
 * Compile object ids into csv.
 * @returns a comma-separated string of resourceIds.
 */
const compileResourceIds = window.Vue.computed(() =>
    resources.value.map((resource: Resource) => resource.id).join(","),
);

/**
 * Get the endpoint for the booking resource search.
 * @returns The endpoint for the booking resource search.
 */
function getResourceSearchEndpoint(): string {
    const searchUrl = new URL(ENDPOINTS.URL_BOOKING_SELECTIZE, window.location.origin);
    const excludeResourceIds = props.resourceBookings.map((booking) => booking.resource.id);
    searchUrl.searchParams.append("excludeResourceIds", excludeResourceIds.join(","));
    // The trailing '&' on the URL below is necessary because the SearchSelect component will add a 'query=' suffix.
    return `${searchUrl.toString()}&`;
}

/**
 * Open this modal.
 */
function open(): void {
    isModalOpen.value = true;
    resources.value = [];
    efficacy.value = "";
}

// Expose functions for parent components
defineExpose({
    open,
});

/**
 * Post the booking creation form.
 */
async function createNewBooking(): Promise<void> {
    if (!createBookingFormEl.value) {
        throw new Error("Missing createBookingFormEl."); // Shouldn't get here.
    }
    isProcessing.value = true;
    const formContent = new FormData(createBookingFormEl.value);
    const toast = window.sbProps.toastActivate("Saving...");

    try {
        const response = await fetch(createBookingFormEl.value.action, {
            method: createBookingFormEl.value.method,
            body: formContent,
            headers: {
                Accept: "application/json",
            },
        });
        const responseData = (await response.json()) as AddBookingsToEventResponse;
        if (response.ok) {
            toast.timeout({
                text: responseData.message,
                isSuccess: responseData.isSuccess,
                //                timeoutCallback: () => {
                //                    // Reload the page after the timeout expires.
                //                    window.location.reload();
                //                },
            });
            isModalOpen.value = false; // Close the modal only on success.
        } else {
            toast.timeout({ text: "Unable to create booking(s)", isSuccess: false });
        }
    } catch (error) {
        toast.timeout({ text: "Unable to create booking(s)", isSuccess: false });
        console.warn(error);
    } finally {
        isProcessing.value = false;
    }
}
</script>

<template>
    <basic-modal v-model:is-open="isModalOpen" :has-background-click="true">
        <form
            ref="createBookingForm"
            method="post"
            :action="ENDPOINTS.addBookingsPath(eventId)"
            class="top"
            @submit.prevent="createNewBooking()"
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
            <template v-if="eventSeriesId">
                <hr />
                <h3>This event is part of a series</h3>
                <p>Would you like to affect only this event, or all in the series?</p>
                <div class="radiolist l-grid l-gap-l l-grid--2-col">
                    <input
                        id="radioEfficacySelf"
                        v-model="efficacy"
                        type="radio"
                        name="efficacy"
                        :value="EFFICACY_SELF"
                    />
                    <label for="radioEfficacySelf">Only this event</label>
                    <input
                        id="radioEfficacySeries"
                        v-model="efficacy"
                        type="radio"
                        name="efficacy"
                        :value="EFFICACY_SERIES"
                    />
                    <label for="radioEfficacySeries">All events in series</label>
                </div>
                <hr />
            </template>
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
    </basic-modal>
</template>
