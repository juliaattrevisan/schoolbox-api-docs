<script setup lang="ts">
import { ENDPOINTS } from "@schoolboxCalendarEvent/endpoints";
import DateRange from "@schoolboxVue/components/dateTimePicker/date-range.vue";
import TextEditor from "@schoolboxVue/components/ckeditor/text-editor.vue";
import { EFFICACY_SELF, EFFICACY_SERIES } from "@schoolboxCalendarEvent/eventTypes";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { Booking } from "./resourceBookingTypes";

interface Props {
    eventId: number;
    eventSeriesId: number | null;
    booking: Booking;
    canModify: boolean;
}
const props = defineProps<Props>();

const emit = defineEmits<{
    bookingDeleted: [bookingId: number];
}>();

/**
 * Request format expected by the server at ENDPOINTS.editBookingPath().
 */
type EditBookingApiRequest = {
    startDatetime: string;
    endDatetime: string;
    notes: string;
    efficacy: string; // Efficacy will be a blank string if the event is not part of a series.
};

/**
 * Success response format expected from the server at ENDPOINTS.editBookingPath().
 * For error response formats, see apiUtils.ts.
 */
type EditBookingApiResponse = { nRowsAffected: number };

/**
 * This defines the server response to a request to ENDPOINTS.deleteBookingPath().
 */
type DeleteBookingApiResponse = { success: boolean; nRowsAffected: number; errorMessage?: string };

const isEditFormOpen = window.Vue.ref<boolean>(false);
const isProcessing = window.Vue.ref<boolean>(false);
const efficacy = window.Vue.ref<string>("");
const isFormSubmittable = window.Vue.computed(() => {
    return !isProcessing.value && (!props.eventSeriesId || efficacy.value !== "");
});
const createEditBookingFormEl = window.Vue.useTemplateRef<HTMLFormElement>("editBookingForm");
const bookingNotes = window.Vue.ref<string>(props.booking.notes);
const startDate = window.Vue.ref<string>(props.booking.start);
const endDate = window.Vue.ref<string>(props.booking.end);

/**
 * Open or close the 'edit booking' drawer.
 */
function toggleEditForm(): void {
    isEditFormOpen.value = !isEditFormOpen.value;
}

/**
 * Cancel any edits on the booking form and close the form.
 */
function cancelEditBookingForm(): void {
    // Reset form values to original prop values.
    bookingNotes.value = props.booking.notes;
    startDate.value = props.booking.start;
    endDate.value = props.booking.end;
    // Close form.
    toggleEditForm();
}

/**
 * Post the 'Edit Booking' form.
 */
async function postEditBookingForm(): Promise<void> {
    if (!createEditBookingFormEl.value) {
        throw new Error("Missing createEditBookingFormEl."); // Shouldn't get here.
    }
    isProcessing.value = true;

    const toast = window.sbProps.toastActivate("Saving...");
    const response = await apiRequest<EditBookingApiResponse, EditBookingApiRequest>({
        method: "POST",
        url: ENDPOINTS.editBookingPath(props.eventId, props.booking.id),
        body: {
            startDatetime: startDate.value,
            endDatetime: endDate.value,
            notes: bookingNotes.value,
            efficacy: efficacy.value,
        },
    });

    isProcessing.value = false;

    let bookingsPlural = "(s)";
    if (response.data && typeof response.data === "object" && "nRowsAffected" in response.data) {
        bookingsPlural = response.data.nRowsAffected === 1 ? "" : "s";
    }

    if (!response.success) {
        console.info("resource-booking-row.vue postEditBookingForm() response: ", response);
        toast.timeout({ text: response.message || `Unable to update booking${bookingsPlural}`, isSuccess: false });
        return;
    }

    toast.timeout({ text: `Booking${bookingsPlural} updated`, isSuccess: true });
    isEditFormOpen.value = false; // Close edit form only on success.
}

/**
 * Post the 'Delete Booking' form.
 */
async function postDeleteBookingForm(): Promise<void> {
    isProcessing.value = true;
    const payload = {
        efficacy: efficacy.value,
    };
    const toast = window.sbProps.toastActivate("Deleting...");
    const endpoint = ENDPOINTS.deleteBookingPath(props.eventId, props.booking.id);

    try {
        const response = await fetch(endpoint, {
            method: "DELETE",
            body: JSON.stringify(payload),
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
        });
        const responseData = (await response.json()) as DeleteBookingApiResponse;
        if (response.ok && responseData.success) {
            emit("bookingDeleted", props.booking.id); // Tell the parent component that this component has been deleted.
            toast.timeout({
                text: `Booking${responseData.nRowsAffected > 1 ? "s" : ""} deleted`,
                isSuccess: true,
            });
            isEditFormOpen.value = false; // Close edit form only on success.
        } else {
            toast.timeout({
                text: responseData.errorMessage
                    ? responseData.errorMessage
                    : `Unable to delete booking${responseData.nRowsAffected > 1 ? "s" : ""}`,
                isSuccess: false,
            });
        }
    } catch (error) {
        toast.timeout({ text: "Unable to delete booking(s)", isSuccess: false });
        console.warn(error);
    } finally {
        isProcessing.value = false;
    }
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
                >{{ props.booking.status.name }}</span
            >
            <a
                v-if="canModify"
                href="#"
                class="icon-modify"
                title="Modify Booking"
                aria-label="Modify Booking"
                @click.prevent="toggleEditForm"
            ></a>
        </div>
    </div>
    <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
        <form
            v-if="isEditFormOpen"
            ref="editBookingForm"
            method="post"
            :action="ENDPOINTS.editBookingPath(props.eventId, props.booking.id)"
            @submit.prevent="postEditBookingForm"
        >
            <input type="hidden" name="bookingId" :value="props.booking.id" />
            <date-range
                ref="dateRangeRef"
                v-model:start-date="startDate"
                v-model:end-date="endDate"
                id-prefix="resource-booking-row"
                :use-time="true"
                :label-start="'From'"
                :label-end="'Until'"
            />
            <div>
                <label :for="`resource-booking-row-textarea-${props.booking.id}`">Notes</label>
                <text-editor :id="`resource-booking-row-textarea-${props.booking.id}`" v-model:body="bookingNotes" />
            </div>
            <template v-if="eventSeriesId">
                <hr />
                <h3>This event is part of a series</h3>
                <p>Would you like to affect only this event, or all in the series?</p>
                <div class="radiolist l-grid l-gap-l l-grid--2-col">
                    <input
                        :id="`radioEfficacySelf-${props.booking.id}`"
                        v-model="efficacy"
                        type="radio"
                        :value="EFFICACY_SELF"
                    />
                    <label :for="`radioEfficacySelf-${props.booking.id}`">Only this event</label>
                    <input
                        :id="`radioEfficacySeries-${props.booking.id}`"
                        v-model="efficacy"
                        type="radio"
                        :value="EFFICACY_SERIES"
                    />
                    <label :for="`radioEfficacySeries-${props.booking.id}`">All events in series</label>
                </div>
            </template>
            <div class="component-action">
                <section>
                    <button @click.prevent="cancelEditBookingForm">Cancel</button>
                    <button :disabled="!isFormSubmittable" @click.prevent="postEditBookingForm">Save</button>
                    <button :disabled="!isFormSubmittable" @click.prevent="postDeleteBookingForm">Delete</button>
                </section>
            </div>
        </form>
    </Transition>
</template>
