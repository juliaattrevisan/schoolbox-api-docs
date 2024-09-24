<script setup lang="ts">
/**
 * To be able to customise what's displayed on a user profile page
 */

import PePortraitImage from "./pe-portrait-image.vue";
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

    // Is there the url hash a valid role type? select it if so or fall back to the first role type
    const roleTypeViaHash = peStore.roleTypes.find((roleType) => roleType.name === window.location.hash.slice(1));
    if (roleTypeViaHash) {
        peStore.setSelectedRoleType(roleTypeViaHash);
    } else if (peStore.roleTypes.length > 0) {
        peStore.setSelectedRoleType(peStore.roleTypes[0]);
    }
});
</script>

<template>
    <p v-if="peStore.isLoading">Loading...</p>
    <pe-role-type />

    <div v-if="peStore.selectedRoleType" class="l-grid l-grid--12 l-gap-m l-mar-m--top">
        <div class="small-12 large-3">
            <pe-portrait-image />
        </div>
        <div class="small-12 large-9">
            <pe-fields />
        </div>
    </div>
</template>

<style scoped></style>
