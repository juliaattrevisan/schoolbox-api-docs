<script setup lang="ts">
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import { apiRequest } from "@js/util/sbx/apiUtils";
import type { RegistrationSession } from "../../js/types/ibRegistrationTypes";

interface Props {
    createEndpoint: string;
    systemPurposeId: number;
}
const props = defineProps<Props>();

const emit = defineEmits<{
    sessionCreated: [session: RegistrationSession];
}>();

const isOpen = defineModel<boolean>("is-open", { default: false });

const academicYear = window.Vue.ref<string>("");
const examSession = window.Vue.ref<"May" | "November" | null>(null);
const academicYearError = window.Vue.ref<string | null>(null);
const examSessionError = window.Vue.ref<string | null>(null);
const isSubmitting = window.Vue.ref<boolean>(false);
const submitError = window.Vue.ref<string | null>(null);

/** Resets form fields and validation errors to their initial state. */
function resetForm(): void {
    academicYear.value = "";
    examSession.value = null;
    academicYearError.value = null;
    examSessionError.value = null;
    isSubmitting.value = false;
    submitError.value = null;
}

window.Vue.watch(isOpen, (newVal) => {
    if (!newVal) {
        resetForm();
    }
});

/** Validates the form and POSTs the new session to the backend. */
async function onSubmit(): Promise<void> {
    academicYearError.value = null;
    examSessionError.value = null;
    submitError.value = null;

    let isValid = true;

    if (academicYear.value.trim() === "") {
        academicYearError.value = "Please enter an academic year.";
        isValid = false;
    } else if (academicYear.value.trim().length > 4) {
        academicYearError.value = "Academic year must be 4 characters or fewer, for example 2026.";
        isValid = false;
    }

    if (examSession.value === null) {
        examSessionError.value = "Please select an examination session.";
        isValid = false;
    }

    if (!isValid) {
        return;
    }

    isSubmitting.value = true;

    const response = await apiRequest<RegistrationSession, Record<string, unknown>>({
        url: props.createEndpoint,
        method: "POST",
        body: {
            systemPurposeId: props.systemPurposeId,
            academicYear: academicYear.value.trim(),
            examSession: examSession.value,
        },
    });

    isSubmitting.value = false;

    if (!response.success) {
        const backendMessage = (response.data as Record<string, unknown>)?.message;
        submitError.value =
            typeof backendMessage === "string" ? backendMessage : "An error occurred. Please try again.";
        return;
    }

    emit("sessionCreated", response.data);
    isOpen.value = false;
}
</script>

<template>
    <BasicModal v-model:is-open="isOpen" :has-background-click="true" size="s">
        <form class="l-flex-col l-gap-m f-no-margins" @submit.prevent="onSubmit">
            <h2>Create New Examination Session</h2>
            <p>Configuring registration for Diploma Programme Year 2 [DIPLOMA]</p>

            <div class="alert-box" role="alert">
                If incorrect students appear on the following report, please update the students' qualification.
            </div>

            <div v-if="submitError" class="sbx-alert sbx-alert--error" role="alert">
                {{ submitError }}
            </div>

            <div class="l-flex-col l-gap-xs">
                <label for="academic-year" class="required">Academic Year</label>
                <input
                    id="academic-year"
                    v-model="academicYear"
                    type="text"
                    name="academic-year"
                    placeholder="e.g. 2026"
                    maxlength="4"
                />
                <label v-if="academicYearError" class="error">{{ academicYearError }}</label>
            </div>

            <fieldset class="radiolist clear-fieldset">
                <legend class="required">Examination Session</legend>
                <ul class="option-list l-flex-row l-gap-s f-no-margins">
                    <li>
                        <input
                            id="may"
                            v-model="examSession"
                            type="radio"
                            name="examination-session"
                            value="May"
                        /><label for="may">May</label>
                    </li>
                    <li>
                        <input
                            id="november"
                            v-model="examSession"
                            type="radio"
                            name="examination-session"
                            value="November"
                        /><label for="november">November</label>
                    </li>
                </ul>
                <label v-if="examSessionError" class="error">{{ examSessionError }}</label>
            </fieldset>

            <div class="l-flex l-gap-xs f-no-margins">
                <button type="button" class="button l-flex__item--1" @click.prevent="isOpen = false">Cancel</button>
                <button
                    class="button submit l-flex__item--1"
                    :disabled="isSubmitting"
                    data-test="ib-registration-create-session-submit"
                >
                    {{ isSubmitting ? "Creating..." : "Create Session" }}
                </button>
            </div>
        </form>
    </BasicModal>
</template>
