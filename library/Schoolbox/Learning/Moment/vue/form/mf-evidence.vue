<script setup lang="ts">
import FileDrop from "@js/resource/file-drop.vue";
import type { FileDropFile } from "@js/resource/fileDropTypes";
import type { AsyncUploadResponseSuccess } from "@js/resource/resourceUtils";
import type { Template } from "@js/editor/types/editorTypes";
import StorageObject from "@schoolboxVue/components/storage-object.vue";
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

const selectedEvidenceId = window.Vue.ref<number>(0);
const selectedEvidence = window.Vue.computed(() => {
    return mfStore.moment?.evidence[selectedEvidenceId.value] || null;
});

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
 * After evidence is uploaded
 */
function onEvidenceUploaded(): void {
    if (!mfStore.moment) return;

    mediaType.value = null;
    selectedEvidenceId.value = mfStore.moment.evidence.length - 1; // select the newly added evidence
}

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

    onEvidenceUploaded();
}

window.Vue.watch(hasRecordedData, async () => {
    // if there's recorded data, auto submit it
    if (hasRecordedData.value) {
        await uploadMediaRecording();
    }
});

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
async function deleteEvidence(index: number): Promise<void> {
    if (!mfStore.moment) {
        throw new Error("moment not found");
    }

    mfStore.moment.evidence.splice(index, 1);

    if (mfStore.moment.evidence.length === 0 && mediaType.value !== MediaType.TEXT) {
        // if there's no evidence left, default back to text mode
        await changeMediaType(MediaType.TEXT);
    }

    if (selectedEvidenceId.value >= mfStore.moment.evidence.length) {
        selectedEvidenceId.value = mfStore.moment.evidence.length - 1; // select the last evidence if the current one was deleted I guess
    }
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

    if (momentTypeParam && Object.values(MediaType).includes(momentTypeParam)) {
        await changeMediaType(momentTypeParam);
    } else if (mfStore.moment.evidence.length > 0) {
        mediaType.value = null; // already has evidence, don't show any media type
    } else {
        await changeMediaType(MediaType.TEXT); // no valid selection, default back to text
    }

    // select the latest evidence if there's any
    selectedEvidenceId.value = mfStore.moment.evidence.length - 1;
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
    if (mediaType.value === MediaType.TEXT) return true;

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
        onEvidenceUploaded();
    },
);
</script>

<template>
    <div v-if="mediaType === null || mediaType === MediaType.TEXT" class="share-work-buttons l-grid l-gap-m">
        <a class="icon-camera share-photo" title="Take a photo" @click.prevent="changeMediaType(MediaType.PHOTO)">
            Take a photo
        </a>
        <a class="icon-video share-video" title="Record a video" @click.prevent="changeMediaType(MediaType.VIDEO)">
            Record a video
        </a>
        <a class="icon-audio share-audio" title="Record audio" @click.prevent="changeMediaType(MediaType.AUDIO)">
            Record audio
        </a>
        <a class="icon-upload share-file" title="Upload file" @click.prevent="changeMediaType(MediaType.UPLOAD)">
            Upload file
        </a>
        <a
            v-if="!(showDescriptionEditor || MediaType.TEXT === mediaType)"
            class="icon-textbox share-text"
            title="Write something"
            @click.prevent="changeMediaType(MediaType.TEXT)"
        >
            Write something
        </a>
    </div>

    <div v-if="mediaType !== null && mediaType !== MediaType.TEXT">
        <!-- These inputs are just to satisfy evidence-media-recording, they don't do anything meaningful here -->
        <input id="mf-evidence-filename" type="hidden" />
        <input id="mf-evidence-storage" type="hidden" />

        <div v-if="mediaRecordingTypes.includes(mediaType)">
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
            <file-drop id="mf-evidence-file-drop" v-model:current-file="currentFileDropFile" auto-upload-to-storage />
        </div>
    </div>

    <div v-if="mfStore.moment !== null && mfStore.moment.evidence.length">
        <label> Attachments </label>
        <div class="l-grid l-grid--12 l-gap-m">
            <div class="large-6 small-12">
                <article
                    v-if="selectedEvidence !== null && selectedEvidence.storage.hash"
                    :key="selectedEvidence.storage.hash"
                >
                    <storage-object
                        :storage="selectedEvidence.storage"
                        :filename="selectedEvidence.filename"
                        data-test="learning-moment-evidence-media"
                    ></storage-object>
                </article>
            </div>

            <div class="large-6 small-12">
                <ul class="grid small-block-grid-1">
                    <li
                        v-for="evidence in [...mfStore.moment.evidence].reverse()"
                        :key="evidence.storage.hash"
                        class="actions-small-2"
                        :class="{ 'mf-evidence-file-selected': selectedEvidence === evidence }"
                    >
                        <div class="list-item">
                            <div class="card small-12">
                                <!-- TODO: we probably need a real preview of this, or at least a way to download it to see what it actually is -->
                                <a
                                    :href="evidence.storage._links.fetch"
                                    :title="evidence.filename"
                                    @click.prevent="
                                        selectedEvidenceId = mfStore.moment.evidence.findIndex((e) => e === evidence)
                                    "
                                >
                                    <img :src="`/ajax/mime/${evidence.storage.mime}`" class="mime" />
                                    <p>{{ evidence.filename }}</p>
                                </a>
                            </div>
                        </div>
                        <nav>
                            <a
                                v-if="evidence.storage._links.fetch"
                                class="icon-download no-text"
                                :href="evidence.storage._links.fetch"
                                title="Download"
                                download
                            ></a>
                            <a
                                class="icon-delete no-text"
                                href="#"
                                title="Delete Evidence"
                                @click.prevent="
                                    deleteEvidence(mfStore.moment.evidence.findIndex((e) => e === evidence))
                                "
                            ></a>
                        </nav>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div v-if="mfStore.moment && showDescriptionEditor">
        <!-- The description is required as long as there isn't other evidence -->
        <label for="mf-evidence-textarea" :class="{ required: isEvidenceRequired }"> Write something </label>
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
.share-work-buttons a {
    margin-bottom: 0; /* override this from the global styles for now */
}

.mf-evidence-file-selected {
    background-color: var(--accent-background);
}
</style>
