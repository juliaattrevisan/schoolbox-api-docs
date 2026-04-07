<script setup lang="ts">
/**
 * Custom Participation Bottom Drawer Component
 */

import locale from "@js/terminology/rosettaStone";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer.vue";
import type { CustomisedPlan, Participant, Plan, Routes } from "../js/types/customParticipationTypes";

const props = defineProps<{
    routes: Routes;
    assessmentId: number;
    customisations: (Plan | CustomisedPlan)[];
}>();

const selectedList = defineModel<Participant[]>("selectedList", { default: [] });

const emit = defineEmits<{
    (event: "actionSelected", participants: Participant[], action: string, isSingle: boolean): void;
    (event: "openIncludeInExistingModal", isSingle: boolean, participant: Participant | null): void;
}>();

/**
 * Check if any selected participants has a customisation,
 * whether can show the "Remove Customisation" action or not
 */
const hasCustomisedInSelection = window.Vue.computed(() => {
    return selectedList.value.some(
        (participant) =>
            participant.excludedAt !== null || participant.plan.name !== null || participant.customisedPlan !== null,
    );
});

/**
 * Handle action selection events
 * @param participants - The participants to perform the action on
 * @param action - The action to perform
 */
function handleActionSelected(participants: Participant[], action: string): void {
    emit("actionSelected", participants, action, false);
}

/**
 * Handle include in existing modal
 */
function handleOpenIncludeInExistingModal(): void {
    emit("openIncludeInExistingModal", false, null);
}

/**
 * Clear the selection
 */
function clearSelection(): void {
    selectedList.value = [];
}
</script>

<template>
    <bottom-drawer data-test="custom-participation-bottom-drawer" :show-drawer="selectedList.length > 0">
        <h3 class="selected-count">
            <em>{{ selectedList.length }} selected</em>
        </h3>
        <button class="button-clear" @click.prevent="clearSelection">Clear selection</button>
        <div class="l-flex-row l-flex--justify l-gap-xs">
            <template v-if="hasCustomisedInSelection">
                <button
                    class="icon-close button l-flex__item--1-auto"
                    data-test="custom-participation-remove-customisation"
                    @click.prevent="handleActionSelected(selectedList, 'removeCustomisation')"
                >
                    Remove Customisation
                </button>
            </template>
            <template v-else>
                <button
                    class="button l-flex__item--1-auto"
                    @click.prevent="
                        handleActionSelected(
                            selectedList,
                            `${props.routes.getLearningPlanNewUrl}?student=${selectedList.map((p) => p.user.id).join(',')}&${getReturnUrl()}`,
                        )
                    "
                >
                    <sbx-icon name="modify"></sbx-icon>
                    Modify Existing {{ locale.activity.translate(false, true) }}
                </button>
                <button
                    class="icon-staff-students button l-flex__item--1-auto"
                    @click.prevent="handleActionSelected(selectedList, 'exclude')"
                >
                    Exclude {{ selectedList.length === 1 ? "Student" : "Students" }}
                </button>
                <button
                    v-if="props.customisations.length > 0"
                    class="button l-flex__item--1-auto"
                    @click.prevent="handleOpenIncludeInExistingModal"
                >
                    <sbx-icon name="list"></sbx-icon>
                    Include in Existing Customisation
                </button>
                <button
                    v-if="props.routes.getAssessmentParticipationCopyActivity"
                    class="button l-flex__item--1-auto"
                    data-test="custom-participation-copy-activity"
                    @click.prevent="
                        handleActionSelected(
                            selectedList,
                            `${props.routes.getAssessmentParticipationCopyActivity}?student=${selectedList.map((p) => p.user.id).join(',')}&original=${props.assessmentId}&${getReturnUrl()}`,
                        )
                    "
                >
                    <sbx-icon name="copy"></sbx-icon>
                    Create Copy of {{ locale.activity.translate(false, true) }}
                </button>
                <button
                    class="icon-submissions button l-flex__item--1-auto"
                    @click.prevent="
                        handleActionSelected(
                            selectedList,
                            `${props.routes.getAssessmentParticipationNewActivity}?student=${selectedList.map((p) => p.user.id).join(',')}&original=${props.assessmentId}&${getReturnUrl()}`,
                        )
                    "
                >
                    Create New {{ locale.activity.translate(false, true) }}
                </button>
            </template>
        </div>
    </bottom-drawer>
</template>

<style scoped>
.selected-count {
    display: inline-block;
    margin-right: 0.5rem;
}

.button-clear {
    background: none;
    display: inline-block;
    font-size: 0.8rem;
    padding: 0px;
    vertical-align: baseline;
}

@media (max-width: 768px) {
    .flex-container {
        flex-direction: column;
    }

    .l-flex__item--1-auto {
        flex: 1 1 100%;
    }
}
</style>
