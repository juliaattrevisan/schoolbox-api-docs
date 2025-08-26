<script setup lang="ts">
import { useMomentFormStore } from "./store/momentFormStore";

const mfStore = useMomentFormStore();
</script>

<template>
    <!-- TODO: Make sure the route checks determine what we can actually do, this currently assumes they determine the button visibility and nothing else -->
    <ul v-if="mfStore.moment !== null" class="flex-list buttons">
        <li v-if="mfStore.moment._links.draft">
            <button>Save for later</button>
        </li>
        <li v-if="mfStore.moment._links.reject">
            <button>Request update</button>
        </li>
        <li v-if="mfStore.moment._links.submit">
            <button class="submit">Submit for approval</button>
        </li>
        <li v-if="mfStore.moment._links.publish">
            <button class="submit">Publish</button>
        </li>
    </ul>
</template>

<style scoped></style>
