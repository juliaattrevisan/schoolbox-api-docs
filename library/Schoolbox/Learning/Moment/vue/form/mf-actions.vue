<script setup lang="ts">
import { useMomentFormStore } from "./store/momentFormStore";

const mfStore = useMomentFormStore();

/**
 * The relevant routes to use,
 * If this is a create form, createRoutes takes precedence
 * otherwise the routes from the existing moment are used
 */
const relevantRoutes = window.Vue.computed(() => {
    if (mfStore.createRoutes !== null) {
        return mfStore.createRoutes;
    }

    if (mfStore.moment !== null) {
        return mfStore.moment._links;
    }

    return {};
});
</script>

<template>
    <!-- TODO: Make sure the route checks determine what we can actually do, this currently assumes they determine the button visibility and nothing else -->
    <ul class="flex-list buttons">
        <li v-if="relevantRoutes.draft">
            <button>Save for later</button>
        </li>
        <li v-if="relevantRoutes.reject">
            <button>Request update</button>
        </li>
        <li v-if="relevantRoutes.submit">
            <button class="submit">Submit for approval</button>
        </li>
        <li v-if="relevantRoutes.publish">
            <button class="submit">Publish</button>
        </li>
    </ul>
</template>

<style scoped></style>
