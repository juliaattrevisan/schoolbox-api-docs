<script setup lang="ts">
/**
 * Custom Participation Vue Component
 */
import {hasErrorMessage, isAbortError} from "@js/util/errorUtils";
import {getReturnUrl} from "@js/util/sbx/urlUtils";
import {extractErrorMessage} from "@js/util/sbx/fetchUtils";
import locale from "@js/terminology/rosettaStone";
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer.vue";
import type {StudentPastoralCountBadge} from "@schoolboxLibrary/Pastoral/types/StudentPastoralCountBadge";
import BasicModal from "@schoolboxVue/components/modal/basic-modal.vue";
import type {CustomisedPlan, Participant, Plan} from "../js/types/customParticipationTypes";
import {postIncludeParticipant} from "../js/types/customParticipationPayload";

interface Props {
    allParticipants: Participant[];
    pastoralEnabled: boolean;
    getStudentsPastoralCountsUrl: string;
    getLearningPlanNewUrl: string;
    getAssessmentParticipationNewActivity: string;
    getAssessmentParticipationCopyActivity: string;
    assessmentId: number;
    customisations: (Plan | CustomisedPlan)[];
    includeInExistingCustomisation: string;
}
const props = defineProps<Props>();

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

// #endregion

// #region pastoral counts

const loadError = window.Vue.ref<string | null>(null);
const studentPastoralCounts = window.Vue.ref<Record<string, StudentPastoralCountBadge[]>>({});

/**
 * Fetch the pastoral counts for the students
 */
async function getStudentPastoralCounts(): Promise<void> {
    if (!props.pastoralEnabled || props.getStudentsPastoralCountsUrl === "") {
        return;
    }
    const url = props.getStudentsPastoralCountsUrl.replace(
        "{studentIds}",
        allParticipants.value.map((participant) => participant.user.id).join(","),
    );
    try {
        const response = await fetch(url, { headers: { Accept: "application/json" } });
        if (!response.ok) {
            const errorMessage = await extractErrorMessage(response);
            throw new Error(errorMessage);
        }
        const responseJson = (await response.json()) as Record<string, StudentPastoralCountBadge[]>;
        if (typeof responseJson !== "object" || responseJson === null) {
            throw new Error("Unexpected response format");
        }
        if (typeof responseJson === "object" && responseJson !== null) {
            // get data
            studentPastoralCounts.value = responseJson;
        } else {
            throw new Error("Unexpected response format");
        }
    } catch (error) {
        // ignore abort errors, as the previous report was cancelled
        if (isAbortError(error)) {
            return;
        }
        if (error instanceof Error && hasErrorMessage(error)) {
            loadError.value = error.message;
        }
        console.error(error);
    }
}

/**
 * Load the student pastoral counts when the component is mounted.
 */
window.Vue.onMounted(() => {
    void getStudentPastoralCounts();
});

// #endregion

// #region exclude/include participants

/**
 * Excludes a participant.
 * @param participant - The participant to exclude.
 * @returns A promise that resolves when the operation is complete.
 */
async function excludeParticipant(participant: Participant): Promise<void> {
    const toast = window.sbProps.toastActivate("Excluding...");
    try {
        const response = await fetch(participant._links.exclude, {
            method: "POST",
            headers: { Accept: "application/json", "Content-Type": "application/json" },
        });
        if (!response.ok) {
            const errorMessage = await extractErrorMessage(response);
            throw new Error(errorMessage);
        }
        participant.excludedAt = new Date().toISOString(); // Assigning a timestamp as a string
        participant.hasInteraction = false;
        toast.timeout({ text: "Student excluded successfully" });
    } catch (error) {
        if (error instanceof Error && hasErrorMessage(error)) {
            loadError.value = error.message;
        }
        console.error("Error excluding participant:", error);
        toast.timeout({ text: "Error excluding student", isSuccess: false });
    }
}

/**
 * Excludes the selected participants.
 * Clears the selection after the operation is complete.
 * @param participants - The participants to exclude.
 * @returns A promise that resolves when all exclusions are complete.
 */
async function excludeParticipants(participants: Participant[]): Promise<void> {
    try {
        await Promise.all(participants.map((participant) => excludeParticipant(participant)));
        selectedList.value = [];
    } catch (error) {
        if (error instanceof Error && hasErrorMessage(error)) {
            loadError.value = error.message;
        }
        console.error("Error excluding selected participants:", error);
    }
}

