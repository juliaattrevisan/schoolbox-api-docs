<script setup lang="ts">
import type { Toast } from "@schoolboxVue/components/toast/sbxToastTypes";
import { ENDPOINTS } from "@schoolboxCalendarEvent/endpoints";
import DateRange from "@schoolboxVue/components/dateTimePicker/date-range.vue";
import type { Booking } from "./resourceBookingTypes";

interface Props {
    eventId: number;
    eventSeriesId: number | null;
    booking: Booking;
    canModify: boolean;
}
const props = defineProps<Props>();

/**
 * This defines the server response to a request to ENDPOINTS.editBookingPath().
 */
type EditBookingResponse = { success: boolean };

const isEditFormOpen = window.Vue.ref<boolean>(false);
const isProcessing = window.Vue.ref<boolean>(false);
const efficacy = window.Vue.ref<string>("");
const isFormSubmittable = window.Vue.computed(() => {
    return !isProcessing.value && (!props.eventSeriesId || efficacy.value !== "");
});
const createEditBookingFormEl = window.Vue.useTemplateRef<HTMLFormElement>("editBookingForm");

/**
 * Open this modal.
 * @param bookingId The id of the booking.
 */
function open(bookingId: number): void {
    // TODO: Open the modal matching this id somehow.
    isEditFormOpen.value = true;
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
function updateToastAndRefreshPage(toast: Toast, responseData: EditBookingResponse): void {
    const isSuccess = true;
    toast.timeout({
        text: "SUCCESS",
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
async function postEditBookingForm(): Promise<void> {
    if (!createEditBookingFormEl.value) {
        throw new Error("Missing createEditBookingFormEl."); // Shouldn't get here.
    }
    isProcessing.value = true;
    const formContent = new FormData(createEditBookingFormEl.value);
    const toast = window.sbProps.toastActivate("Saving...");

    try {
        const response = await fetch(createEditBookingFormEl.value.action, {
            method: createEditBookingFormEl.value.method,
            body: formContent,
            headers: {
                Accept: "application/json",
            },
        });
        const responseData = (await response.json()) as EditBookingResponse;
        if (response.ok) {
            updateToastAndRefreshPage(toast, responseData);
        } else {
            toast.timeout({ text: "Unable to create booking(s).", isSuccess: false });
            isEditFormOpen.value = false; // Close the modal after an error.
        }
    } catch (error) {
        const message = error instanceof Error ? error.message : String(error);
        toast.timeout({ text: `Unable to create booking(s). ${message}`, isSuccess: false });
        console.warn(error);
        isEditFormOpen.value = false; // Close the modal after an error.
    } finally {
        isProcessing.value = false;
    }
}

/**
 *
 */
function toggleEditForm(): void {
    isEditFormOpen.value = !isEditFormOpen.value;
}
</script>

<template>
    <div class="l-grid l-gap-l l-grid--2-col l-mar-m--bottom">
        <div>
            <!-- Intentionally display the resource name here, not the booking name -->
            {{ props.booking.resource.name }}
        </div>
        <div class="text-right">
            <span
                class="label"
                :class="{
                    pending: props.booking.status.isPending,
                    approved: props.booking.status.isApproved,
                    rejected: props.booking.status.isRejected,
                }"
                >{{ booking.status.name }}</span
            >
            <a
                v-if="canModify"
                href="#"
                class="icon-modify"
                title="Modify Booking"
                aria-label="Modify Booking"
                @click.prevent="toggleEditForm()"
            ></a>
        </div>
    </div>
    <div v-if="isEditFormOpen">
        <form
            ref="editBookingForm"
            method="post"
            :action="ENDPOINTS.editBookingPath(props.eventId, props.booking.id)"
            class="top"
            @submit.prevent="postEditBookingForm()"
        >
            <date-range ref="dateRangeRef" id-prefix="resource-booking-row" :use-time="true" />
            <div class="component-action">
                <section>
                    <button>Cancel</button>
                    <button :disabled="!isFormSubmittable">Save</button>
                    <button>Delete</button>
                </section>
            </div>
        </form>
    </div>
</template>

<style scoped></style>
