<script setup lang="ts">
import { apiRequest } from "@js/util/sbx/apiUtils";

interface Props {
    updateConfirmationEndpoint: string;
    initialCandidateConfirmed: boolean;
    initialSubjectConfirmed: boolean;
    initialCandidateConfirmedBy: string;
    initialCandidateConfirmedAt: string;
    initialSubjectConfirmedBy: string;
    initialSubjectConfirmedAt: string;
    initialConfirmationStatus: string;
}

type UpdateConfirmationResponse = {
    success: boolean;
    confirmationStatus: string;
    updatedAt: string;
    updatedBy: { name: string };
    message?: string;
};

const props = defineProps<Props>();

const candidateConfirmed = window.Vue.ref<boolean>(props.initialCandidateConfirmed);
const subjectConfirmed = window.Vue.ref<boolean>(props.initialSubjectConfirmed);
const candidateConfirmedBy = window.Vue.ref<string>(props.initialCandidateConfirmedBy);
const candidateConfirmedAt = window.Vue.ref<string>(props.initialCandidateConfirmedAt);
const subjectConfirmedBy = window.Vue.ref<string>(props.initialSubjectConfirmedBy);
const subjectConfirmedAt = window.Vue.ref<string>(props.initialSubjectConfirmedAt);
const confirmationStatus = window.Vue.ref<string>(props.initialConfirmationStatus);
const isSaving = window.Vue.ref<boolean>(false);

const isLocked = window.Vue.computed(() => confirmationStatus.value === "confirmed");

/**
 * Sends a PUT request to toggle the confirmed state of the given section.
 * Reverts the checkbox on failure and shows a toast error.
 * @param section - The section to toggle ("candidate" or "subject")
 * @param value - The new confirmed state
 */
async function toggleSection(section: "candidate" | "subject", value: boolean): Promise<void> {
    isSaving.value = true;
    const toast = window.sbProps.toastActivate("Saving...");

    try {
        const response = (await apiRequest<UpdateConfirmationResponse>({
            url: props.updateConfirmationEndpoint,
            method: "PUT",
            body: { section, confirmed: value },
        })) as UpdateConfirmationResponse;

        if (!response.success) {
            toast.timeout({ text: response.message ?? "Failed to save confirmation.", isSuccess: false });
            if (section === "candidate") {
                candidateConfirmed.value = !value;
            } else {
                subjectConfirmed.value = !value;
            }
            return;
        }

        confirmationStatus.value = response.confirmationStatus;

        if (section === "candidate") {
            candidateConfirmedBy.value = response.updatedBy.name;
            candidateConfirmedAt.value = response.updatedAt;
        } else {
            subjectConfirmedBy.value = response.updatedBy.name;
            subjectConfirmedAt.value = response.updatedAt;
        }
        toast.timeout({ text: "Confirmation saved." });
    } catch {
        toast.timeout({ text: "Failed to save confirmation. Please try again.", isSuccess: false });
        if (section === "candidate") {
            candidateConfirmed.value = !value;
        } else {
            subjectConfirmed.value = !value;
        }
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <div class="ib-registration-confirmation">
        <div class="ib-registration-confirmation__section">
            <label class="ib-registration-confirmation__label">
                <input
                    v-model="candidateConfirmed"
                    type="checkbox"
                    :disabled="isLocked || isSaving"
                    @change="toggleSection('candidate', candidateConfirmed)"
                />
                I confirm my candidate details are correct.
            </label>
            <p v-if="candidateConfirmedBy && candidateConfirmedAt" class="ib-registration-confirmation__meta">
                Last updated by {{ candidateConfirmedBy }} on {{ candidateConfirmedAt }}
            </p>
        </div>

        <div class="ib-registration-confirmation__section">
            <label class="ib-registration-confirmation__label">
                <input
                    v-model="subjectConfirmed"
                    type="checkbox"
                    :disabled="isLocked || isSaving"
                    @change="toggleSection('subject', subjectConfirmed)"
                />
                I confirm my subject details are correct.
            </label>
            <p v-if="subjectConfirmedBy && subjectConfirmedAt" class="ib-registration-confirmation__meta">
                Last updated by {{ subjectConfirmedBy }} on {{ subjectConfirmedAt }}
            </p>
        </div>
    </div>
</template>

<style scoped>
.ib-registration-confirmation {
    margin-top: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.ib-registration-confirmation__label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: bold;
    cursor: pointer;
}

.ib-registration-confirmation__label input[type="checkbox"]:disabled {
    cursor: not-allowed;
}

.ib-registration-confirmation__meta {
    margin: 0.25rem 0 0 1.5rem;
    font-size: 0.85rem;
    color: #666;
}
</style>
