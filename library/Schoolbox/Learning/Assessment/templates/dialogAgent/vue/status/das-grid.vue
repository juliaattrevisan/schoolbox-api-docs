<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import type { DialogAgentResponseStatus } from "@schoolboxAssessment/Types/dialogAgent";
import { getResponseStatus } from "./dialogAgentStatusTypes";
import type { DialogAgentParticipant, DialogAgentScenarioCriteria } from "./dialogAgentStatusTypes";
import DasGridRow from "./das-grid-row.vue";

interface Props {
    criteria: DialogAgentScenarioCriteria[];
    participants: DialogAgentParticipant[];
    filters: Record<DialogAgentResponseStatus, boolean>;
    participantSearchFilter: string;
}

const props = defineProps<Props>();
const emit = defineEmits(["resetFilters"]);

const filteredParticipants = window.Vue.computed(() => {
    return props.participants.filter((participant) => {
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
    <div v-if="props.participants.length === 0" class="empty-state">
        <i class="icon-dialog-agent"></i>
        <p>This {{ locale.activity.singular() }} has no students participating.</p>
    </div>
    <div v-else-if="filteredParticipants.length === 0" class="empty-state">
        <i class="icon-staff-students"></i>
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
