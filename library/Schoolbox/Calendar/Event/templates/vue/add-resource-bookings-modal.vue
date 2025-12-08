<script lang="ts" setup>
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { ENDPOINTS } from "@schoolboxCalendarEvent/endpoints";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type { Toast } from "@schoolboxVue/components/toast/sbxToastTypes";
import type { Booking, Resource } from "./resourceBookingTypes";
import { EFFICACY_SELF, EFFICACY_SERIES } from "./eventTypes";

interface Props {
    resourceBookings: Booking[];
    eventId: number;
    eventSeriesId: number | null;
}

const props = defineProps<Props>();

/**
 * This defines the server response to an ENDPOINTS.URL_BOOKINGS_ADD request.
 */
type AddBookingsToEventResponse = {
    nBookingsAttempted: number;
    nBookingsCreatedWithClash: number;
    nBookingsCreatedWithoutClash: number;
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
 * @returns a URL.
 */
function getAddBookingsEndpoint(): string {
    return new URL(ENDPOINTS.URL_BOOKINGS_ADD, window.location.href).toString();
}

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
 * @param toast The toast to update
 * @param responseData The server's response to an 'addBookings' request.
 */
function updateToastAndRefreshPage(toast: Toast, responseData: AddBookingsToEventResponse): void {
    const nBookingsCreated = responseData.nBookingsCreatedWithoutClash + responseData.nBookingsCreatedWithClash;
    const nBookingsNotCreated = responseData.nBookingsAttempted - nBookingsCreated;

    let msg;
    let isSuccess;

    switch (nBookingsCreated) {
        case responseData.nBookingsAttempted:
            isSuccess = true;
            msg =
                responseData.nBookingsCreatedWithClash === 0
                    ? "Created all bookings and found no clashes"
                    : `Created all bookings (${responseData.nBookingsCreatedWithClash} as pending because of clash${
                          responseData.nBookingsCreatedWithClash > 1 ? "es" : ""
                      })`;
            break;
        case 0:
            isSuccess = false;
            msg = `Could not create any bookings because of clashes`;
            break;
        default:
            isSuccess = true;
            msg = `Created some bookings (${nBookingsNotCreated} not created because of clash${
                nBookingsNotCreated > 1 ? "es" : ""
            })`;
    }

    toast.timeout({
        text: msg,
        isSuccess,
        duration: 2000,
        fadeDuration: 1000,
        timeoutCallback: () => {
            // Reload the page after the timeout expires.
            window.location.reload();
        },
    });
}

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
            updateToastAndRefreshPage(toast, responseData);
        } else {
            toast.timeout({ text: "Unable to create booking(s).", isSuccess: false });
            isModalOpen.value = false; // Close the modal after an error.
        }
    } catch (error) {
        const message = error instanceof Error ? error.message : String(error);
        toast.timeout({ text: `Unable to create booking(s). ${message}`, isSuccess: false });
        console.warn(error);
        isModalOpen.value = false; // Close the modal after an error.
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
            :action="getAddBookingsEndpoint()"
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
