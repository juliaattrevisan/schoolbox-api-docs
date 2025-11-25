<script setup lang="ts">
/**
 * Custom Participation Vue Component
 */
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import CpParticipant from "./cp-participant.vue";
import CpBottomDrawer from "./cp-bottom-drawer.vue";
import CpIncludeInExistingModal from "./cp-include-in-existing-modal.vue";
import CpConfirmModifyModal from "./cp-confirm-modify-modal.vue";
import type { CustomisedPlan, Participant, Plan, Routes } from "../js/types/customParticipationTypes";
import { useGetStudentPastoralCounts } from "./composable/useGetStudentPastoralCounts";
import { useExcludeParticipant } from "./composable/useExcludeParticipant";
import { useIncludeParticipant } from "./composable/useIncludeParticipant";
import { useIncludeInExisting } from "./composable/useIncludeInExisting";

const props = defineProps<{
    allParticipants: Participant[];
    pastoralEnabled: boolean;
    routes: Routes;
    assessmentId: number;
    customisations: (Plan | CustomisedPlan)[];
}>();

const allParticipants = window.Vue.ref<Participant[]>(props.allParticipants);

// #region checkboxes and selection

const showCheckboxes = window.Vue.computed(() => allParticipants.value.length > 1); // show the checkboxes if there are more than one participant
const selectedList = window.Vue.ref<Participant[]>([]); // currently selected participants (check boxes in the table)

/**
 * Check if all participants are selected
 */
const allSelected = window.Vue.computed({
    get: () => allParticipants.value.every((participant) => selectedList.value.includes(participant)),
    set: (value) => {
        selectedList.value = value ? allParticipants.value : [];
    },
});

/**
 * Function to check if the selection is indeterminate (some but not all selected)
 * to set the checkbox state appropriately
 */
const isIndeterminate = window.Vue.computed(() => {
    return selectedList.value.length > 0 && selectedList.value.length < allParticipants.value.length;
});

// #endregion

// #region pastoral counts

const { loadError, studentPastoralCounts } = useGetStudentPastoralCounts(
    props.pastoralEnabled,
    props.routes.getStudentsPastoralCountsUrl,
    () => allParticipants.value,
);

// #endregion

// #region exclude/include participants

const { excludeParticipants } = useExcludeParticipant(loadError);
const { includeParticipants } = useIncludeParticipant(loadError);

// #endregion

// #region include in existing customisation

const {
    isIncludeInExistingOpen,
    includeInExistingList,
    includeInExistingActivityId,
    isSingleIncludeInExisting,
    includeParticipantInExistingPlan,
    openIncludeInExistingModal,
} = useIncludeInExisting(loadError, props.customisations, props.routes.includeInExistingCustomisation, selectedList);

// #endregion

// #region perform modification and check for interactions prior to action

const isConfirmModifyOpen = window.Vue.ref(false);
const modifyAction = window.Vue.ref<string>("");
const modifyList = window.Vue.ref<Participant[]>([]); // array of participants to modify
const isSingleModify = window.Vue.ref(false); // whether the current modification is for a single participant (three-dot) or multiple (bottom drawer)
const participantsWithInteractions = window.Vue.ref<Participant[]>([]); // participants with interactions in the current modification list

/**
 * Performs the action for participants without interactions,
 * or after confirmation when selection contains participants with interactions.
 */
async function performAction(): Promise<void> {
    switch (modifyAction.value) {
        case "removeCustomisation":
            await includeParticipants(modifyList.value);
            break;
        case "exclude":
            await excludeParticipants(modifyList.value, selectedList);
            break;
        case "includeInExisting":
            await includeParticipantInExistingPlan();
            break;
        default:
            // redirect to the appropriate URL
            window.location.href = modifyAction.value;
    }
    if (!isSingleModify.value) {
        // only clear the selection if this is a multi-selection action
        selectedList.value = [];
    }
    modifyList.value = [];
    isConfirmModifyOpen.value = false; // close the confirmation modal
}

/**
 * Handles the modification of a selected participant(s).
 * If the participant(s) has interactions, it opens a confirmation modal.
 * Otherwise, the appropriate action is taken directly.
 * @param participants - The participants to modify.
 * @param action - the action to perform (e.g., 'remove').
 * @param $isSingleModify - whether this action is for a single participant or multiple
 */
function onActionSelected(participants: Participant[], action: string, $isSingleModify: boolean): void {
    modifyList.value = participants;
    modifyAction.value = action;
    isSingleModify.value = $isSingleModify;
    // if any participant has interactions, show the confirmation modal
    if (modifyList.value.some((participant) => participant.hasInteraction)) {
        participantsWithInteractions.value = modifyList.value.filter((participant) => participant.hasInteraction);
        isConfirmModifyOpen.value = true;
        return;
    }
    // otherwise, perform the action directly
    void performAction();
}

/**
 * Handles the confirmation of an action in the confirmation modal,
 * closes the modal after performing the action.
 */
async function onConfirmActionSelected(): Promise<void> {
    await performAction();
}

// #endregion
</script>

<template>
    <div v-if="loadError" class="c-island l-pad-m" data-test="custom-participation-load-error">
        <data-load-error data-title="Student Participation" :further-info="loadError" />
    </div>
    <div>
        <div class="scrollable">
            <table>
                <thead>
                    <tr>
                        <th v-if="showCheckboxes" width="40" data-test="custom-participation-select-all">
                            <input
                                id="select-all"
                                v-model="allSelected"
                                type="checkbox"
                                :class="{ indeterminate: isIndeterminate }"
                            />
                            <label for="select-all" class="checkbox-label"></label>
                        </th>
                        <td width="84"></td>
                        <th>Name</th>
                        <th>Customisation</th>
                        <td></td>
                    </tr>
                </thead>
                <tbody>
                    <cp-participant
                        v-for="participant in allParticipants"
                        :key="participant.id"
                        v-model:selected-list="selectedList"
                        :participant="participant"
                        :show-checkboxes="showCheckboxes"
                        :student-pastoral-counts="studentPastoralCounts"
                        :routes="props.routes"
                        :assessment-id="props.assessmentId"
                        :customisations="props.customisations"
                        @action-selected="onActionSelected"
                        @open-include-in-existing-modal="openIncludeInExistingModal"
                    />
                </tbody>
            </table>
        </div>
        <!-- container for the sbx-pop-out to be displayed in -->
        <div id="pop-out-container"></div>
        <cp-bottom-drawer
            v-model:selected-list="selectedList"
            :routes="routes"
            :assessment-id="props.assessmentId"
            :customisations="props.customisations"
            @action-selected="onActionSelected"
            @open-include-in-existing-modal="openIncludeInExistingModal"
        />
    </div>
    <cp-include-in-existing-modal
        v-model:is-open="isIncludeInExistingOpen"
        v-model:selected-activity-id="includeInExistingActivityId"
        :customisations="props.customisations"
        :participant-list="includeInExistingList"
        :is-single="isSingleIncludeInExisting"
        @confirm="onActionSelected"
    />
    <cp-confirm-modify-modal
        v-model:is-open="isConfirmModifyOpen"
        :participants-with-interactions="participantsWithInteractions"
        @confirm="onConfirmActionSelected"
    />
</template>
