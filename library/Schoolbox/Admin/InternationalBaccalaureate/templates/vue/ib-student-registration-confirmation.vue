<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";

interface Props {
    section: "candidate" | "subject";
    updateConfirmationEndpoint: string;
    initialConfirmed: boolean;
    initialConfirmedBy: string;
    initialConfirmedAt: string;
    initialConfirmationStatus: string;
}

const props = defineProps<Props>();

const confirmed = window.Vue.ref<boolean>(props.initialConfirmed);
const confirmedBy = window.Vue.ref<string>(props.initialConfirmedBy);
const confirmedAt = window.Vue.ref<string>(props.initialConfirmedAt);
const isSaving = window.Vue.ref<boolean>(false);
const isSubmitted = window.Vue.ref<boolean>(props.initialConfirmationStatus === "submitted");

/**
 * Format an ISO 8601 date string using the site's configured datetime display format.
 * @param isoString - The ISO 8601 date string to format.
 * @returns The formatted date string.
 */
function formatDate(isoString: string): string {
    const d = window.dayjs(isoString);
    return `${d.format(window.dateDisplayFormats.dayjsDateShort)} ${d.format(window.dateDisplayFormats.dayjsTime)}`;
}

const labelText =
    props.section === "candidate"
        ? "I confirm my candidate details are correct."
        : "I confirm my subject details are correct.";

/**
 * Send the confirmation toggle to the backend and update local state.
 * @param value The new confirmed value.
 */
async function toggle(value: boolean): Promise<void> {
    isSaving.value = true;
    const toast = window.sbProps.toastActivate("Saving...");

    try {
        type ConfirmResponse = {
            confirmationStatus: string;
            updatedAt: string;
            updatedBy: { name: string };
            message?: string;
        };
        const raw = await apiRequest<ConfirmResponse, { section: string; confirmed: boolean }>({
            url: props.updateConfirmationEndpoint,
            method: "PUT",
            body: { section: props.section, confirmed: value },
        });

        if (!raw.success) {
            confirmed.value = !value;
            toast.timeout({ text: raw.message ?? "Failed to save confirmation.", isSuccess: false });
            return;
        }

        const data = raw.data;
        confirmedBy.value = data.updatedBy.name;
        confirmedAt.value = data.updatedAt;

        toast.timeout({ text: "Confirmation saved." });
    } catch {
        confirmed.value = !value;
        toast.timeout({ text: "Failed to save confirmation. Please try again.", isSuccess: false });
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <div class="small-12 u-mar-s--top">
        <div class="checklist sbx-checkbox-radio-container">
            <input
                :id="`confirmation-${props.section}`"
                v-model="confirmed"
                type="checkbox"
                :disabled="isSaving || isSubmitted"
                @change="toggle(confirmed)"
            />
            <label :for="`confirmation-${props.section}`">{{ labelText }}</label>
        </div>
        <p v-if="confirmedBy && confirmedAt" class="meta">
            Last updated by {{ confirmedBy }} on {{ formatDate(confirmedAt) }}
        </p>
    </div>
</template>

<style scoped></style>
