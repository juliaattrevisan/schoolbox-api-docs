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
    quantity: number;
    efficacy: string; // Efficacy will be a blank string if the event is not part of a series.
};

/**
 * Success response format expected from the server at ENDPOINTS.editBookingPath().
 * For error response formats, see apiUtils.ts.
 */
type EditBookingApiResponse = { nRowsAffected: number };

/**
 * Request format expected by the server at ENDPOINTS.deleteBookingPath().
 */
type DeleteBookingApiRequest = {
    efficacy: string; // Efficacy will be a blank string if the event is not part of a series.
};

/**
 * Success response format expected from the server at ENDPOINTS.deleteBookingPath().
 * For error response formats, see apiUtils.ts.
 */
type DeleteBookingApiResponse = { nRowsAffected: number };

const isEditFormOpen = window.Vue.ref<boolean>(false);
const isProcessing = window.Vue.ref<boolean>(false);
const efficacy = window.Vue.ref<string>("");
const bookingNotes = window.Vue.ref<string>(props.booking.notes);
const startDate = window.Vue.ref<string>(props.booking.start);
const endDate = window.Vue.ref<string>(props.booking.end);
const bookingQuantity = window.Vue.ref<number>(props.booking.quantity);
const showQuantityInput = window.Vue.computed(() => props.booking.resource.capacity > 1 || props.booking.quantity > 1);
const quantityMax = window.Vue.computed(() => Math.max(props.booking.resource.capacity, props.booking.quantity));
const isQuantityValid = window.Vue.computed(() => {
    return (
        !showQuantityInput.value ||
        (Number.isInteger(bookingQuantity.value) &&
            bookingQuantity.value >= 1 &&
            bookingQuantity.value <= quantityMax.value)
    );
});
const isFormSubmittable = window.Vue.computed(() => {
    return !isProcessing.value && isQuantityValid.value && (!props.eventSeriesId || efficacy.value !== "");
});

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
    bookingQuantity.value = props.booking.quantity;
    // Close form.
    toggleEditForm();
}

/**
 * Post the 'Edit Booking' form.
 */
async function postEditBookingForm(): Promise<void> {
    if (!isFormSubmittable.value) {
        return;
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
            quantity: bookingQuantity.value,
            efficacy: efficacy.value,
        },
    });

    isProcessing.value = false;

    // Multiple bookings may have been affected if we are dealing with an event series.
    let bookingsPlural = "(s)";
    if (response.data && typeof response.data === "object" && "nRowsAffected" in response.data) {
        bookingsPlural = response.data.nRowsAffected === 1 ? "" : "s";
    }

    if (!response.success) {
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

    const toast = window.sbProps.toastActivate("Deleting...");
    const response = await apiRequest<DeleteBookingApiResponse, DeleteBookingApiRequest>({
        method: "DELETE",
        url: ENDPOINTS.deleteBookingPath(props.eventId, props.booking.id),
        body: {
            efficacy: efficacy.value,
        },
    });

    isProcessing.value = false;

    // Multiple bookings may have been affected if we are dealing with an event series.
    let bookingsPlural = "(s)";
    if (response.data && typeof response.data === "object" && "nRowsAffected" in response.data) {
        bookingsPlural = response.data.nRowsAffected === 1 ? "" : "s";
    }

    if (!response.success) {
        toast.timeout({ text: response.message || `Unable to delete booking${bookingsPlural}`, isSuccess: false });
        return;
    }

    emit("bookingDeleted", props.booking.id); // Tell the parent component that this component has been deleted.
    toast.timeout({ text: `Booking${bookingsPlural} deleted`, isSuccess: true });
    isEditFormOpen.value = false; // Close edit form only on success.
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
                title="Modify Booking"
                aria-label="Modify Booking"
                class="sbx-icon-only-button"
                data-test="resource-booking-row-modify-button"
                @click.prevent="toggleEditForm"
                ><sbx-icon name="modify"></sbx-icon
            ></a>
        </div>
    </div>
    <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
        <form v-if="isEditFormOpen" @submit.prevent="postEditBookingForm">
            <input type="hidden" name="bookingId" :value="props.booking.id" />
            <date-range
                ref="dateRangeRef"
                v-model:start-date="startDate"
                v-model:end-date="endDate"
                id-prefix="resource-booking-row"
                :use-time="true"
                label-start="From"
                label-end="Until"
            />
            <div v-if="showQuantityInput">
                <label :for="`resource-booking-row-quantity-${props.booking.id}`">Quantity</label>
                <input
                    :id="`resource-booking-row-quantity-${props.booking.id}`"
                    v-model.number="bookingQuantity"
                    name="quantity"
                    type="number"
                    min="1"
                    :max="quantityMax"
                    step="1"
                    required
                    data-test="resource-booking-row-quantity-input"
                />
            </div>
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
                    <button
                        :disabled="!isFormSubmittable"
                        data-test="resource-booking-row-save-button"
                        @click.prevent="postEditBookingForm"
                    >
                        Save
                    </button>
                    <button :disabled="!isFormSubmittable" @click.prevent="postDeleteBookingForm">Delete</button>
                </section>
            </div>
        </form>
    </Transition>
</template>
