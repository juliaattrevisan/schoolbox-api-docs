<script lang="ts" setup>
import { EvidenceStatus, evidenceStatusLabels } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import { useCohortReportStore } from "./store/cohortReportStore";
import type { CohortReportEntry } from "./store/cohortReportPayload";

interface Props {
    entry: CohortReportEntry;
    dataPrefix: string;
}

const props = defineProps<Props>();
const crStore = useCohortReportStore();

const inputId = window.Vue.computed(() => {
    return `${props.dataPrefix}-select-${props.entry.id}`;
});
</script>

<template>
    <input
        :id="inputId"
        type="checkbox"
        :name="inputId"
        :checked="crStore.isEntrySelected(props.entry.id)"
        @change="crStore.toggleEntrySelection(props.entry.id)"
    />
    <label :for="inputId" class="checkbox-label">
        <!-- Only show status that isn't published, since that is the default state -->
        <span
            v-if="props.entry.currentState !== EvidenceStatus.PUBLISHED"
            class="label"
            :class="`label--moment-${props.entry.currentState}`"
        >
            {{ evidenceStatusLabels[props.entry.currentState] }}
        </span>
    </label>

    <!-- eslint-disable-next-line vue/no-v-html -- this is scrubbed on the backend -->
    <p v-html="props.entry.body"></p>
</template>

<style scoped></style>
