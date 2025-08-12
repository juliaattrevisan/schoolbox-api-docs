<script lang="ts" setup>
import StorageObject from "@schoolboxVue/components/storage-object.vue";
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
import { EvidenceStatus, evidenceStatusLabels } from "@schoolboxLearningMomentEvidence/evidenceTypes";
import { useCohortReportStore } from "./store/cohortReportStore";
import type { CohortReportEntry } from "./store/cohortReportPayload";
import CrResultsGroupEntryActions from "./cr-results-group-entry-actions.vue";
import { CohortReportModalType, useCohortReportModalStore } from "./store/cohortReportModalStore";

interface Props {
    entry: CohortReportEntry;
    dataPrefix: string;
}

const props = defineProps<Props>();
const crStore = useCohortReportStore();
const crmStore = useCohortReportModalStore();

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

/**
 * The current evidence item hash that should be shown
 */
const selectedEvidenceHash = window.Vue.ref<CohortReportEntry["files"][number]["hash"] | null>(null);
const selectedEvidence = window.Vue.computed(() => {
    return props.entry.files.find((f) => f.hash === selectedEvidenceHash.value) || null;
});

window.Vue.onMounted(() => {
    if (props.entry.files.length > 0) {
        selectedEvidenceHash.value = props.entry.files[0].hash;
    }
});
</script>

<template>
    <div
        class="cr-result-entry l-flex-col l-pad-s actions-small-1"
        :class="`cr-result-entry--${props.entry.currentState}`"
    >
        <div>
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
        </div>

        <nav class="c-nav-actions">
            <sbx-pop-out :content-id="dropdownId"></sbx-pop-out>
        </nav>
        <ul :id="dropdownId" class="f-dropdown" data-dropdown-content>
            <cr-results-group-entry-actions :entry="props.entry" />
        </ul>

        <div class="l-flex__item--1 l-grid l-grid--12 l-gap-s l-mar-xs--top">
            <div class="small-12 medium-8">
                <article v-if="selectedEvidence !== null && selectedEvidence.hash">
                    <storage-object
                        :storage="selectedEvidence"
                        :filename="selectedEvidence.name"
                        style="max-height: 10rem"
                        data-test="learning-moment-evidence-media"
                    ></storage-object>
                </article>

                <p>
                    <!-- eslint-disable-next-line vue/no-v-html - this is scrubbed on the backend -->
                    <span v-html="props.entry.blurb"></span>
                    <span v-if="props.entry.showReadMore" class="l-mar-s--top">
                        <a @click.prevent="crmStore.openModal(props.entry, CohortReportModalType.READ_MORE)">
                            READ MORE
                        </a>
                    </span>
                </p>
            </div>

            <div class="small-12 medium-4 l-flex-col l-gap-s">
                <div
                    v-for="storage in props.entry.files.filter((f) => f.hash !== selectedEvidenceHash)"
                    :key="storage.hash"
                    class="cr-evidence-button l-flex--no-wrap l-flex-row l-gap-xs l-pad-xs"
                    :title="`${storage.name}.${storage.type}`"
                    @click="selectedEvidenceHash = storage.hash"
                >
                    <img :src="`/ajax/mime/${storage.mime}`" />
                    <span>{{ storage.name }}.{{ storage.type }}</span>
                </div>
            </div>
        </div>

        <div class="l-flex-row l-mar-s--top l-gap-s">
            <user-photo v-for="user in relevantUsers" :key="user.id" :user="user" render="tiny" :title="user.label" />
        </div>
    </div>
</template>

<style scoped lang="scss">
@import "@css/core/base/_colors.scss";

.cr-evidence-button {
    box-shadow: 0px 4px 4px 0px rgba(0, 0, 0, 0.25);

    img {
        width: 1.5rem;
    }

    span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    &:hover {
        cursor: pointer;
        background-color: var(--content-ui-background);
    }
}

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
