<script setup lang="ts">
import type { Toast } from "@schoolboxVue/components/toast/sbxToastTypes";
import { ENDPOINTS } from "@schoolboxCalendarEvent/endpoints";
import DateRange from "@schoolboxVue/components/dateTimePicker/date-range.vue";
import TextEditor from "@schoolboxVue/components/ckeditor/text-editor.vue";
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
const bookingNotes = window.Vue.ref<string>(props.booking.notes);
const startDate = window.Vue.ref<string>(props.booking.start);
const endDate = window.Vue.ref<string>(props.booking.end);

/**
 *
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
 * Post the booking creation form.
 */
async function postEditBookingForm(): Promise<void> {
    if (!createEditBookingFormEl.value) {
        throw new Error("Missing createEditBookingFormEl."); // Shouldn't get here.
    }
    isProcessing.value = true;
    const payload = {
        startDatetime: startDate.value,
        endDatetime: endDate.value,
        notes: bookingNotes.value,
    };
    const toast = window.sbProps.toastActivate("Saving...");

    try {
        const response = await fetch(createEditBookingFormEl.value.action, {
            method: createEditBookingFormEl.value.method,
            body: JSON.stringify(payload),
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
        });
        const responseData = (await response.json()) as EditBookingResponse;
        if (response.ok) {
            toast.timeout({ text: "Booking updated", isSuccess: true });
            isEditFormOpen.value = false; // Close the modal after a successful edit.
        } else {
            toast.timeout({ text: "Unable to update booking", isSuccess: false });
            isEditFormOpen.value = false; // Close the modal after an unsuccessful edit.
        }
    } catch (error) {
        const message = error instanceof Error ? error.message : String(error);
        toast.timeout({ text: `Unable to update booking ${message}`, isSuccess: false });
        console.warn(error);
        isEditFormOpen.value = false; // Close the modal after an error.
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
                <label for="resource-booking-row-textarea">Notes</label>
                <text-editor id="resource-booking-row-textarea" v-model:body="bookingNotes" />
            </div>
            <div class="component-action">
                <section>
                    <button @click.prevent="cancelEditBookingForm()">Cancel</button>
                    <button :disabled="!isFormSubmittable" @click.prevent="postEditBookingForm()">Save</button>
                    <button>Delete</button>
                </section>
            </div>
        </form>
    </div>
</template>

<style scoped></style>
