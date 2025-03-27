<script setup lang="ts">
/**
 * Custom Participation Vue Component
 */
import type { Participant } from "../js/types/customParticipationTypes";

interface Props {
    allParticipants: Participant[];
}
const props = defineProps<Props>();

// show the checkboxes if there are more than one participant
const showCheckboxes = window.Vue.computed(() => props.allParticipants.length > 1);

// selecteList is the items that are currently selected
const selectedList = window.Vue.ref<number[]>([]);

// Computed property to check if all are selected
const allSelected = window.Vue.computed({
    get: () => props.allParticipants.every((item) => selectedList.value.includes(item.id)),
    set: (value) => {
        selectedList.value = value ? props.allParticipants.map((item) => item.id) : [];
    },
});

// Function to check if at least one is selected
const isIndeterminate = window.Vue.computed(() => {
    return selectedList.value.length > 0 && selectedList.value.length < props.allParticipants.length;
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
                            :value="participant.id"
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
                        <template v-if="participant.id">
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
                        <!-- <span class="meta">({{ student.synergy_id }})</span>
                        <span class="show-for-print"><br />{{ student.email }}</span
                        ><br />
                        <p class="meta inline-container">
                            <pastoral-count-badge
                                v-for="studentBadge in studentPastoralCounts[student.id]"
                                :key="studentBadge.id"
                                v-bind="studentBadge"
                            ></pastoral-count-badge>
                        </p> -->
                    </td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped></style>
