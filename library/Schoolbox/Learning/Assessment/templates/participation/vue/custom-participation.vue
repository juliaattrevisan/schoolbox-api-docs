<script setup lang="ts">
/**
 * Custom Participation Vue Component
 */
import { isAbortError, hasErrorMessage } from "@js/util/errorUtils";
import { extractErrorMessage } from "@js/util/sbx/fetchUtils";
import PastoralCountBadge from "@schoolboxLibrary/Pastoral/Count/vue/pastoral-count-badge.vue";
import BottomDrawer from "@schoolboxVue/components/bottomDrawer/bottom-drawer.vue";
import type { StudentPastoralCountBadge } from "@schoolboxLibrary/Pastoral/types/StudentPastoralCountBadge";
import type { Participant } from "../js/types/customParticipationTypes";

interface Props {
    allParticipants: Participant[];
    getStudentsPastoralCountsUrl: string;
}

const props = defineProps<Props>();

// show the checkboxes if there are more than one participant
const showCheckboxes = window.Vue.computed(() => props.allParticipants.length > 1);

// selecteList is the items that are currently selected
const selectedList = window.Vue.ref<number[]>([]);

// Computed property to check if all are selected
const allSelected = window.Vue.computed({
    get: () => props.allParticipants.every((participant) => selectedList.value.includes(participant.user.id)),
    set: (value) => {
        selectedList.value = value ? props.allParticipants.map((participant) => participant.user.id) : [];
    },
});
// Function to check if at least one is selected
const isIndeterminate = window.Vue.computed(() => {
    return selectedList.value.length > 0 && selectedList.value.length < props.allParticipants.length;
});

const loadError = window.Vue.ref<string | null>(null);
const studentPastoralCounts = window.Vue.ref<Record<string, StudentPastoralCountBadge[]>>({});

/**
 * Fetch the pastoral counts for the students
 */
async function getStudentPastoralCounts(): Promise<void> {
    const url = props.getStudentsPastoralCountsUrl.replace(
        "{studentIds}",
        props.allParticipants.map((participant) => participant.user.id).join(","),
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
                <tr v-for="participant in props.allParticipants" :key="participant.id">
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
                    <td></td>
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
                                <li class="separator">Manage</li>
                                <li><a class="icon-modify">Modify</a></li>
                                <li><a class="icon-staff-students">Exclude</a></li>
                                <li class="separator">Create</li>
                                <li><a class="icon-copy">Copy Activity</a></li>
                                <li><a class="icon-submissions">New Activity</a></li>
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
            <div class="modal-actions">
                <a title="Set goal" class="icon-modify button submit">Modify</a>
                <a title="Set goal" class="icon-staff-students button submit">Exclude</a>
                <a title="Set goal" class="icon-copy button submit">Copy Activity</a>
                <a title="Set goal" class="icon-submissions button submit">New Activity</a>
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
    a {
        margin: 0 1px;
    }
}
</style>
