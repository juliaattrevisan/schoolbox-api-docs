<script setup lang="ts">
/**
 * Load in pastoral flags for a group of students and display in specified container elements
 */

import { apiRequest } from "@js/util/sbx/apiUtils";
import type { Routes, Config } from "@schoolboxLibrary/Pastoral/types/PastoralRecord";
import type { PastoralFlag } from "@schoolboxLibrary/Pastoral/types/PastoralFlag";
import PastoralFlags from "@schoolboxLibrary/Pastoral/vue/flags/pastoral-flags.vue";
import { hasErrorMessage } from "@js/util/errorUtils";

const props = defineProps<{
    url: string;
    pastoralConfig: Config;
    pastoralRoutes: Routes;
}>();

/**
 * To pass through to the pastoral-records-modal that displays when a flag is selected
 */
window.Vue.provide("pastoralConfig", props.pastoralConfig);
window.Vue.provide("pastoralRoutes", props.pastoralRoutes);

const payload = window.Vue.ref<Record<string, PastoralFlag[]> | null>(null); // to store the loaded flags

/**
 * Load the pastoral flags from the provided URL
 */
async function loadFlags(): Promise<void> {
    try {
        const response = await apiRequest<Record<string, PastoralFlag[]>>({
            url: props.url,
            method: "GET",
        });
        if (!response.success) {
            throw new Error(response.message || "Failed to load data");
        }
        // check for expected structure
        if (typeof response.data !== "object" || response.data === null) {
            throw new Error("Unexpected response format");
        }
        payload.value = response.data;
    } catch (error) {
        if (error instanceof Error && hasErrorMessage(error)) {
            console.error(error.message);
            return;
        }
        console.error(error);
    }
}

/**
 * Load the flags when the component is mounted
 */
window.Vue.onMounted(() => {
    void loadFlags();
});
</script>

<template>
    <template v-if="payload">
        <template v-for="(flags, studentId) in payload" :key="`flags-container-${studentId}`">
            <teleport :to="`#pastoral-flags-container-${studentId}`">
                <pastoral-flags :user-id="Number(studentId)" :flags="flags" />
            </teleport>
        </template>
    </template>
</template>

<style scoped></style>
