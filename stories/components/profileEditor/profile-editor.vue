<script setup lang="ts">
/**
 * To be able to customise what's displayed on a user profile page
 */

import PeFields from "./pe-fields.vue";
import PeRoleType from "./pe-role-type.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";
import type { ProfileEditorEndpoints } from "./types/profileEditorTypes";

interface Props {
    endpoints: ProfileEditorEndpoints;
}

const props = defineProps<Props>();
const peStore = useProfileEditorStore();

window.Vue.onMounted(async () => {
    await peStore.init(props.endpoints);
});
</script>

<template>
    <p v-if="peStore.isLoading">Loading...</p>

    <pe-role-type />
    <pe-fields />
</template>

<style scoped></style>
