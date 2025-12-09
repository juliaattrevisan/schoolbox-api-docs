<script setup lang="ts">
/**
 *
 */

import locale from "@js/terminology/rosettaStone";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import type { Participant, Routes } from "../js/types/customParticipationTypes";

const props = defineProps<{
    routes: Routes;
    assessmentId: number;
    participant: Participant | null;
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
    const studentId = props.participant?.user.id ?? null;
    if (typePicked.value === "modify") {
        window.location.href = `${props.routes.getLearningPlanNewUrl}?student=${studentId}&${getReturnUrl()}`;
    } else if (typePicked.value === "copy") {
        window.location.href = `${props.routes.getAssessmentParticipationCopyActivity}?student=${studentId}&original=${props.assessmentId}&${getReturnUrl()}`;
    } else if (typePicked.value === "new") {
        window.location.href = `${props.routes.getAssessmentParticipationNewActivity}?student=${studentId}&original=${props.assessmentId}&${getReturnUrl()}`;
    }
}
</script>

<template>
    <form class="l-flex-col l-gap-m f-no-margins" @submit.prevent="onConfirm">
        <div class="l-flex-row l-flex--justify-space l-gap-s f-no-margins">
            <h2>Select Customisation</h2>
        </div>
        <div>
            <fieldset class="checklist f-no-margins">
                <legend class="t-visually-hidden">Choose the type of customisation to apply:</legend>
                <ul class="option-list">
                    <li>
                        <input id="cp-modify" v-model="typePicked" type="radio" value="modify" />
                        <label for="cp-modify">
                            <span class="inline-icon"><sbx-icon name="modify" size="0.75rem" /></span>
                            <span>Modify</span>
                        </label>
                    </li>
                    <li>
                        <input id="cp-copy" v-model="typePicked" type="radio" value="copy" />
                        <label for="cp-copy">
                            <span class="inline-icon"><sbx-icon name="copy" size="0.75rem" /></span>
                            <span>Copy {{ locale.activity.translate(false, true) }}</span>
                        </label>
                    </li>
                    <li>
                        <input id="cp-new" v-model="typePicked" type="radio" value="new" />
                        <label for="cp-new">
                            <span class="inline-icon"><sbx-icon name="submissions" size="0.75rem" /></span>
                            <span>New {{ locale.activity.translate(false, true) }}</span>
                        </label>
                    </li>
                </ul>
            </fieldset>
        </div>
        <div class="l-flex l-gap-xs f-no-margins">
            <button class="button l-flex__item--1" data-test="" @click.prevent="onBack">Back</button>
            <button class="button submit l-flex__item--1" type="button" data-test="" @click.prevent="onConfirm">
                {{ proceedText }}
            </button>
        </div>
    </form>
</template>

<style scoped>
.inline-icon {
    display: inline-block;
    margin-right: var(--sbx-size-xs, 0.25rem);
}
</style>
