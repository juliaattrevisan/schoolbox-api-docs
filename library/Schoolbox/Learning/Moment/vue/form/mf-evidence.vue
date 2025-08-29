<script setup lang="ts">
import FileDrop from "@js/resource/file-drop.vue";
import type { FileDropFile } from "@js/resource/fileDropTypes";
import { ResourceType } from "@js/resource/resourceUtils";
import type { AsyncUploadResponseSuccess } from "@js/resource/resourceUtils";
import type { Template } from "@js/editor/types/editorTypes";
import TextEditor from "@schoolboxVue/components/ckeditor/text-editor.vue";
import EvidenceMediaRecording from "@schoolboxLearningMomentEvidence/evidence-media-recording.vue";
import { useMomentFormStore } from "./store/momentFormStore";

interface Props {
    editorTemplates: Template[];
}

const props = defineProps<Props>();
const mfStore = useMomentFormStore();

enum MediaType {
    VIDEO = "video",
    AUDIO = "audio",
    PHOTO = "photo",
    UPLOAD = "upload",
    TEXT = "text",
}

// TODO: add limit (10)

const mediaRecordingTypes = [MediaType.VIDEO, MediaType.AUDIO, MediaType.PHOTO];
const mediaRecordingEl = window.Vue.useTemplateRef<InstanceType<typeof EvidenceMediaRecording>>("mediaRecordingRef");

const mediaType = window.Vue.ref<MediaType | null>(null);
const currentFileDropFile = window.Vue.ref<FileDropFile | null>(null);

/**
 * Whether there is recorded data in the media recording component
 * Used to determine if we need to upload it or if the user can switch to the same media type
 */
const hasRecordedData = window.Vue.computed(() => {
    if (!mediaRecordingEl.value) return false;
    return !!mediaRecordingEl.value.hasRecordedData();
});

/**
 * Upload the current media recording,
 * either occurs when the user switches media types or if the user tries to submit the form
 */
