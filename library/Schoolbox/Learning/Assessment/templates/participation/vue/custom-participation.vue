<script setup lang="ts">
/**
 * Display a list of students linked to an assessment and provide details and actions for student participation
 */

import locale from "@js/terminology/rosettaStone";
import { sbProps } from "@js/util/sbx/pageUtils";
import DataLoadError from "@schoolboxVue/components/error/data-load-error.vue";
import { useSelectedList } from "@schoolboxVue/composables/form/useSelectedList";
import type { User } from "@schoolboxLibrary/types/User";
import ViewDpModal from "@schoolboxLibrary/DifferentiationProfile/templates/vue/view-dp-modal.vue";
import { ACTIVITY_TYPE_DUE_WORK, ACTIVITY_TYPE_TASK } from "@schoolboxAssessment/Types/assessmentTypes";
import type { Routes as PastoralRoutes, Config } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";
import CpParticipant from "./cp-participant.vue";
import CpBottomDrawer from "./cp-bottom-drawer.vue";
import CpIncludeInExistingModal from "./cp-include-in-existing-modal.vue";
import CpConfirmModifyModal from "./cp-confirm-modify-modal.vue";
import CpSuggestedCustomisationModal from "./cp-suggested-customisation-modal.vue";
import type { CustomisedPlan, Participant, Plan, Routes } from "../js/types/customParticipationTypes";
import { useGetPastoralFlags } from "./composable/useGetPastoralFlags";
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
    assessmentType: string;
    customisations: (Plan | CustomisedPlan)[];
    pastoralConfig: Config;
    pastoralRoutes: PastoralRoutes;
}>();

/**
 * To pass through to the pastoral-records-modal that displays when a flag is selected
 */
window.Vue.provide("pastoralConfig", props.pastoralConfig);
window.Vue.provide("pastoralRoutes", props.pastoralRoutes);

/**
 * Handle selection of participants via checkboxes
 */
const allParticipants = window.Vue.ref<Participant[]>(props.allParticipants);
const { showCheckboxes, selectedList, allSelected, isIndeterminate } = useSelectedList(allParticipants);

/**
 * We only want to show the column on certain assessment types and when differentiation and AI features are enabled
 */
const canSuggestCustomisation = window.Vue.computed(() => {
    return (
        props.differentiationEnabled &&
        sbProps.aiFeatures.learningactivitycustomisation?.enabled &&
        (props.assessmentType === ACTIVITY_TYPE_DUE_WORK || props.assessmentType === ACTIVITY_TYPE_TASK)
    );
});

/**
 * Get student pastoral flags
 */
