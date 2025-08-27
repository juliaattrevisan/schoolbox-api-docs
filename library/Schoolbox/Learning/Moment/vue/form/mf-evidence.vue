<script setup lang="ts">
import type { AsyncUploadResponseSuccess } from "@js/resource/resourceUtils";
import EvidenceMediaRecording from "@schoolboxLearningMomentEvidence/evidence-media-recording.vue";
import { useMomentFormStore } from "./store/momentFormStore";

const mfStore = useMomentFormStore();

enum MediaType {
    VIDEO = "video",
    AUDIO = "audio",
    PHOTO = "photo",
    UPLOAD = "upload",
    TEXT = "text",
}

const mediaRecordingTypes = [MediaType.VIDEO, MediaType.AUDIO, MediaType.PHOTO];
const mediaRecordingEl = window.Vue.useTemplateRef<InstanceType<typeof EvidenceMediaRecording>>("mediaRecordingRef");

const mediaType = window.Vue.ref<MediaType>(MediaType.AUDIO);

/**
 * Upload the current media,
 * either occurs when the user switches media types or if the user tries to submit the form
 */
async function uploadMedia(): Promise<void> {
    if (!mediaRecordingEl.value) {
        throw new Error("Media recording component not found");
    }

    if (!mfStore.moment) {
        throw new Error("moment not found");
    }

    if (!mediaRecordingEl.value.hasRecordedData()) {
        return; // nothing to upload
    }

    mediaRecordingEl.value.checkRecording();

    // need to override this type as evidence-media-recording.vue is not in TS
    const response = await (
        mediaRecordingEl.value.uploadMediaAndReturnResponse as () => Promise<AsyncUploadResponseSuccess | undefined>
    )();

    if (response === undefined) {
        return; // failed but already handled by evidence-media-recording.vue
    }

    mfStore.moment.evidence.push({
        storage: response.meta.file,
        filename: response.meta.name,
    });
}

/**
 * Change the selected media type
 * @param type The new media type
 */
async function changeMediaType(type: MediaType): Promise<void> {
    // If the current media type is a recording type, and there's recorded data, upload it first
    if (mediaRecordingTypes.includes(mediaType.value)) {
        await uploadMedia();
    }

    mediaType.value = type;
}

/**
 * Delete evidence from the moment
 * @param index The index of the evidence to delete
 */
function deleteEvidence(index: number): void {
    if (!mfStore.moment) {
        throw new Error("moment not found");
    }

    mfStore.moment.evidence.splice(index, 1);
}

// TODO: on submit of the overall form (not autosave), need to ensure any current media is uploaded first

/**
 * TODO: for the CK editor, need the following settings:
    {% if USER.role.staff %}hasTokens: true,{% endif %}
    {% if editorTemplates is defined %}
        templates: {{ editorTemplates|json_encode|raw }},
    {% endif %}
 */
</script>

<template>
    <div class="l-grid l-grid--12 l-gap-m">
        <div class="large-6 medium-8 small-12">
            <!-- These inputs are just to satisfy evidence-media-recording, they don't do anything meaningful here -->
            <input id="mf-evidence-filename" type="hidden" />
            <input id="mf-evidence-storage" type="hidden" />

            <evidence-media-recording
                v-if="mediaRecordingTypes.includes(mediaType)"
                ref="mediaRecordingRef"
                :key="`${mediaType}-${mfStore.moment?.evidence.length}`"
                :moment-type="mediaType"
                filename-id="mf-evidence-filename"
                storage-id="mf-evidence-storage"
            />
        </div>
        <div class="large-6 medium-4 small-12">
            <div class="share-work-buttons">
                <a
                    class="icon-camera share-photo"
                    title="Take a photo"
                    @click.prevent="changeMediaType(MediaType.PHOTO)"
                >
                    Take a photo
                </a>
                <a
                    class="icon-video share-video"
                    title="Record a video"
                    @click.prevent="changeMediaType(MediaType.VIDEO)"
                >
                    Record a video
                </a>
                <a
                    class="icon-audio share-audio"
                    title="Record audio"
                    @click.prevent="changeMediaType(MediaType.AUDIO)"
                >
                    Record audio
                </a>
                <a
                    class="icon-upload share-file"
                    title="Upload file"
                    @click.prevent="changeMediaType(MediaType.UPLOAD)"
                >
                    Upload file
                </a>
                <a
                    class="icon-textbox share-text"
                    title="Write something"
                    @click.prevent="changeMediaType(MediaType.TEXT)"
                >
                    Write something
                </a>
            </div>
        </div>
    </div>

    <div v-if="mfStore.moment !== null" class="l-flex-col l-gap-m l-mar-m--bottom">
        <div v-for="(evidence, index) in mfStore.moment.evidence" :key="index" class="v-card">
            <div class="l-flex-row l-flex--align-center l-gap-m l-pad-m">
                <div class="l-flex-col">
                    <img :src="`/ajax/mime/${evidence.storage.mime}`" />
                </div>
                <div class="l-flex-col l-flex--justify-center l-flex__item--1 f-no-margins">
                    <h3 class="">{{ evidence.filename }}</h3>
                </div>
                <nav class="c-nav-actions l-flex-row">
                    <a
                        class="icon-delete no-text"
                        href="#"
                        title="Delete Evidence"
                        @click.prevent="deleteEvidence(index)"
                    ></a>
                </nav>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* force the box shadow to have some top shadow too here, TODO: find a more sbx way... */
.v-card {
    box-shadow: 0px 2px 3px 2px rgba(0, 0, 0, 0.1);
}
</style>
