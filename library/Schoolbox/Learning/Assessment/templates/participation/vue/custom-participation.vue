<script setup lang="ts">
/**
 * Custom Participation Vue Component
 */
import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import PastoralCountBadge from "@schoolboxLibrary/Pastoral/Count/vue/pastoral-count-badge.vue";
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer.vue";
import type { StudentPastoralCountBadge } from "@schoolboxLibrary/Pastoral/types/StudentPastoralCountBadge";
import type { Participant as BaseParticipant } from "../js/types/customParticipationTypes";

type Participant = BaseParticipant & {
    _links?: {
        exclude?: string;
        include?: string;
    };
};

interface Props {
    allParticipants: Participant[];
    getStudentsPastoralCountsUrl: string;
}

const props = defineProps<Props>();

const allParticipants = window.Vue.ref<Participant[]>(props.allParticipants);

// show the checkboxes if there are more than one participant
const showCheckboxes = window.Vue.computed(() => allParticipants.value.length > 1);

// selecteList is the items that are currently selected
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

// Function to check if any selected participant is excluded
const hasExcludedInSelection = window.Vue.computed(() => {
    return allParticipants.value.some(
        (participant) => selectedList.value.includes(participant.user.id) && participant.excludedAt != null,
    );
});

const loadError = window.Vue.ref<string | null>(null);
const studentPastoralCounts = window.Vue.ref<Record<string, StudentPastoralCountBadge[]>>({});

/**
 * Fetch the pastoral counts for the students
 */
async function getStudentPastoralCounts(): Promise<void> {
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
    const excludeUrl = participant._links?.exclude;
    if (!excludeUrl) {
        console.warn("No exclude URL available for participant", participant);
        return;
    }

    try {
        const response = await fetch(excludeUrl, {
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

        if (participant._links) {
            participant._links.include = excludeUrl.replace("/exclude", "/include");
        }
    } catch (error) {
        if (error instanceof Error && hasErrorMessage(error)) {
            loadError.value = error.message;
        }
        console.error("Error excluding participant:", error);
    }
}

/**
 * Includes a participant.
 * @param participant - The participant to include.
 * @returns A promise that resolves when the operation is complete.
 */
async function includeParticipant(participant: Participant): Promise<void> {
    const includeUrl = participant._links?.include;
    if (!includeUrl) {
        console.warn("No include URL available for participant", participant);
        return;
    }

    try {
        const response = await fetch(includeUrl, {
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

        participant.excludedAt = null;

        if (participant._links) {
            participant._links.exclude = includeUrl.replace("/include", "/exclude");
        }
    } catch (error) {
        if (error instanceof Error && hasErrorMessage(error)) {
            loadError.value = error.message;
        }
        console.error("Error including participant:", error);
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

window.Vue.onMounted(() => {
    void getStudentPastoralCounts();
});
</script>

<template>
    <div>
        <table>
            <thead>
                <tr>
                    <th v-if="showCheckboxes" width="40">
                        <input
                            id="select-all"
                            v-model="allSelected"
                            type="checkbox"
                            :class="{ indeterminate: isIndeterminate }"
                        />
                        <label for="select-all" class="checkbox-label"></label>
                    </th>
                    <th width="84"></th>
                    <th>Name</th>
                    <th>Customisation</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="participant in allParticipants" :key="participant.id">
                    <td v-if="showCheckboxes">
                        <input
                            :id="`id-${participant.user.id}`"
                            v-model="selectedList"
                            type="checkbox"
                            :value="participant.user.id"
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
                        <template v-if="participant.user.id">
                            <a :href="`/search/user/${participant.user.id}`">
                                {{ participant.user.lastname + ", " + participant.user.firstname }}
                                {{
                                    participant.user.preferredName &&
                                    participant.user.preferredName != participant.user.firstname
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
                                participant.user.preferredName != participant.user.firstname
                                    ? "‘" + participant.user.preferredName + "’"
                                    : ""
                            }}
                            {{ participant.user.pronoun }}
                        </template>
                        <br />

                        <p class="meta inline-container">
                            <pastoral-count-badge
                                v-for="studentBadge in studentPastoralCounts[participant.user.id]"
                                :key="`badge-${studentBadge.typeId}-${studentBadge.subtypeId}`"
                                :badge="studentBadge"
                            />
                        </p>
                    </td>
                    <td>
                        <span v-if="participant.excludedAt != null" class="excluded">Excluded</span>
                    </td>
                    <td class="no-pad">
                        <div v-if="participant.user.id" class="actions-small-1">
                            <nav>
                                <sbx-pop-out :content-id="`participant-${participant.user.id}`"></sbx-pop-out>
                            </nav>
                            <ul
                                :id="`participant-${participant.user.id}`"
                                data-dropdown-content=""
                                class="f-dropdown"
                                move-to="#custom-participation-container"
                            >
                                <template v-if="participant.excludedAt != null">
                                    <li class="separator">Manage</li>
                                    <li>
                                        <a class="icon-close" @click.prevent="includeParticipant(participant)">
                                            Remove Customisation
                                        </a>
                                    </li>
                                </template>
                                <template v-else>
                                    <li class="separator">Manage</li>
                                    <li><a class="icon-modify">Modify</a></li>
                                    <li>
                                        <a class="icon-staff-students" @click.prevent="excludeParticipant(participant)">
                                            Exclude
                                        </a>
                                    </li>
                                    <li class="separator">Create</li>
                                    <li><a class="icon-copy">Copy Activity</a></li>
                                    <li><a class="icon-submissions">New Activity</a></li>
                                </template>
                            </ul>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <bottom-drawer :show-drawer="selectedList.length > 0">
            <h3 class="selected-count">
                <em>{{ selectedList.length }} selected</em>
            </h3>
            <button class="button-clear" @click.prevent="selectedList.length = 0">Clear selection</button>
            <div class="modal-actions l-flex-row l-gap-s l-flex--justify">
                <template v-if="hasExcludedInSelection">
                    <a class="icon-close submit" @click.prevent="includeSelected">Remove Customisation</a>
                </template>
                <template v-else>
                    <div class="flex-item"><a class="icon-modify submit">Modify</a></div>
                    <div class="flex-item">
                        <a class="icon-staff-students submit" @click.prevent="excludeSelected">Exclude</a>
                    </div>
                    <div class="flex-item"><a class="icon-copy submit">Copy Activity</a></div>
                    <div class="flex-item"><a class="icon-submissions submit">New Activity</a></div>
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
    display: inline-block;
    padding: 0px;
    background: none;
    vertical-align: baseline;
    font-size: 0.8rem;
}
.modal-actions {
    display: flex;
    width: 100%;
    a {
        margin: 0 1px;
    }
}
.flex-item {
    flex: 1;
}
.excluded {
    display: inline-block;
    color: #666;
}
@media (max-width: 768px) {
    .flex-container {
        flex-direction: column;
    }

    .flex-item {
        flex: 1 1 100%;
    }
}
</style>