/**
 * Includes a participant.
 * @param participant - The participant to include.
 * @returns A promise that resolves when the operation is complete.
 */
async function includeParticipant(participant: Participant): Promise<void> {
    const toast = window.sbProps.toastActivate("Removing customisation...");
    const response = await postIncludeParticipant(participant._links.include, {
        includeResponse: true,
    });

    if (!response.success) {
        const error = response.message ?? "Error excluding student";
        loadError.value = error;
        console.error("Error including participant:", error);
        toast.timeout({ text: error, isSuccess: false });
        return;
    }

    participant.excludedAt = null; // Resetting the exclusion timestamp
    participant.plan.name = null; // Resetting the plan
    participant.customisedPlan = null; // Resetting the customised plan
    participant.hasInteraction = response.data?.hasInteraction ?? false
    toast.timeout({ text: "Customisation removed successfully" });
}

/**
 * Includes the selected participants.
 * Clears the selection after the operation is complete.
 * @param participants - The participants to include.
 * @returns A promise that resolves when all inclusions are complete.
 */
async function includeParticipants(participants: Participant[]): Promise<void> {
    await Promise.all(participants.map((participant) => includeParticipant(participant)));
}

// #endregion

// #region include in existing customisation

const isIncludeInExistingOpen = window.Vue.ref(false);
const includeInExistingList = window.Vue.ref<Participant[]>([]); // array of participants to include in existing customisation
const includeInExistingActivityId = window.Vue.ref<number | null>(null);
const isSingleIncludeInExisting = window.Vue.ref(false); // whether the include is for a single participant (three-dot) or multiple (bottom drawer)

/**
 * The current selected activity for inclusion.
 */
const includeInExistingActivity = window.Vue.computed<Plan | CustomisedPlan | null>(() => {
    return props.customisations.find((plan) => plan.id === includeInExistingActivityId.value) ?? null;
});

/**
 * Add all the selected participants to an existing customisation plan.
 */
async function includeParticipantInExistingPlan(): Promise<void> {
    if (includeInExistingActivity.value === null) return;
    const activity: Plan | CustomisedPlan = includeInExistingActivity.value;
    const toast = window.sbProps.toastActivate("Adding to customisation...");
    try {
        const response = await fetch(props.includeInExistingCustomisation, {
            method: "POST",
            headers: { Accept: "application/json", "Content-Type": "application/json" },
            body: JSON.stringify({
                students: includeInExistingList.value.map((p) => p.user.id),
                ...("assessable" in activity ? { assessmentId: activity.assessable.id } : {}),
                ...(activity.name !== null ? { planId: activity.id } : {}),
            }),
        });
        if (!response.ok) {
            const errorMessage = await extractErrorMessage(response);
            throw new Error(errorMessage);
        }
        // add the activity to each participant
        includeInExistingList.value.forEach((participant) => {
            if ("assessable" in activity) {
                participant.customisedPlan = { ...activity };
            } else {
                participant.plan = { ...activity };
            }
        });
        toast.timeout({ text: "Customisation added successfully", isSuccess: true });
        isIncludeInExistingOpen.value = false;
        includeInExistingList.value = [];
    } catch (error) {
        if (error instanceof Error && hasErrorMessage(error)) {
            loadError.value = error.message;
        }
        console.error("Error adding Customisation:", error);
        toast.timeout({ text: "Error adding Customisation", isSuccess: false });
    }
}

/**
 * Opens the modal to include a participant in an existing customisation.
 * @param isSingle - Whether this is a single participant inclusion (from pop-out) or multiple (from bottom drawer).
 * @param participant - The participant to be included.
 */
function openIncludeInExistingModal(isSingle: boolean, participant: Participant | null): void {
    isSingleIncludeInExisting.value = isSingle;
    if (isSingleIncludeInExisting && participant !== null) {
        includeInExistingList.value = [participant];
    } else {
        includeInExistingList.value = selectedList.value;
    }
    includeInExistingActivityId.value = null;
    isIncludeInExistingOpen.value = true;
}

/**
 * Generates a label for a given customisation plan.
 * @param plan - The customisation plan to generate the label for.
 * @returns The label for the customisation plan.
 */
