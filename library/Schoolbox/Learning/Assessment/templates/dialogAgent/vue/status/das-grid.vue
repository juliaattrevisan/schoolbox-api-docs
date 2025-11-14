<script setup lang="ts">
import type { DialogAgentParticipant, DialogAgentScenarioCriteria } from "./dialogAgentStatusTypes";
import DasGridRow from "./das-grid-row.vue";

interface Props {
    criteria: DialogAgentScenarioCriteria[];
    participants: DialogAgentParticipant[];
}

const props = defineProps<Props>();
</script>

<template>
    <div class="dialog-agent-grid scrollable">
        <table>
            <thead>
                <tr>
                    <th class="u-sticky-left u-sticky-left--first"></th>
                    <th class="u-sticky-left u-sticky-left--offset-1 u-border-right"></th>
                    <th v-for="field in props.criteria" :key="field.id" class="field-col">{{ field.title }}</th>
                </tr>
            </thead>
            <tbody>
                <das-grid-row
                    v-for="participant in props.participants"
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

    /* force the nav's to be the width of the cell */
    .u-sticky-left--offset-1 {
        width: 50px;
    }
}
</style>
