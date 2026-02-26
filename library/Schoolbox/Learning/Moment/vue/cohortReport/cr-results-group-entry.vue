<script lang="ts" setup>
import { getFileIcon, ResourceType } from "@js/resource/resourceUtils";
import type { StorageFile } from "@js/resource/resourceUtils";
import { openFilePreview } from "@js/preview/previewUtils";
import { createLightbox, getStorageGallerySlides } from "@js/image-lightbox/imageLightboxEvent";
import StorageObject from "@schoolboxVue/components/storage-object.vue";
import UserPhoto from "@schoolboxVue/components/user/user-photo.vue";
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
const selectedEvidenceHash = window.Vue.ref<StorageFile["hash"] | null>(null);
const selectedEvidence = window.Vue.computed(() => {
    return props.entry.evidence.find((f) => f.storage.hash === selectedEvidenceHash.value) || null;
});

const selectableEvidence = window.Vue.computed(() => {
    return props.entry.evidence.filter((f) => f.storage.hash !== selectedEvidenceHash.value);
});

window.Vue.onMounted(() => {
    if (props.entry.evidence.length > 0) {
        selectedEvidenceHash.value = props.entry.evidence[0].storage.hash;
    }
});

/**
 * Open a lightbox for a specific file
 * If it's an image, it will include all other images in the moment's evidence
 * @param file The file to open
 * @param event The click event
 */
async function openLightbox(file: StorageFile, event: MouseEvent): Promise<void> {
    if (file.type === ResourceType.IMAGE && file._links.image) {
        event.preventDefault();
        /**
         * This is an image, so we can show multiple at once.
         * Due to limitations of preview.ts/our way of showing iframes/pdfs/etc in a modal,
         * we can't show multiple media types as part of the same lightbox, so only images are supported here for now
         */

        const allImageEvidence = props.entry.evidence.filter((e) => e.storage._links.image);

        const { slides, selectedIndex } = getStorageGallerySlides(
            allImageEvidence.map((f) => f.storage),
            file.hash,
        );

        const lightbox = await createLightbox("cr-results-group-entry-file", slides, {
            index: selectedIndex,
        });
        lightbox.openGallery();
        return;
    }

    if (file.type === ResourceType.AUDIO || file.type === ResourceType.VIDEO) {
        // videos/audio already have their own dedicated media players, nothing to do for those
        return;
    }

    // otherwise, figure out what to do via preview.ts if there's a fetch URL
    if (file._links.fetch) {
        if (event.ctrlKey || event.shiftKey || event.metaKey) {
            return; // the user is trying to open in a new tab/window, don't open the preview
        }

        event.preventDefault();
        await openFilePreview(file._links.fetch);
    }
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
                    :data-test="`learning-moment-status-${props.entry.currentState}`"
                >
                    {{ evidenceStatusLabels[props.entry.currentState] }}
                </span>
                <!-- To avoid padding changes, shove an &nbsp; in here when the status is published -->
                <template v-else>&nbsp;</template>
            </label>
            <component
                :is="props.entry.recipient._links.profile ? 'a' : 'span'"
                v-if="props.entry.recipient"
                :href="props.entry.recipient._links.profile || undefined"
            >
                <user-photo :user="props.entry.recipient" render="tiny" />
                <span class="l-pad-s--left">{{ props.entry.recipient.fullName }}</span>
            </component>
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
                    v-if="selectedEvidence !== null && selectedEvidence.storage.hash"
                    :key="selectedEvidence.storage.hash"
                    style="width: fit-content"
                    :style="{ cursor: selectedEvidence.storage.type === ResourceType.IMAGE ? 'pointer' : undefined }"
                    @click="($event) => openLightbox(selectedEvidence!.storage, $event)"
                >
                    <storage-object
                        :storage="selectedEvidence.storage"
                        :filename="selectedEvidence.filename || selectedEvidence.storage.name"
                        max-height="10rem"
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
                    v-for="evidence in props.entry.evidence"
                    :key="evidence.storage.hash"
                    class="cr-evidence-button l-flex--no-wrap l-flex-row l-gap-xs l-pad-xs"
                    :class="{ 'cr-evidence-button-selected': evidence.storage.hash === selectedEvidenceHash }"
                    :title="evidence.storage.name"
                    @click="selectedEvidenceHash = evidence.storage.hash"
                >
                    <img :src="getFileIcon(evidence.storage)" />
                    <span>{{ evidence.filename || evidence.storage.name }}</span>
                </div>
            </div>
        </div>

        <div v-if="props.entry.creator && props.entry.recipient?.id !== props.entry.creator.id" class="l-mar-s--top">
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
    border: 1px solid var(--sbx-color-accent, var(--form-border-color));

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
        border-color: var(--sbx-color-alert, $alert-color);
    }
}
</style>
