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

const imgGalleryEl = window.Vue.useTemplateRef<InstanceType<typeof ImgGallery>>("imgGalleryRef");

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
    return images.map((image) => ({ ...image, hidden: image === featuredEvidence.value }));
});

/** show the file grid if there are files of any type that aren't the first evidence item or in the image gallery */
const fileGridEvidence = window.Vue.computed(() => {
    return props.momentEvidence
        .filter((file) => file !== featuredEvidence.value)
        .filter((file) => !imageGalleryEvidence.value.map((i) => i.storage).includes(file.storage));
});

/**
 * @returns Whether the featured evidence can be clicked to open the lightbox (i.e. it's an image in the gallery)
 */
const canOpenFeaturedEvidenceLightbox = window.Vue.computed(() => {
    if (!featuredEvidence.value) {
        return false; // no featured evidence
    }

    if (!imageGalleryEvidence.value.map((i) => i.storage).includes(featuredEvidence.value.storage)) {
        return false; // the featured evidence isn't in the gallery
    }

    return true;
});

/**
 * When the user clicks on the featured evidence
 */
function onFeaturedEvidenceClick(): void {
    if (!canOpenFeaturedEvidenceLightbox.value) {
        return; // not clickable
    }

    if (!featuredEvidence.value) {
        throw new Error("No featured evidence to open");
    }

    if (!imgGalleryEl.value) {
        throw new Error("Image gallery ref not set");
    }

    imgGalleryEl.value.openImageLightbox(featuredEvidence.value);
}
</script>

<template>
    <!-- Show the first evidence item as the featured media, any other evidence will show in the gallery -->
    <article
        v-if="featuredEvidence"
        :style="{ cursor: canOpenFeaturedEvidenceLightbox ? 'pointer' : undefined }"
        @click="onFeaturedEvidenceClick"
    >
        <storage-object
            :storage="featuredEvidence.storage"
            :filename="featuredEvidence.filename"
            :data-test="`${props.dataTest}-media`"
        />
    </article>

    <img-gallery
        v-if="imageGalleryEvidence.length > 0"
        :id="props.id"
        ref="imgGalleryRef"
        :files="imageGalleryEvidence"
    />

    <file-grid v-if="fileGridEvidence.length > 0" :id="props.id" :files="fileGridEvidence" class="l-mar-m--top" />
</template>

<style scoped></style>
