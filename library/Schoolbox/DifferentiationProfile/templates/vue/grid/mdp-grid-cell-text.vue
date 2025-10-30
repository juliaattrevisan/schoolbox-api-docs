<script setup lang="ts">
/**
 * Display text cell for a student in the MDP grid
 */

import EmptyState from "@schoolboxVue/components/empty-state.vue";
import type { Text } from "../../js/types/manageDifferentiationProfilesTypes";

const props = defineProps<{
    text: Text;
}>();
</script>

<template>
    <empty-state v-if="props.text === null" icon="icon-textbox">
        <button>Add content to this text area</button>
    </empty-state>
    <div v-else class="l-pad-s">
        <p>{{ props.text }}</p>
    </div>
</template>

<style scoped></style>
