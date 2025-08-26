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
            <button :disabled="mfStore.isSaving" @click="() => mfStore.submitForm(false, 'draft')">
                Save for later
            </button>
        </li>
        <li v-if="relevantRoutes.reject">
            <button :disabled="mfStore.isSaving" @click="() => mfStore.reject()">Request update</button>
        </li>
        <li v-if="relevantRoutes.publish">
            <button :disabled="mfStore.isSaving" class="submit" @click="() => mfStore.publish()">Publish</button>
        </li>
        <!-- publish button takes precedence over submit if it exists for now, once route checks work correctly this may change to just an if -->
        <li v-else-if="relevantRoutes.submit">
            <button :disabled="mfStore.isSaving" class="submit" @click="() => mfStore.submitForm(false, 'submit')">
                Submit for approval
            </button>
        </li>
    </ul>
</template>

<style scoped></style>
