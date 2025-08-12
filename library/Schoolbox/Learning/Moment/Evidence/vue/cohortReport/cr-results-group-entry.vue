<script lang="ts" setup>
import { EvidenceStatus, evidenceStatusLabels } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import { useCohortReportStore } from "./store/cohortReportStore";
import type { CohortReportEntry } from "./store/cohortReportPayload";
import CrResultsGroupEntryActions from "./cr-results-group-entry-actions.vue";

interface Props {
    entry: CohortReportEntry;
    dataPrefix: string;
}

const props = defineProps<Props>();
const crStore = useCohortReportStore();

const inputId = window.Vue.computed(() => {
    return `${props.dataPrefix}-select-${props.entry.id}`;
});

const dropdownId = window.Vue.computed(() => {
    return `${props.dataPrefix}-dropdown-${props.entry.id}`;
});
</script>

<template>
    <div class="cr-result-entry l-pad-s" :class="`cr-result-entry--${props.entry.currentState}`">
        <div class="l-flex-row l-gap-s">
            <input
                :id="inputId"
                type="checkbox"
                :name="inputId"
                :checked="crStore.isEntrySelected(props.entry.id)"
                @change="crStore.toggleEntrySelection(props.entry.id)"
            />
            <label :for="inputId" class="checkbox-label l-flex__item--1">
                <!-- Only show status that isn't published, since that is the default state -->
                <span
                    v-if="props.entry.currentState !== EvidenceStatus.PUBLISHED"
                    class="label"
                    :class="`label--moment-${props.entry.currentState}`"
                >
                    {{ evidenceStatusLabels[props.entry.currentState] }}
                </span>
            </label>

            <nav class="c-nav-actions">
                <sbx-pop-out :content-id="dropdownId"></sbx-pop-out>
                <ul :id="dropdownId" class="f-dropdown" data-dropdown-content>
                    <cr-results-group-entry-actions :entry="props.entry" />
                </ul>
            </nav>
        </div>

        <!-- eslint-disable-next-line vue/no-v-html - this is scrubbed on the backend -->
        <p v-html="props.entry.body"></p>
    </div>
</template>

<style scoped lang="scss">
@import "@css/core/base/_colors.scss";

.cr-result-entry {
    border: 1px solid var(--form-border-color);

    &--draft {
        border-color: $neutral-foreground;
    }

    &--submitted {
        border-color: $warning-color;
    }

    &--rejected {
        border-color: $alert-color;
    }
}
</style>
