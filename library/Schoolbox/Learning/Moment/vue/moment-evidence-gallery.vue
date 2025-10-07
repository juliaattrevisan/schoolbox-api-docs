<script lang="ts" setup>
import { ResourceType } from "@js/resource/resourceUtils";
import ImgGallery from "@js/resource/img-gallery.vue";
import FileGrid from "@js/resource/file-grid.vue";
import StorageObject from "@schoolboxVue/components/storage-object.vue";
import type { LearningMoment } from "./evidenceTypes";

interface Props {
    id?: string;
    dataTest?: string;
    momentEvidence: LearningMoment["evidence"];
}
const props = withDefaults(defineProps<Props>(), {
    id: "learning-moment-evidence",
    dataTest: "learning-moment-evidence",
});

/**
 * show the first image, video, or audio to show as the featured media,
 * any other evidence will show in the galleries
 */
const featuredEvidenceTypes = [ResourceType.IMAGE, ResourceType.VIDEO, ResourceType.AUDIO];
const featuredEvidence = window.Vue.computed(() => {
    const firstMedia = props.momentEvidence.find((e) => featuredEvidenceTypes.includes(e.storage.type));
    return firstMedia || null;
});

/** show the image gallery if there are images that aren't the first evidence item */
const imageGalleryEvidence = window.Vue.computed(() => {
    const images = props.momentEvidence.filter((e) => e.storage._links.image);

    if (images.filter((e) => e !== featuredEvidence.value).length === 0) {
        return []; // there is only one image, and it's already the featured one
    }

    return images;
});

/** show the file grid if there are files of any type that aren't the first evidence item */
const fileGridEvidence = window.Vue.computed(() => {
    const files = props.momentEvidence;

    if (files.filter((e) => e !== featuredEvidence.value).length === 0) {
        return []; // there are no other files to show
    }

    return files;
});
</script>

<template>
    <!-- Show the first evidence item as the featured media, any other evidence will show in the gallery -->
    <!-- TODO: preview on click -->
    <article v-if="featuredEvidence">
        <storage-object
            :storage="featuredEvidence.storage"
            :filename="featuredEvidence.filename"
            :data-test="`${props.dataTest}-media`"
        />
    </article>

    <img-gallery v-if="imageGalleryEvidence.length > 0" :id="props.id" :files="imageGalleryEvidence" />
    <file-grid v-if="fileGridEvidence.length > 0" :id="props.id" :files="fileGridEvidence" class="l-mar-m--top" />
</template>

<style scoped></style>
