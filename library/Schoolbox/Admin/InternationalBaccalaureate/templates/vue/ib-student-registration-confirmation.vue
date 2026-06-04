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
    return window.dayjs(isoString).format(window.dateDisplayFormats.dayjsDatetime);
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
    <div class="ib-registration-section-confirm">
        <label class="ib-registration-section-confirm__label">
            <input
                v-model="confirmed"
                type="checkbox"
                :disabled="isSaving || isSubmitted"
                @change="toggle(confirmed)"
            />
            {{ labelText }}
        </label>
        <p v-if="confirmedBy && confirmedAt" class="ib-registration-section-confirm__meta">
            Last updated by {{ confirmedBy }} on {{ formatDate(confirmedAt) }}
        </p>
    </div>
</template>

<style scoped>
.ib-registration-section-confirm {
    margin-top: 0.75rem;
}

.ib-registration-section-confirm__label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: bold;
    cursor: pointer;
}

.ib-registration-section-confirm__label input[type="checkbox"]:disabled {
    cursor: not-allowed;
}

.ib-registration-section-confirm__meta {
    margin: 0.25rem 0 0 1.5rem;
    font-size: 0.85rem;
    color: #666;
}
</style>
