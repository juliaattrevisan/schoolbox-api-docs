<script setup lang="ts">
import { cloneDeep } from "es-toolkit";
import locale from "@js/terminology/rosettaStone";
import type { DialogAgentResponseStatus } from "@schoolboxAssessment/Types/dialogAgent";
import { getResponseStatus } from "./dialogAgentStatusTypes";
import type {
    ApiDialogAgentActionResponse,
    DialogAgentParticipant,
    DialogAgentScenarioCriteria,
} from "./dialogAgentStatusTypes";
import DasGridRow from "./das-grid-row.vue";

interface Props {
    criteria: DialogAgentScenarioCriteria[];
    participants: DialogAgentParticipant[];
    filters: Record<DialogAgentResponseStatus, boolean>;
    participantSearchFilter: string;
}

const props = defineProps<Props>();
const emit = defineEmits(["resetFilters"]);

const participantsData = window.Vue.ref<DialogAgentParticipant[]>([]);

/**
 * Clone the participants list, we'll then be able to modify it based on actions from das-actions.vue
 * When the props change, this will re-run and reset the participants list again
 */
window.Vue.watch(
    () => props.participants,
    (newParticipants) => {
        participantsData.value = cloneDeep(newParticipants);
    },
    { immediate: true },
);

/**
 * Update the stored participant data after an action
 * @param participant The participant to update
 * @param data The updated data from the API to apply
 */
function applyParticipantUpdates(participant: DialogAgentParticipant, data: ApiDialogAgentActionResponse): void {
    const index = participantsData.value.findIndex((p) => p.id === participant.id);
    if (index === -1) {
        throw new Error("Unable to find participant to update"); // shouldn't get here
    }

    participantsData.value[index].response = data.response;
}

const filteredParticipants = window.Vue.computed(() => {
    return participantsData.value.filter((participant) => {
        if (!props.filters[getResponseStatus(participant.response)]) {
            return false;
        }

        if (props.participantSearchFilter.trim() !== "") {
            const searchLower = props.participantSearchFilter.toLowerCase();
            const possibleNames = [
                participant.user.firstName,
                participant.user.lastName,
                participant.user.fullName,
                participant.user.preferredName,
            ].filter((name) => name !== null && name !== undefined);

            if (!possibleNames.some((name) => name.toLowerCase().includes(searchLower))) {
                return false;
            }
        }

        return true;
    });
});
</script>

<template>
    <div v-if="participantsData.length === 0" class="empty-state">
        <sbx-icon name="dialog-agent" size="2.5rem"></sbx-icon>
        <p>This {{ locale.activity.singular() }} has no students participating.</p>
    </div>
    <div v-else-if="filteredParticipants.length === 0" class="empty-state">
        <sbx-icon name="staff-students" size="2.5rem"></sbx-icon>
        <p>
            No students match the current filters. Add another filter or
            <a href="#" @click.prevent="emit('resetFilters')">show everything</a>.
        </p>
    </div>
    <section v-else>
        <div class="dialog-agent-grid scrollable">
            <table>
                <thead>
                    <tr>
                        <th class="u-sticky-left u-sticky-left--first u-border-right"></th>
                        <th class="u-sticky-left u-sticky-left--offset-1 u-border-right">Status</th>
                        <th v-for="field in props.criteria" :key="field.id" class="field-col">{{ field.title }}</th>
                    </tr>
                </thead>
                <tbody>
                    <das-grid-row
                        v-for="participant in filteredParticipants"
                        :key="participant.id"
                        :participant="participant"
                        :criteria="props.criteria"
                        @update-response="(data) => applyParticipantUpdates(participant, data)"
                    />
                </tbody>
            </table>
        </div>
    </section>
</template>

<style scoped lang="scss">
.dialog-agent-grid {
    --sbx-table-u-sticky-width-first: 16.5rem;
    max-height: 90vh;

    .field-col {
        min-width: 18rem;
    }
}
</style>
