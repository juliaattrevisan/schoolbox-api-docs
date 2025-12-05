<script setup lang="ts">
/**
 *
 */

import locale from "@js/terminology/rosettaStone";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import type { Routes } from "../js/types/customParticipationTypes";

const props = defineProps<{
    routes: Routes;
    assessmentId: number;
    studentId: number | null;
}>();

const emit = defineEmits<{
    (event: "back"): void;
}>();

const typePicked = window.Vue.ref("modify"); // to track the type of customisation picked
const proceedText = window.Vue.ref("Proceed");

/**
 * To go back to viewing the suggested customisation
 */
function onBack(): void {
    emit("back");
}

/**
 * To use the suggested AI content and navigate to the appropriate activity page
 */
function onConfirm(): void {
    proceedText.value = "Proceeding...";
    if (typePicked.value === "modify") {
        window.location.href = `${props.routes.getLearningPlanNewUrl}?student=${props.studentId}&${getReturnUrl()}`;
    } else if (typePicked.value === "copy") {
        window.location.href = `${props.routes.getAssessmentParticipationCopyActivity}?student=${props.studentId}&original=${props.assessmentId}&${getReturnUrl()}`;
    } else if (typePicked.value === "new") {
        window.location.href = `${props.routes.getAssessmentParticipationNewActivity}?student=${props.studentId}&original=${props.assessmentId}&${getReturnUrl()}`;
    }
}
</script>

<template>
    <form class="l-flex-col l-gap-m f-no-margins" @submit.prevent="onConfirm">
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
</template>

<style scoped></style>