async function uploadMediaRecording(): Promise<void> {
    if (!mediaRecordingEl.value) {
        throw new Error("Media recording component not found");
    }

    if (!mfStore.moment) {
        throw new Error("moment not found");
    }

    if (!hasRecordedData.value) {
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
    if (!mfStore.moment) return;

    // If the current media type is a recording type, and there's recorded data, upload it first
    if (mediaType.value && mediaRecordingTypes.includes(mediaType.value)) {
        await uploadMediaRecording();
    }

    if (type === MediaType.TEXT && mfStore.moment.body === null) {
        mfStore.moment.body = ""; // ensure body is a string if switching to text so that the editor shows up
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
// TODO: on submit of the overall form (not autosave), need to validate that there's at least one evidence OR text - although you could just bypass that due to autosave I guess, lol

const editorSettings = window.Vue.computed(() => ({
    hasTokens: window.schoolboxUser.role.staff,
    templates: props.editorTemplates,
}));

window.Vue.onMounted(async () => {
    if (mfStore.moment === null) {
        console.warn("no moment found, cannot add evidence");
        return;
    }

    // derive the initial media type from the URL param "momentType"
    const urlParams = new URLSearchParams(window.location.search);
    const momentTypeParam = (urlParams.get("momentType")?.toLowerCase() as MediaType | null) ?? null;
    const firstEvidenceType = mfStore.moment.evidence[0]?.storage.type ?? null;

    // TODO: should actually select the first evidence and make it properly visible again...

    if (momentTypeParam && Object.values(MediaType).includes(momentTypeParam)) {
        await changeMediaType(momentTypeParam);
    } else if (firstEvidenceType === ResourceType.VIDEO) {
        await changeMediaType(MediaType.VIDEO);
    } else if (firstEvidenceType === ResourceType.AUDIO) {
        await changeMediaType(MediaType.AUDIO);
    } else if (firstEvidenceType === ResourceType.IMAGE) {
        await changeMediaType(MediaType.PHOTO);
    } else if (firstEvidenceType !== null) {
        await changeMediaType(MediaType.UPLOAD); // some other file type, default to upload
    } else {
        await changeMediaType(MediaType.TEXT); // default back to text
    }
});

/**
 * The "required" icon on the evidence inputs are only necessary if
 * there isn't already evidence or text attached to the moment
 */
const isEvidenceRequired = window.Vue.computed(() => {
    if (!mfStore.moment) return true;

    // TODO: I expect this is gonna change logic when the first evidence is shown pre-selected at the top when it can be

    const hasEvidence = mfStore.moment.evidence.length > 0;
    const hasText = (mfStore.moment.body ?? "").trim().length > 0;

    if (mediaType.value === MediaType.TEXT) {
        /**
         * If you have the TEXT mode selected, it's probably because you don't have any evidence
         * In case you do for some reason, check anyways
         *
         * This field is required if there's not already attached evidence
         */
        return !hasEvidence;
    }

    /**
     * For all other media types, the field is required if there's existing evidence or text
     */
    return !(hasEvidence || hasText);
});

/**
 * Track if the description editor has ever been shown
 * This is to avoid it being removed from existence if the user removes all text in it
 * and hasn't got the media type set to text
 */
const hasEverShownDescriptionEditor = window.Vue.ref(false);

/**
 * Unlike the other media types, you can only ever have one text field
 * It either shows up the top if you have text selected as the media type,
 * or it shows up as a "Description" field if you have another media type selected and there's already text in the body
 */
const showDescriptionEditor = window.Vue.computed(() => {
    if (!mfStore.moment) return false;

    // if the media type is text, don't show the description editor (as it's redundant)
    if (mediaType.value === MediaType.TEXT) return false;

    // otherwise, show it if there's already text in the body (someone has added text previously)
    const shouldShow = (mfStore.moment.body ?? "").trim().length > 0;

    if (shouldShow) {
        // yikes, side effect - i guess it's fine for now
        hasEverShownDescriptionEditor.value = true;
    }

    return shouldShow || hasEverShownDescriptionEditor.value;
});

/**
 * When a file is uploaded, force it to fully upload and add it to the evidence list
 * TODO: this probably shouldn't be the way we do this, but speeeeeeeeed
 */
window.Vue.watch(
    () => currentFileDropFile.value?.storageHash,
    () => {
        if (!mfStore.moment) {
            return;
        }

        if (!currentFileDropFile.value?.storageHash || !currentFileDropFile.value.storageFile) {
            return; // not yet uploaded
        }

        mfStore.moment.evidence.push({
            storage: currentFileDropFile.value.storageFile,
            filename: currentFileDropFile.value.file.name,
        });

        currentFileDropFile.value = null;
    },
);
</script>

<template>
    <div class="l-grid l-grid--12 l-gap-m">
        <div class="large-6 medium-8 small-12">
            <!-- These inputs are just to satisfy evidence-media-recording, they don't do anything meaningful here -->
            <input id="mf-evidence-filename" type="hidden" />
            <input id="mf-evidence-storage" type="hidden" />

            <div v-if="mediaType && mediaRecordingTypes.includes(mediaType)">
                <label for="mf-evidence-storage" :class="{ required: isEvidenceRequired }">
                    <template v-if="mediaType === MediaType.PHOTO"> Take a picture </template>
                    <template v-else-if="mediaType === MediaType.VIDEO"> Record something </template>
                    <template v-else-if="mediaType === MediaType.AUDIO"> Say something </template>
                </label>
                <evidence-media-recording
                    ref="mediaRecordingRef"
                    :key="`${mediaType}-${mfStore.moment?.evidence.length}`"
                    :moment-type="mediaType"
                    filename-id="mf-evidence-filename"
                    storage-id="mf-evidence-storage"
                />
            </div>

            <div v-else-if="mediaType === MediaType.UPLOAD">
                <label for="mf-evidence-file-drop" :class="{ required: isEvidenceRequired }"> Upload File </label>
                <!-- TODO for speed reasons i've used this, but it should be a proper file input... that's a bit more complex atm -->
                <file-drop
                    id="mf-evidence-file-drop"
                    v-model:current-file="currentFileDropFile"
                    auto-upload-to-storage
                />
            </div>

            <div v-else-if="mediaType === MediaType.TEXT && !showDescriptionEditor">
                <label for="mf-evidence-textarea" :class="{ required: isEvidenceRequired }"> Write something </label>
                <p class="meta">
                    Student detail tokens can be viewed when you press [. Tokens will be substituted with the students
                    details on creation.
                </p>
                <text-editor
                    v-if="mfStore.moment && typeof mfStore.moment.body === 'string'"
                    id="mf-evidence-textarea"
                    v-model:body="mfStore.moment.body"
                    :config="editorSettings"
                />
            </div>
        </div>
        <div class="large-6 medium-4 small-12">
            <label>
                &nbsp;
                <!-- literally just a spacer to align with the other side -->
            </label>
            <div class="share-work-buttons">
                <a
                    class="icon-camera share-photo"
                    title="Take a photo"
                    :disabled="(MediaType.PHOTO === mediaType && !hasRecordedData) || undefined"
                    @click.prevent="changeMediaType(MediaType.PHOTO)"
                >
                    Take a photo
                </a>
                <a
                    class="icon-video share-video"
                    title="Record a video"
                    :disabled="(MediaType.VIDEO === mediaType && !hasRecordedData) || undefined"
                    @click.prevent="changeMediaType(MediaType.VIDEO)"
                >
                    Record a video
                </a>
                <a
                    class="icon-audio share-audio"
                    title="Record audio"
                    :disabled="(MediaType.AUDIO === mediaType && !hasRecordedData) || undefined"
                    @click.prevent="changeMediaType(MediaType.AUDIO)"
                >
                    Record audio
                </a>
                <a
                    class="icon-upload share-file"
                    title="Upload file"
                    :disabled="MediaType.UPLOAD === mediaType || undefined"
                    @click.prevent="changeMediaType(MediaType.UPLOAD)"
                >
                    Upload file
                </a>
                <a
                    class="icon-textbox share-text"
                    title="Write something"
                    :disabled="!!(showDescriptionEditor || MediaType.TEXT === mediaType) || undefined"
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
                <!-- TODO: we probably need a real preview of this, or at least a way to download it to see what it actually is -->
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

    <div v-if="mfStore.moment && showDescriptionEditor">
        <!-- The description is required as long as there isn't other evidence -->
        <label for="mf-evidence-textarea" :class="{ required: isEvidenceRequired }"> Description </label>
        <p class="meta">
            Student detail tokens can be viewed when you press [. Tokens will be substituted with the students details
            on creation.
        </p>
        <text-editor
            v-if="typeof mfStore.moment.body === 'string'"
            id="mf-evidence-textarea"
            v-model:body="mfStore.moment.body"
            :config="editorSettings"
        />
    </div>
</template>

<style scoped>
/* force the box shadow to have some top shadow too here, TODO: find a more sbx way... */
.v-card {
    box-shadow: 0px 2px 3px 2px rgba(0, 0, 0, 0.1);
}
</style>
