<script setup lang="ts">
/**
 * Display a list of students linked to an assessment and provide details and actions for student participation
 */

import locale from "@js/terminology/rosettaStone";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import { useSelectedList } from "@schoolboxVue/composables/form/useSelectedList";
import type { User } from "@schoolboxLibrary/types/User";
import CpParticipant from "./cp-participant.vue";
import CpBottomDrawer from "./cp-bottom-drawer.vue";
import CpViewDpModal from "./cp-view-dp-modal.vue";
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
    differentiationEnabled: boolean;
    routes: Routes;
    assessmentId: number;
    customisations: (Plan | CustomisedPlan)[];
}>();

/**
 * Handle selection of participants via checkboxes
 */
const allParticipants = window.Vue.ref<Participant[]>(props.allParticipants);
const { showCheckboxes, selectedList, allSelected, isIndeterminate } = useSelectedList(allParticipants);

/**
 * Get student pastoral counts
 */
const { loadError, studentPastoralCounts } = useGetStudentPastoralCounts(
    props.pastoralEnabled,
    props.routes.getStudentsPastoralCountsUrl,
    () => allParticipants.value,
);

/**
 * Exclude and include participants handlers
 */
const { excludeParticipants } = useExcludeParticipant(loadError);
const { includeParticipants } = useIncludeParticipant(loadError);

/**
 * To include participants in existing customisation plans
 */
const {
    isIncludeInExistingOpen,
    includeInExistingList,
    includeInExistingActivityId,
    isSingleIncludeInExisting,
    includeParticipantInExistingPlan,
    openIncludeInExistingModal,
} = useIncludeInExisting(loadError, props.customisations, props.routes.includeInExistingCustomisation, selectedList);

/**
 * To confirm action if any participants have submission/feedback
 */
const { isConfirmModifyOpen, participantsWithInteractions, onActionSelected, onConfirmActionSelected } =
    usePerformAction(selectedList, excludeParticipants, includeParticipants, includeParticipantInExistingPlan);

// #region Differentiation Profiles

const studentWithDP = window.Vue.ref<User | null>(null);

/**
 * Display the differentiation profile for a participant
 * @param participant - The participant whose differentiation profile is to be viewed
 */
function onDPSelected(participant: Participant): void {
    studentWithDP.value = participant.user;
}

// #endregion Differentiation Profiles
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
                        <th v-if="props.differentiationEnabled">
                            {{ locale.differentiationProfile.translate(false, true) }}
                        </th>
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
                        :differentiation-enabled="props.differentiationEnabled"
                        @view-differentiation-profile="onDPSelected"
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
    <cp-view-dp-modal v-model:student="studentWithDP" />
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
