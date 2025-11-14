<script setup lang="ts">
import type { DialogAgentResponseStatus } from "@schoolboxAssessment/Types/dialogAgent";
import { getResponseStatus } from "./dialogAgentStatusTypes";
import type { DialogAgentParticipant, DialogAgentScenarioCriteria } from "./dialogAgentStatusTypes";
import DasGridRow from "./das-grid-row.vue";

interface Props {
    criteria: DialogAgentScenarioCriteria[];
    participants: DialogAgentParticipant[];
    filters: Record<DialogAgentResponseStatus, boolean>;
}

const props = defineProps<Props>();

const filteredParticipants = window.Vue.computed(() => {
    return props.participants.filter((participant) => props.filters[getResponseStatus(participant.response)]);
});
</script>

<template>
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
