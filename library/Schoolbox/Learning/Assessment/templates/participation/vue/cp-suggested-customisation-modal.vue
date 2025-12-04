<script setup lang="ts">
/**
 * To generate and display a suggested customisation,
 * choose what type of customisation to apply and
 * notify if existing customisation or feedback already exists.
 */

import locale from "@js/terminology/rosettaStone";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import CpGenAi from "./cp-gen-ai.vue";
import type { AIResponse, Routes } from "../js/types/customParticipationTypes";

const props = defineProps<{
    routes: Routes;
    assessmentId: number;
}>();

const studentId = defineModel<number | null>("student-id", { default: null });

const isOpen = window.Vue.ref(false);
const newLoad = window.Vue.ref(true); // to indicate whether to load new AI content, as we may be returning from another status without changing student
const aiResponse = window.Vue.ref<AIResponse | null>(null); // to store the generated AI response, which is set within the cp-gen-ai component
const status = window.Vue.ref<"view" | "selectType">("view"); // to track the current status of the modal
const typePicked = window.Vue.ref("modify"); // to track the type of customisation picked
const proceedText = window.Vue.ref("Proceed");

/**
 * To display the view/generate customisation modal when a student is selected
 */
window.Vue.watch(studentId, (newStudentId) => {
    if (newStudentId) {
        newLoad.value = true;
        isOpen.value = true;
    }
});

/**
 * When the modal is closed trigger to clear the studentId
 */
window.Vue.watch(isOpen, (newIsOpen) => {
    if (!newIsOpen) {
        studentId.value = null;
        newLoad.value = true;
        status.value = "view";
    }
});

/**
 * To move to the next step of customising the activity
 */
function onCustomiseActivity(): void {
    status.value = "selectType";
}

/**
 * Close the modal, resetting state to cancel any operation
 */
function onCancel(): void {
    isOpen.value = false;
    studentId.value = null;
}

/**
 * To go back to viewing the suggested customisation
 */
function onBack(): void {
    newLoad.value = false; // to prevent reloading AI content when the cp-gen-ai component is mounted again
    status.value = "view";
}

/**
 * To use the suggested AI content and navigate to the appropriate activity page
 */
function onConfirm(): void {
    proceedText.value = "Proceeding...";
    if (typePicked.value === "modify") {
        window.location.href = `${props.routes.getLearningPlanNewUrl}?student=${studentId.value}&${getReturnUrl()}`;
    } else if (typePicked.value === "copy") {
        window.location.href = `${props.routes.getAssessmentParticipationCopyActivity}?student=${studentId.value}&original=${props.assessmentId}&${getReturnUrl()}`;
    } else if (typePicked.value === "new") {
        window.location.href = `${props.routes.getAssessmentParticipationNewActivity}?student=${studentId.value}&original=${props.assessmentId}&${getReturnUrl()}`;
    }
}
</script>

<template>
    <basic-modal v-model:is-open="isOpen">
        <cp-gen-ai
            v-if="status === 'view'"
            v-model:ai-response="aiResponse"
            :route="props.routes.genAI"
            :assessment-id="props.assessmentId"
            :student-id="studentId"
            :new-load="newLoad"
            @cancel="onCancel"
            @customise-activity="onCustomiseActivity"
        />
        <form v-else-if="status === 'selectType'" class="l-flex-col l-gap-m f-no-margins" @submit.prevent="onConfirm">
            <div class="l-flex-row l-flex--justify-space l-gap-s f-no-margins">
                <h2>Select Customisation</h2>
            </div>
            <div>
                <ul class="option-list">
                    <li>
                        <input id="cp-modify" v-model="typePicked" type="radio" value="modify" />
                        <label for="cp-modify" class="icon-modify f-no-icon-color">Modify</label>
                    </li>
                    <li>
                        <input id="cp-copy" v-model="typePicked" type="radio" value="copy" />
                        <label for="cp-copy" class="icon-copy f-no-icon-color"
                            >Copy {{ locale.activity.translate(false, true) }}</label
                        >
                    </li>
                    <li>
                        <input id="cp-new" v-model="typePicked" type="radio" value="new" />
                        <label for="cp-new" class="icon-submissions f-no-icon-color"
                            >New {{ locale.activity.translate(false, true) }}</label
                        >
                    </li>
                </ul>
            </div>
            <div class="l-flex l-gap-xs f-no-margins">
                <button class="button l-flex__item--1" data-test="" @click.prevent="onBack">Back</button>
                <button class="button submit l-flex__item--1" type="button" data-test="" @click.prevent="onConfirm">
                    {{ proceedText }}
                </button>
            </div>
        </form>
    </basic-modal>
</template>

<style scoped></style>
