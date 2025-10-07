<script lang="ts" setup>
import ImgGallery from "@js/resource/img-gallery.vue";
import StorageObject from "@schoolboxVue/components/storage-object.vue";
import type { LearningMoment } from "./evidenceTypes";

interface Props {
    momentEvidence: LearningMoment["evidence"];
}
const props = defineProps<Props>();
</script>

<template>
    <!-- Show the first evidence item as the featured media, any other evidence will show in the gallery -->
    <!-- TODO: preview on click -->
    <article v-if="props.momentEvidence.length > 0">
        <storage-object
            :storage="props.momentEvidence[0].storage"
            :filename="props.momentEvidence[0].filename"
            data-test="learning-moment-evidence-media"
        />
    </article>

    <img-gallery v-if="props.momentEvidence.length > 1" id="activity-feed" :files="props.momentEvidence" />

    <!-- TODO: files grid -->
</template>

<style scoped></style>
