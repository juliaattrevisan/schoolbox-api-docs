<script setup lang="ts">
import type { LearningMoment } from "@schoolboxLearningMomentEvidence/evidenceTypes";

interface Props {
    moment: LearningMoment;
}

const props = defineProps<Props>();
</script>

<template>
    <!-- TODO: Make sure the route checks determine what we can actually do, this currently assumes they determine the button visibility and nothing else -->
    <ul class="flex-list buttons">
        <li v-if="moment._links.draft">
            <button>Save for later</button>
        </li>
        <li v-if="moment._links.reject">
            <button>Request update</button>
        </li>
        <li v-if="moment._links.submit">
            <button class="submit">Submit for approval</button>
        </li>
        <li v-if="moment._links.publish">
            <button class="submit">Publish</button>
        </li>
    </ul>
</template>

<style scoped></style>
