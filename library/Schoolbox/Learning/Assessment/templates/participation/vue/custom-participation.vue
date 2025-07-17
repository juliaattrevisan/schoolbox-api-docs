<script setup lang="ts">
/**
 * Custom Participation Vue Component
 */
import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer.vue";
import type { StudentPastoralCountBadge } from "@schoolboxLibrary/Pastoral/types/StudentPastoralCountBadge";
import type { Participant, Plan } from "../js/types/customParticipationTypes";

interface Props {
    allParticipants: Participant[];
    getStudentsPastoralCountsUrl: string;
    getLearningPlanNewUrl: string;
    getAssessmentParticipation: string;
    getAssessmentParticipationNewActivity: string;
    assessmentId: number;
    customisations: Plan[];
    includeInExistingCustomisation: string;
}
const props = defineProps<Props>();

const allParticipants = window.Vue.ref<Participant[]>(props.allParticipants);

// show the checkboxes if there are more than one participant
const showCheckboxes = window.Vue.computed(() => allParticipants.value.length > 1);

// selectedList is the items that are currently selected
const selectedList = window.Vue.ref<number[]>([]);

// Computed property to check if all are selected
const allSelected = window.Vue.computed({
    get: () => allParticipants.value.every((participant) => selectedList.value.includes(participant.user.id)),
    set: (value) => {
        selectedList.value = value ? allParticipants.value.map((participant) => participant.user.id) : [];
    },
});

// Function to check if at least one is selected
const isIndeterminate = window.Vue.computed(() => {
    return selectedList.value.length > 0 && selectedList.value.length < allParticipants.value.length;
});

// Function to check if any selected participants has and customisation, whether excluded or assigned a different activity
const hasCustomisedInSelection = window.Vue.computed(() => {
    return allParticipants.value.some(
        (participant) =>
            selectedList.value.includes(participant.user.id) &&
            (participant.excludedAt !== null || participant.plan.name !== null),
    );
});

const loadError = window.Vue.ref<string | null>(null);
const studentPastoralCounts = window.Vue.ref<Record<string, StudentPastoralCountBadge[]>>({});

/**
 * Fetch the pastoral counts for the students
 */
async function getStudentPastoralCounts(): Promise<void> {
    if (props.getStudentsPastoralCountsUrl === "") {
        return;
    }
    const url = props.getStudentsPastoralCountsUrl.replace(
        "{studentIds}",
        allParticipants.value.map((participant) => participant.user.id).join(","),
    );
    try {
        const response = await fetch(url, {
            headers: {
                Accept: "application/json",
            },
        });
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
 * Excludes a participant.
 * @param participant - The participant to exclude.
 * @returns A promise that resolves when the operation is complete.
 */
async function excludeParticipant(participant: Participant): Promise<void> {
    const toast = window.sbProps.toastActivate("Excluding...");
    try {
        const response = await fetch(participant._links.exclude, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
            },
        });
        if (!response.ok) {
            const errorMessage = await extractErrorMessage(response);
            throw new Error(errorMessage);
        }
        participant.excludedAt = new Date().toISOString(); // Assigning a timestamp as a string
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
 * Includes a participant.
 * @param participant - The participant to include.
 * @returns A promise that resolves when the operation is complete.
 */
async function includeParticipant(participant: Participant): Promise<void> {
    const toast = window.sbProps.toastActivate("Removing customisation...");
    try {
        const response = await fetch(participant._links.include, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
            },
        });
        if (!response.ok) {
            const errorMessage = await extractErrorMessage(response);
            throw new Error(errorMessage);
        }
        participant.excludedAt = null; // Resetting the exclusion timestamp
        participant.plan.name = null; // Resetting the plan
        toast.timeout({ text: "Customisation removed successfully" });
    } catch (error) {
        if (error instanceof Error && hasErrorMessage(error)) {
            loadError.value = error.message;
        }
        console.error("Error including participant:", error);
        toast.timeout({ text: "Error excluding student", isSuccess: false });
    }
}

/**
 * Excludes the selected participants.
 * Clears the selection after the operation is complete.
 * @returns A promise that resolves when all exclusions are complete.
 */
async function excludeSelected(): Promise<void> {
    try {
        const participantsToExclude = allParticipants.value.filter((p) => selectedList.value.includes(p.user.id));
        await Promise.all(participantsToExclude.map((participant) => excludeParticipant(participant)));
        selectedList.value = [];
    } catch (error) {
        if (error instanceof Error && hasErrorMessage(error)) {
            loadError.value = error.message;
        }
        console.error("Error excluding selected participants:", error);
    }
}

/**
 * Includes the selected participants.
 * Clears the selection after the operation is complete.
 * @returns A promise that resolves when all inclusions are complete.
 */
async function includeSelected(): Promise<void> {
    const participantsToInclude = allParticipants.value.filter((p) => selectedList.value.includes(p.user.id));
    await Promise.all(participantsToInclude.map((participant) => includeParticipant(participant)));
    selectedList.value = [];
}

/**
 * Placeholder function for testing endpoint
 * Use this as sample of how it will work
 * @param participant - The participant to include
 */
