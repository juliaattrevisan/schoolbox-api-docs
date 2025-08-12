<script lang="ts" setup>
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
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

const relevantUsers = window.Vue.computed(() => {
    // when creator/recipient are the same, only show one user
    if (props.entry.creator.id === props.entry.recipient.id) {
        return [{ ...props.entry.creator, label: props.entry.creator.fullname }];
    }

    // otherwise show both with meaningful labels
    return [
        { ...props.entry.recipient, label: `Received by ${props.entry.recipient.fullname}` },
        { ...props.entry.creator, label: `Created by ${props.entry.creator.fullname}` },
    ];
});
</script>

<template>
    <div class="cr-result-entry l-flex-col l-pad-s" :class="`cr-result-entry--${props.entry.currentState}`">
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

        <div class="l-flex__item--1">
            <!-- eslint-disable-next-line vue/no-v-html - this is scrubbed on the backend -->
            <p v-html="props.entry.body"></p>
        </div>

        <div class="l-flex-row l-mar-s--top l-gap-s">
            <user-photo v-for="user in relevantUsers" :key="user.id" :user="user" render="tiny" :title="user.label" />
        </div>
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
