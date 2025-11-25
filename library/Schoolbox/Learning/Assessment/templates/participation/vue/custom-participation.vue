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
import { usePerformAction } from "./composable/usePerformAction";

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

const { isConfirmModifyOpen, participantsWithInteractions, onActionSelected, onConfirmActionSelected } =
    usePerformAction(selectedList, excludeParticipants, includeParticipants, includeParticipantInExistingPlan);

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