async function includeParticipantInExistingPlan(participant: Participant): Promise<void> {
    const toast = window.sbProps.toastActivate("Adding to customisation...");
    try {
        const response = await fetch(props.includeInExistingCustomisation, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                students: [
                    participant.user.id
                ],
                planId: 1305, // TODO: plan id of the plan to include the student
                assessmentId: 1576, // TODO: assessment id of the plan (ie plan.assessable.id)
            }),
        });
        if (response.ok) {
            toast.timeout({ text: "Customisation added successfully" });
        }

    } catch (error) {
        toast.timeout({ text: "Error adding Customisation" });
    }
}
/**
 * Load the student pastoral counts when the component is mounted.
 */
window.Vue.onMounted(() => {
    void getStudentPastoralCounts();
});
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
                            :value="participant.user.id"
                            :data-test="`custom-participation-checkbox-${participant.user.id}`"
                        />
                        <label :for="`id-${participant.user.id}`" class="checkbox-label"></label>
                    </td>
                    <td>
                        <img
                            :src="`/portrait.php?id=${participant.user.id}&size=square64`"
                            :alt="participant.user.lastname + ', ' + participant.user.firstname"
                        />
                    </td>
                    <td>
                        <div class="l-flex-col l-gap-s">
                            <template v-if="participant.user.id">
                                <a :href="`/search/user/${participant.user.id}`">
                                    {{ participant.user.lastname + ", " + participant.user.firstname }}
                                    {{
                                        participant.user.preferredName &&
                                        participant.user.preferredName !== participant.user.firstname
                                            ? "‘" + participant.user.preferredName + "’"
                                            : ""
                                    }}
                                    {{ participant.user.pronoun }}
                                </a>
                            </template>
                            <template v-else>
                                {{ participant.user.lastname + ", " + participant.user.firstname }}
                                {{
                                    participant.user.preferredName &&
                                    participant.user.preferredName !== participant.user.firstname
                                        ? "‘" + participant.user.preferredName + "’"
                                        : ""
                                }}
                                {{ participant.user.pronoun }}
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
                        <template v-if="participant.excludedAt !== null">
                            <span v-if="participant.customisedPlan !== null">
                                <a :href="`${participant.customisedPlan.assessable._links.modify}`">
                                    {{ participant.customisedPlan.assessable.title }}
                                </a>
                            </span>
                            <span v-else> Excluded </span>
                        </template>
                        <span v-else-if="participant.plan.name !== null">
                            <a :href="`${getAssessmentParticipation}/${participant.plan.id}/modify?${getReturnUrl()}`">
                                {{ participant.plan.name }}
                            </a>
                        </span>
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
                                <template v-if="participant.excludedAt !== null">
                                    <li class="separator">Manage</li>
                                    <li>
                                        <button class="icon-close" @click.prevent="includeParticipant(participant)">
                                            Remove Customisation
                                        </button>
                                    </li>
                                </template>
                                <template v-else-if="participant.plan.name !== null">
                                    <li>
                                        <button class="icon-close" @click.prevent="includeParticipant(participant)">
                                            Remove Customisation
                                        </button>
                                    </li>
                                </template>
                                <template v-else>
                                    <li class="separator">Manage</li>
                                    <li>
                                        <a
                                            class="icon-modify"
                                            :href="`${getLearningPlanNewUrl}?student=${participant.user.id}&${getReturnUrl()}`"
                                        >
                                            Modify
                                        </a>
                                    </li>
                                    <li>
                                        <button
                                            class="icon-staff-students"
                                            @click.prevent="excludeParticipant(participant)"
                                        >
                                            Exclude
                                        </button>
                                    </li>
                                    <li>
                                        <button
                                            class="icon-staff-students"
                                            @click.prevent="includeParticipantInExistingPlan(participant)"
                                        >
                                            Test
                                        </button>
                                    </li>
                                    <li class="separator">Create</li>
                                    <li>
                                        <a class="icon-copy"> Copy Activity </a>
                                    </li>
                                    <li>
                                        <a
                                            class="icon-submissions"
                                            :href="`${getAssessmentParticipationNewActivity}?student=${participant.user.id}&original=${assessmentId}&${getReturnUrl()}`"
                                        >
                                            New Activity
                                        </a>
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
            <button class="button-clear" @click.prevent="selectedList.length = 0">Clear selection</button>
            <div class="l-flex-row l-flex--justify l-gap-xs">
                <template v-if="hasCustomisedInSelection">
                    <button
                        class="icon-close submit"
                        data-test="custom-participation-remove-customisation"
                        @click.prevent="includeSelected"
                    >
                        Remove Customisation
                    </button>
                </template>
                <template v-else>
                    <div class="l-flex__item--1">
                        <a class="icon-copy submit" data-test="custom-participation-copy-activity">Copy Activity</a>
                    </div>
                    <div class="l-flex__item--1">
                        <button class="icon-staff-students submit" @click.prevent="excludeSelected">Exclude</button>
                    </div>
                    <div class="l-flex__item--1">
                        <a
                            class="icon-modify submit"
                            :href="`${getLearningPlanNewUrl}?student=${selectedList.join(',')}&${getReturnUrl()}`"
                        >
                            Modify
                        </a>
                    </div>
                    <div class="l-flex__item--1">
                        <a
                            class="icon-submissions submit"
                            :href="`${getAssessmentParticipationNewActivity}?student=${selectedList.join(',')}&original=${assessmentId}&${getReturnUrl()}`"
                        >
                            New Activity
                        </a>
                    </div>
                </template>
            </div>
        </bottom-drawer>
    </div>
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

    .l-flex__item--1 {
        flex: 1 1 100%;
    }
}
</style>
