<script setup lang="ts">
/**
 * To be able to customise what's displayed on a user profile page
 */

import PeFields from "./pe-fields.vue";
import PeButtons from "./pe-buttons.vue";
import PeTabs from "./pe-tabs.vue";
import PeRoleType from "./pe-role-type.vue";
import PeNameFormat from "./pe-name-format.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";
import type { ProfileEditorPayload } from "./types/profileEditorTypes";

interface Props {
    payload: ProfileEditorPayload;
}

const props = defineProps<Props>();
const peStore = useProfileEditorStore();

window.Vue.onMounted(() => {
    peStore.init(props.payload.data.roleTypes, props.payload.data.fieldSettings, props.payload.data.tabSettings);

    // Is there the url hash a valid role type? select it if so or fall back to the first role type
    const roleTypeViaHash = peStore.roleTypes.find((roleType) => roleType.roleType === window.location.hash.slice(1));
    if (roleTypeViaHash) {
        peStore.setSelectedRoleType(roleTypeViaHash);
    } else if (peStore.roleTypes.length > 0) {
        peStore.setSelectedRoleType(peStore.roleTypes[0]);
    }
});
</script>

<template>
    <div class="small-12 island">
        <pe-role-type />
    </div>
    <div class="small-12 island">
        <pe-name-format />
    </div>
    <pe-fields />
    <pe-buttons />
    <pe-tabs />
</template>

<style scoped></style>
