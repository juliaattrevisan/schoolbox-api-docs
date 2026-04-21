<script setup lang="ts">
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type { RegistrationSession } from "../../js/types/ibRegistrationTypes";

// TODO(SA-22225): Remove createEndpoint prop and restore API call when backend is ready.
interface Props {
    createEndpoint: string;
}
defineProps<Props>();

const emit = defineEmits<{
    sessionCreated: [session: RegistrationSession];
}>();

const isOpen = defineModel<boolean>("is-open", { default: false });

const academicYear = window.Vue.ref<string>("");
const examinationSession = window.Vue.ref<"May" | "November" | null>(null);
const academicYearError = window.Vue.ref<string | null>(null);
const examinationSessionError = window.Vue.ref<string | null>(null);

/**
 * Resets form fields and validation errors to their initial state.
 */
function resetForm(): void {
    academicYear.value = "";
    examinationSession.value = null;
    academicYearError.value = null;
    examinationSessionError.value = null;
}

window.Vue.watch(isOpen, (newVal) => {
    if (!newVal) {
        resetForm();
    }
});

/**
 * Creates a temporary local session from form data.
 * TODO(SA-22225): Replace with real POST to createEndpoint when backend is ready.
 */
function onSubmit(): void {
    academicYearError.value = null;
    examinationSessionError.value = null;

    let isValid = true;

    if (academicYear.value.trim() === "") {
        academicYearError.value = "Please enter an academic year.";
        isValid = false;
    }

    if (examinationSession.value === null) {
        examinationSessionError.value = "Please select an examination session.";
        isValid = false;
    }

    if (!isValid) {
        return;
    }

    const session: RegistrationSession = {
        id: Date.now(),
        title: `Diploma Program Year 2 [DIPLOMA] - ${examinationSession.value} ${academicYear.value.trim()}`,
        qualificationType: "DIPLOMA",
        academicYear: academicYear.value.trim(),
        examSession: examinationSession.value as "May" | "November",
        createdAt: new Date().toISOString(),
        createdBy: "current user", // TODO(SA-22225): Replace with actual user from backend response
    };

    emit("sessionCreated", session);
    isOpen.value = false;
}
</script>

<template>
    <BasicModal v-model:is-open="isOpen" :has-background-click="true" size="s">
        <form class="l-flex-col l-gap-m f-no-margins" @submit.prevent="onSubmit">
            <h2>Create New Examination Session</h2>
            <p>Configuring registration for Diploma Program Year 2 [DIPLOMA]</p>

            <div class="alert-box" role="alert">
                If incorrect students appear on the following report, please update the students' qualification.
            </div>

            <div class="l-flex-col l-gap-xs">
                <label for="academic-year" class="required">Academic Year</label>
                <input
                    id="academic-year"
                    v-model="academicYear"
                    type="text"
                    name="academic-year"
                    placeholder="e.g. 2026 or 2025/2026"
                />
                <label v-if="academicYearError" class="error">{{ academicYearError }}</label>
            </div>

            <fieldset class="radiolist clear-fieldset">
                <legend class="required">Examination Session</legend>
                <ul class="option-list l-flex-row l-gap-s f-no-margins">
                    <li>
                        <input
                            id="may"
                            v-model="examinationSession"
                            type="radio"
                            name="examination-session"
                            value="May"
                        /><label for="may">May</label>
                    </li>
                    <li>
                        <input
                            id="november"
                            v-model="examinationSession"
                            type="radio"
                            name="examination-session"
                            value="November"
                        /><label for="november">November</label>
                    </li>
                </ul>
                <label v-if="examinationSessionError" class="error">{{ examinationSessionError }}</label>
            </fieldset>

            <div class="l-flex l-gap-xs f-no-margins">
                <button type="button" class="button l-flex__item--1" @click.prevent="isOpen = false">Cancel</button>
                <button class="button submit l-flex__item--1" data-test="ib-registration-create-session-submit">
                    Create Session
                </button>
            </div>
        </form>
    </BasicModal>
</template>
