<script setup lang="ts">
/**
 * To select the type of customisation to apply for a participant
 * when acting on the suggested customisation
 */

import locale from "@js/terminology/rosettaStone";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import RequestError from "@schoolboxVue/components/error/request-error.vue";
import type { Participant, Routes } from "../js/types/customParticipationTypes";
import { useIncludeParticipant } from "./composable/useIncludeParticipant";
import { hasCustomisation } from "../js/util/participantUtils";

const props = defineProps<{
    routes: Routes;
    assessmentId: number;
    participant: Participant | null;
}>();

const emit = defineEmits<{
    (event: "back"): void;
}>();

type CustomisationType = "modify" | "copy" | "new";

/**
 * Choose the first available customisation type for the initial radio selection.
 * @returns The first available customisation type.
 */
function getDefaultType(): CustomisationType {
    if (props.routes.getLearningPlanNewUrl) return "modify";
    if (props.routes.getAssessmentParticipationCopyActivity) return "copy";
    return "new";
}

const typePicked = window.Vue.ref<CustomisationType>(getDefaultType()); // to track the type of customisation picked
const proceedText = window.Vue.ref("Proceed");
const loadError = window.Vue.ref<string | null>(null);
const { includeParticipants } = useIncludeParticipant(loadError);

/**
 * To go back to viewing the suggested customisation
 */
function onBack(): void {
    emit("back");
}

/**
 * To use the suggested AI content and navigate to the appropriate activity page
 */
async function onConfirm(): Promise<void> {
    proceedText.value = "Proceeding...";
    if (props.participant && hasCustomisation(props.participant)) {
        await includeParticipants([props.participant]);
    }
    const studentId = props.participant?.user.id ?? null;
    if (typePicked.value === "modify" && props.routes.getLearningPlanNewUrl) {
        window.location.href = `${props.routes.getLearningPlanNewUrl}?student=${studentId}&${getReturnUrl()}`;
    } else if (typePicked.value === "copy" && props.routes.getAssessmentParticipationCopyActivity) {
        window.location.href = `${props.routes.getAssessmentParticipationCopyActivity}?student=${studentId}&original=${props.assessmentId}&${getReturnUrl()}`;
    } else if (typePicked.value === "new" && props.routes.getAssessmentParticipationNewActivity) {
        window.location.href = `${props.routes.getAssessmentParticipationNewActivity}?student=${studentId}&original=${props.assessmentId}&${getReturnUrl()}`;
    }
}
</script>

<template>
    <form class="u-flex-col u-gap-m f-no-margins" @submit.prevent="onConfirm">
        <div class="u-flex-row u-flex--justify-space u-gap-s f-no-margins">
            <h2>Select Customisation</h2>
        </div>
        <fieldset class="checklist u-no-border u-pad--0 f-no-margins">
            <legend class="u-visually-hidden">Choose the type of customisation to apply:</legend>
            <ul class="option-list">
                <li v-if="props.routes.getLearningPlanNewUrl">
                    <input id="cp-modify" v-model="typePicked" type="radio" value="modify" />
                    <label for="cp-modify">
                        <span class="icon-modify f-no-icon-color">
                            Modify Existing {{ locale.activity.translate(false, true) }}
                        </span>
                    </label>
                </li>
                <li v-if="props.routes.getAssessmentParticipationCopyActivity">
                    <input id="cp-copy" v-model="typePicked" type="radio" value="copy" />
                    <label for="cp-copy">
                        <span class="icon-copy f-no-icon-color">
                            Create Copy of {{ locale.activity.translate(false, true) }}
                        </span>
                    </label>
                </li>
                <li v-if="props.routes.getAssessmentParticipationNewActivity">
                    <input id="cp-new" v-model="typePicked" type="radio" value="new" />
                    <label for="cp-new">
                        <span class="icon-submissions f-no-icon-color">
                            Create New {{ locale.activity.translate(false, true) }}
                        </span>
                    </label>
                </li>
            </ul>
        </fieldset>
        <div v-if="loadError">
            <request-error :further-info="loadError" />
        </div>
        <div class="u-flex u-gap-xs f-no-margins">
            <button class="button u-flex__item--1" data-test="" @click.prevent="onBack">Back</button>
            <button class="button submit u-flex__item--1" type="button" data-test="" @click.prevent="onConfirm">
                {{ proceedText }}
            </button>
        </div>
    </form>
</template>

<style scoped></style>
