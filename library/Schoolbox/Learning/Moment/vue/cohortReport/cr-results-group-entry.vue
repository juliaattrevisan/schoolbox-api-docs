<script lang="ts" setup>
import type { StorageFile } from "@js/resource/resourceUtils";
import { IMAGE_LIGHTBOX_CREATE } from "@js/image-lightbox/imageLightboxEvent";
import type { ImageLightboxCreateEvent } from "@js/image-lightbox/imageLightboxEvent";
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

/**
 * The current evidence item hash that should be shown
 */
const selectedEvidenceHash = window.Vue.ref<CohortReportEntry["files"][number]["hash"] | null>(null);
const selectedEvidence = window.Vue.computed(() => {
    return props.entry.files.find((f) => f.hash === selectedEvidenceHash.value) || null;
});

const selectableEvidence = window.Vue.computed(() => {
    return props.entry.files.filter((f) => f.hash !== selectedEvidenceHash.value);
});

window.Vue.onMounted(() => {
    if (props.entry.files.length > 0) {
        selectedEvidenceHash.value = props.entry.files[0].hash;
    }
});

/**
 * Show a lightbox for a specific file if it has an image link
 * @param file The file to show in the lightbox
 */
function spawnLightbox(file: StorageFile): void {
    if (!file._links.image) {
        return; // no link available, can't show lightbox
    }

    document.dispatchEvent(
        new CustomEvent<ImageLightboxCreateEvent["detail"]>(IMAGE_LIGHTBOX_CREATE, {
            detail: {
                targetKey: `ig-feed-lightbox`,
                options: {
                    dynamic: true,
                    dynamicEl: [{ src: file._links.image }],
                },
                onCreated: (gallery) => gallery.openGallery(),
            },
        }),
    );
}
</script>

<template>
    <div
        v-if="crStore.loadingEntries[props.entry.id]"
        class="cr-result-entry cr-result-entry--loading l-flex-col l-pad-s"
        :class="`cr-result-entry--${props.entry.currentState}`"
    >
        <div class="loader"></div>
    </div>

    <div
        v-else
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
                <!-- To avoid padding changes, shove an &nbsp; in here when the status is published -->
                <template v-else>&nbsp;</template>
            </label>
            <a :href="`/search/user/${props.entry.recipient.id}`">
                <user-photo :user="props.entry.recipient" render="tiny" />
                <span class="l-pad-s--left">{{ props.entry.recipient.fullName }}</span>
            </a>
        </div>

        <nav class="c-nav-actions">
            <sbx-pop-out :content-id="dropdownId"></sbx-pop-out>
        </nav>
        <ul :id="dropdownId" class="f-dropdown" data-dropdown-content>
            <cr-results-group-entry-actions :entry="props.entry" />
        </ul>

        <div class="l-flex__item--1 l-grid l-grid--12 l-gap-s l-mar-xs--top">
            <div class="small-12" :class="{ 'medium-8': selectableEvidence.length > 0 }">
                <article
                    v-if="selectedEvidence !== null && selectedEvidence.hash"
                    :key="selectedEvidence.hash"
                    style="width: fit-content"
                    @click="spawnLightbox(selectedEvidence)"
                >
                    <!-- TODO: this doesn't use the file name from the model, rather the base storage name... -->
                    <storage-object
                        :storage="selectedEvidence"
                        :filename="selectedEvidence.name"
                        style="max-height: 10rem"
                        maintain-dims
                        data-test="learning-moment-evidence-media"
                    ></storage-object>
                </article>

                <p class="l-mar-s--top">
                    <!-- eslint-disable-next-line vue/no-v-html - this is scrubbed on the backend -->
                    <span v-html="props.entry.blurb"></span>
                    <span v-if="props.entry.showReadMore" class="l-mar-s--top">
                        <a @click.prevent="crmStore.openModal(props.entry, CohortReportModalType.READ_MORE)">
                            READ MORE
                        </a>
                    </span>
                </p>
            </div>

            <div v-if="selectableEvidence.length > 0" class="small-12 medium-4 l-flex-col l-gap-s">
                <div
                    v-for="storage in props.entry.files"
                    :key="storage.hash"
                    class="cr-evidence-button l-flex--no-wrap l-flex-row l-gap-xs l-pad-xs"
                    :class="{ 'cr-evidence-button-selected': storage.hash === selectedEvidenceHash }"
                    :title="storage.name"
                    @click="selectedEvidenceHash = storage.hash"
                >
                    <img :src="`/ajax/mime/${storage.mime}`" />
                    <span>{{ storage.name }}</span>
                </div>
            </div>
        </div>

        <div v-if="props.entry.recipient.id !== props.entry.creator.id" class="l-mar-s--top">
            <user-photo
                :user="props.entry.creator"
                render="tiny"
                :title="`Created by ${props.entry.creator.fullName}`"
            />
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

    &.cr-evidence-button-selected {
        opacity: 0.5;
    }

    &:not(.cr-evidence-button-selected):hover {
        cursor: pointer;
        background-color: var(--content-ui-background);
    }
}

.cr-result-entry {
    border: 1px solid var(--form-border-color);

    &--loading {
        opacity: 0.5;
        pointer-events: none;
    }

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
