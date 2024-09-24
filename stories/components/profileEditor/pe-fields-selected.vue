<script setup lang="ts">
/**
 * The selected fields for the current role type
 */

import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import PeFieldsSelectedItem from "./pe-fields-selected-item.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";

const peStore = useProfileEditorStore();
</script>

<template>
    <template v-if="peStore.selectedRoleType">
        <TransitionGroup :key="peStore.selectedRoleType" @enter="slideDownOnEnter" @leave="slideUpOnLeave">
            <pe-fields-selected-item
                v-for="field in peStore.selectedRoleType.fields.selected"
                :key="field.field"
                :field="field"
            />
        </TransitionGroup>
        <div v-if="peStore.selectedRoleType.fields.selected.length === 0" class="masonry-placeholder">
            You can drag fields into this space
        </div>
    </template>
</template>

<style scoped>
.masonry-placeholder {
    display: block;
    height: 51px;
}
</style>