const { loadError, pastoralFlags } = useGetPastoralFlags(
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
const studentWithDPRoute = window.Vue.ref<string>("");
const studentWithCustomisationsRoute = window.Vue.ref<string>("");

/**
 * Display the differentiation profile for a participant
 * @param participant - The participant whose differentiation profile is to be viewed
 */
function onViewDP(participant: Participant): void {
    studentWithDPRoute.value = participant._links.differentiationProfile ?? "";
    studentWithCustomisationsRoute.value = participant._links.customisationRecord ?? "";
    studentWithDP.value = participant.user;
}

// #endregion Differentiation Profiles

// #region AI Suggested Customisation

const currentParticipant = window.Vue.ref<Participant | null>(null);

/**
 * To view the AI suggested customisation for a participant
 * @param participant - The participant whose AI suggested customisation is to be viewed or generated
 */
function onViewSuggestedCustomisation(participant: Participant): void {
    currentParticipant.value = participant;
}

// #endregion AI Suggested Customisation

// Filter to only show students with differentiation profiles
const onlyShowDP = window.Vue.ref(false);

const filteredParticipants = window.Vue.computed(() => {
    let participants = allParticipants.value;

    if (onlyShowDP.value) {
        participants = participants.filter((participant) => participant._links.differentiationProfile);
    }

    if (customisationFilter.value === "excluded") {
        participants = participants.filter(
            (participant) => participant.excludedAt != null && participant.customisedPlan == null,
        );
    }
    if (customisationFilter.value === "none") {
        participants = participants.filter(
            (participant) =>
                participant.excludedAt == null && participant.customisedPlan == null && participant.plan?.name == null,
        );
    }
    if (customisationFilter.value === "new") {
        participants = participants.filter((participant) => participant.customisedPlan != null);
    }
    if (customisationFilter.value === "modified") {
        participants = participants.filter((participant) => participant.plan?.name != null);
    }

    return participants;
});
const customisationFilter = window.Vue.ref("all");
const customisationOptions = [
    { label: "Show All Students", value: "all" },
    { label: "Excluded", value: "excluded" },
    { label: "Modified Activity", value: "modified" },
    { label: "New Activity", value: "new" },
    { label: "No Customisation", value: "none" },
];
</script>

<template>
    <div v-if="loadError" class="c-island l-pad-m" data-test="custom-participation-load-error">
        <data-load-error data-title="Student Participation" :further-info="loadError" />
    </div>
    <div>
        <div class="small-12 island l-mbm">
            <h2 class="subheader">Filter Students</h2>
            <section class="content">
                <div class="small-12 medium-6 large-4 columns">
                    <label for="customisation-type">Customisation Type</label>
                    <select id="customisation-type" v-model="customisationFilter" style="min-width: 220px">
                        <option v-for="option in customisationOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div class="small-12 medium-6 large-4 columns">
                    <label class="l-mbs" for="only-show-dp">
                        Only show students with {{ locale.differentiationProfile.translate(false, true) }}
                    </label>
                    <div class="switch f-no-margins">
                        <input id="only-show-dp" v-model="onlyShowDP" type="checkbox" />
                        <label for="only-show-dp">
                            <span>On</span>
                            <span>Off</span>
                        </label>
                    </div>
                </div>
            </section>
        </div>

        <div class="small-12 island l-mbm">
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
                            <th v-if="canSuggestCustomisation">Suggested Customisation</th>
                            <th>Customisation</th>
                            <td></td>
                        </tr>
                    </thead>
                    <tbody>
                        <cp-participant
                            v-for="participant in filteredParticipants"
                            :key="participant.id"
                            v-model:selected-list="selectedList"
                            :participant="participant"
                            :show-checkboxes="showCheckboxes"
                            :pastoral-flags="pastoralFlags"
                            :routes="props.routes"
                            :assessment-id="props.assessmentId"
                            :can-suggest-customisation="canSuggestCustomisation"
                            :customisations="props.customisations"
                            :differentiation-enabled="props.differentiationEnabled"
                            @view-differentiation-profile="onViewDP"
                            @view-suggested-customisation="onViewSuggestedCustomisation"
                            @action-selected="onActionSelected"
                            @open-include-in-existing-modal="openIncludeInExistingModal"
                        />
                    </tbody>
                </table>
            </div>
        </div>
        <!-- container for the sbx-pop-out to be displayed in -->
        <div id="pop-out-container"></div>
        <cp-bottom-drawer
            v-model:selected-list="selectedList"
            :routes="props.routes"
            :assessment-id="props.assessmentId"
            :customisations="props.customisations"
            @action-selected="onActionSelected"
            @open-include-in-existing-modal="openIncludeInExistingModal"
        />
    </div>
    <view-dp-modal
        v-model:student="studentWithDP"
        :dp-route="studentWithDPRoute"
        :customisations-route="studentWithCustomisationsRoute"
    />
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
    <cp-suggested-customisation-modal
        v-if="props.differentiationEnabled && sbProps.aiFeatures.learningactivitycustomisation?.enabled"
        v-model:participant="currentParticipant"
        :routes="props.routes"
        :assessment-id="props.assessmentId"
    />
</template>