function getCustomisationLabel(plan: Plan | CustomisedPlan): string {
    if ("assessable" in plan && plan.assessable !== null) {
        return plan.assessable.title || "";
    }
    return plan.name || "";
}

/**
 * Generates the meta for a given customisation plan.
 * @param plan - The customisation plan to generate the meta for.
 * @returns The meta for the customisation plan.
 */
function getCustomisationMeta(plan: Plan | CustomisedPlan): string {
    if ("assessable" in plan && plan.assessable !== null) {
        return `(New ${locale.activity?.singular()})`;
    }
    return `(Modified ${locale.activity?.singular()})`;
}

/**
 * Gets the modification URL for a customised plan
 * @param customisedPlan - The customised plan to get the URL for
 * @returns The modification URL with return URL parameter
 */
function getCustomisedPlanUrl(customisedPlan: CustomisedPlan): string {
    const baseUrl =
        customisedPlan.name !== null ? customisedPlan._links.modify : customisedPlan.assessable._links.modify;
    return `${baseUrl}?${getReturnUrl()}`;
}

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
            await excludeParticipants(modifyList.value);
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
    isConfirmModifyOpen.value = false;
}

// #endregion
</script>

<template>
    <div v-if="loadError" class="c-island l-pad-m" data-test="custom-participation-load-error">
        <div class="alert-box alert f-no-margins">
            Sorry, there was an error during report retrieval. Please
            <a href="" title="refresh the page"> refresh the page </a>
            to try again and contact your support if the problem persists.
            <em>Further support info: {{ loadError }}</em>
        </div>
    </div>
    <div>
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
                <tr
                    v-for="participant in allParticipants"
                    :key="participant.id"
                    :data-test="`custom-participation-row-${participant.user.id}`"
                >
                    <td v-if="showCheckboxes">
                        <input
                            :id="`id-${participant.user.id}`"
                            v-model="selectedList"
                            type="checkbox"
                            :value="participant"
                            :data-test="`custom-participation-checkbox-${participant.user.id}`"
                        />
                        <label :for="`id-${participant.user.id}`" class="checkbox-label"></label>
                    </td>
                    <td>
                        <img
                            :src="`/portrait.php?id=${participant.user.id}&size=square64`"
                            :alt="`${participant.user.lastname}, ${participant.user.firstname}`"
                        />
                    </td>
                    <td>
                        <div class="l-flex-col l-gap-s">
                            <div v-if="participant.user.id">
                                <a :href="`/search/user/${participant.user.id}`">
                                    {{ participant.user.lastname }},
                                    {{ participant.user.preferredName || participant.user.firstname }}
                                    {{ participant.user.pronoun || "" }}
                                </a>
                            </div>
                            <template v-else>
                                {{ participant.user.lastname }},
                                {{ participant.user.preferredName || participant.user.firstname }}
                                {{ participant.user.pronoun || "" }}
                            </template>
                            <div class="l-flex l-gap-xs">
                                <sbx-label
                                    v-for="studentBadge in studentPastoralCounts[participant.user.id]"
                                    :key="`badge-${studentBadge.typeId}-${studentBadge.subtypeId}`"
                                    :title="studentBadge.title"
                                    :text="studentBadge.count.toString()"
                                    :icon="studentBadge.icon"
                                    :background-color="studentBadge.bgColor"
                                    :text-color="studentBadge.fgColor"
                                    :href="studentBadge.destinationUrl"
                                />
                            </div>
                        </div>
                    </td>
                    <td>
                        <template v-if="participant.customisedPlan !== null">
                            <a :href="getCustomisedPlanUrl(participant.customisedPlan)">
                                {{ participant.customisedPlan.name ?? participant.customisedPlan.assessable.title }}
                            </a>
                            <span class="meta">{{ getCustomisationMeta(participant.customisedPlan) }}</span>
                        </template>
                        <template v-else-if="participant.plan.name !== null">
                            <a :href="`${participant.plan._links.modify}?${getReturnUrl()}`">
                                {{ participant.plan.name }}
                            </a>
                            <span class="meta">{{ getCustomisationMeta(participant.plan) }}</span>
                        </template>
                        <template v-else-if="participant.excludedAt !== null">Excluded</template>
                    </td>
                    <td class="no-pad">
                        <div v-if="participant.user.id" class="actions-small-1">
                            <nav>
                                <sbx-pop-out
                                    :content-id="`participant-${participant.user.id}`"
                                    move-to="#pop-out-container"
                                    is-close-on-content-click
                                ></sbx-pop-out>
                            </nav>
                            <ul :id="`participant-${participant.user.id}`" data-dropdown-content="" class="f-dropdown">
                                <template
                                    v-if="
                                        participant.excludedAt !== null ||
                                        participant.plan.name !== null ||
                                        participant.customisedPlan !== null
                                    "
                                >
                                    <li class="separator">Manage</li>
                                    <li>
                                        <button
                                            class="icon-close"
                                            @click.prevent="
                                                onActionSelected([participant], 'removeCustomisation', true)
                                            "
                                        >
                                            Remove Customisation
                                        </button>
                                    </li>
                                </template>
                                <template v-else>
                                    <li class="separator">Manage</li>
                                    <li>
                                        <button
                                            class="icon-modify"
                                            @click.prevent="
                                                onActionSelected(
                                                    [participant],
                                                    `${props.getLearningPlanNewUrl}?student=${participant.user.id}&${getReturnUrl()}`,
                                                    true,
                                                )
                                            "
                                        >
                                            Modify
                                        </button>
                                    </li>
                                    <li>
                                        <button
                                            class="icon-staff-students"
                                            @click.prevent="onActionSelected([participant], 'exclude', true)"
                                        >
                                            Exclude
                                        </button>
                                    </li>
                                    <li>
                                        <button
                                            v-if="props.customisations.length > 0"
                                            class="icon-list"
                                            @click.prevent="openIncludeInExistingModal(true, participant)"
                                        >
                                            Include in Existing Customisation
                                        </button>
                                    </li>
                                    <li class="separator">Create</li>
                                    <li v-if="getAssessmentParticipationCopyActivity">
                                        <button
                                            class="icon-copy"
                                            @click.prevent="
                                                onActionSelected(
                                                    [participant],
                                                    `${getAssessmentParticipationCopyActivity}?student=${participant.user.id}&original=${assessmentId}&${getReturnUrl()}`,
                                                    true,
                                                )
                                            "
                                        >
                                            Copy Activity
                                        </button>
                                    </li>
                                    <li>
                                        <button
                                            class="icon-submissions"
                                            @click.prevent="
                                                onActionSelected(
                                                    [participant],
                                                    `${getAssessmentParticipationNewActivity}?student=${participant.user.id}&original=${assessmentId}&${getReturnUrl()}`,
                                                    true,
                                                )
                                            "
                                        >
                                            New Activity
                                        </button>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <!-- container for the sbx-pop-out to be displayed in -->
        <div id="pop-out-container"></div>
        <bottom-drawer data-test="custom-participation-bottom-drawer" :show-drawer="selectedList.length > 0">
            <h3 class="selected-count">
                <em>{{ selectedList.length }} selected</em>
            </h3>
            <button class="button-clear" @click.prevent="selectedList = []">Clear selection</button>
            <div class="l-flex-row l-flex--justify l-gap-xs">
                <template v-if="hasCustomisedInSelection">
                    <button
                        class="icon-close submit"
                        data-test="custom-participation-remove-customisation"
                        @click.prevent="onActionSelected(selectedList, 'removeCustomisation', false)"
                    >
                        Remove Customisation
                    </button>
                </template>
                <template v-else>
                    <div class="l-flex__item--1-auto">
                        <button
                            class="icon-modify submit"
                            @click.prevent="
                                onActionSelected(
                                    selectedList,
                                    `${getLearningPlanNewUrl}?student=${selectedList.map((p) => p.user.id).join(',')}&${getReturnUrl()}`,
                                    false,
                                )
                            "
                        >
                            Modify
                        </button>
                    </div>
                    <div class="l-flex__item--1-auto">
                        <button
                            class="icon-staff-students submit"
                            @click.prevent="onActionSelected(selectedList, 'exclude', false)"
                        >
                            Exclude
                        </button>
                    </div>
                    <div v-if="props.customisations.length > 0" class="l-flex__item--1-auto">
                        <button class="icon-list submit" @click.prevent="openIncludeInExistingModal(false, null)">
                            Include in Existing Customisation
                        </button>
                    </div>
                    <div v-if="getAssessmentParticipationCopyActivity" class="l-flex__item--1-auto">
                        <button
                            class="icon-copy submit"
                            data-test="custom-participation-copy-activity"
                            @click.prevent="
                                onActionSelected(
                                    selectedList,
                                    `${getAssessmentParticipationCopyActivity}?student=${selectedList.map((p) => p.user.id).join(',')}&original=${assessmentId}&${getReturnUrl()}`,
                                    false,
                                )
                            "
                        >
                            Copy Activity
                        </button>
                    </div>
                    <div class="l-flex__item--1-auto">
                        <button
                            class="icon-submissions submit"
                            @click.prevent="
                                onActionSelected(
                                    selectedList,
                                    `${getAssessmentParticipationNewActivity}?student=${selectedList.map((p) => p.user.id).join(',')}&original=${assessmentId}&${getReturnUrl()}`,
                                    false,
                                )
                            "
                        >
                            New Activity
                        </button>
                    </div>
                </template>
            </div>
        </bottom-drawer>
    </div>
    <basic-modal v-model:is-open="isIncludeInExistingOpen">
        <div class="l-flex-col l-gap-m f-no-margins">
            <div class="l-flex-col l-gap-s f-no-margins">
                <h2>Include in existing customisation</h2>
                <p class="meta">
                    Modifying participation for:
                    <template v-for="(participant, index) in includeInExistingList" :key="participant.user.id">
                        {{
                            participant.user.preferredName ? participant.user.preferredName : participant.user.firstname
                        }}
                        {{ participant.user.lastname
                        }}<template v-if="index < includeInExistingList.length - 2">, </template
                        ><template v-if="index === includeInExistingList.length - 2"> and </template>
                    </template>
                </p>
            </div>
            <div class="l-flex-col l-gap-s f-no-margins">
                <label for="activitySelect">Customisation</label>
                <select id="activitySelect" v-model="includeInExistingActivityId" class="f-no-margins">
                    <option value="null" disabled>Select customisation</option>
                    <option
                        v-for="customisation in props.customisations"
                        :key="customisation.id"
                        :value="customisation.id"
                    >
                        {{ getCustomisationLabel(customisation) }} {{ getCustomisationMeta(customisation) }}
                    </option>
                </select>
            </div>
            <div class="l-flex l-gap-xs f-no-margins">
                <button class="button l-flex__item--1" @click.prevent="isIncludeInExistingOpen = false">Cancel</button>
                <button
                    class="button submit l-flex__item--1"
                    :disabled="!includeInExistingActivity"
                    @click.prevent="
                        onActionSelected(includeInExistingList, 'includeInExisting', isSingleIncludeInExisting)
                    "
                >
                    Confirm
                </button>
            </div>
        </div>
    </basic-modal>
    <basic-modal v-model:is-open="isConfirmModifyOpen">
        <div class="l-flex-col l-gap-m f-no-margins">
            <div class="l-flex-col l-gap-s f-no-margins">
                <h2>Please confirm</h2>
                <p class="f-no-margins">
                    The following student(s) have submissions and/or feedback for this
                    {{ locale.assessment.singular() }}.
                </p>
                <div class="l-flex-col l-gap-xs">
                    <div
                        v-for="participant in participantsWithInteractions"
                        :key="participant.user.id"
                        class="l-flex l-gap-s l-align-center"
                    >
                        <img
                            :src="`/portrait.php?id=${participant.user.id}&size=square32`"
                            :alt="`${participant.user.lastname}, ${participant.user.firstname}`"
                        />
                        <p>
                            {{
                                participant.user.preferredName
                                    ? participant.user.preferredName
                                    : participant.user.firstname
                            }}
                            {{ participant.user.lastname }}
                        </p>
                    </div>
                </div>
                <p>
                    Exclusion from this {{ locale.assessment.singular() }} will remove access to these submissions
                    and/or feedback. Proceed?
                </p>
            </div>
            <div class="l-flex l-gap-xs f-no-margins">
                <button class="button submit l-flex__item--1" @click.prevent="onConfirmActionSelected">Yes</button>
                <button class="button l-flex__item--1" @click.prevent="isConfirmModifyOpen = false">Cancel</button>
            </div>
        </div>
    </basic-modal>
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
