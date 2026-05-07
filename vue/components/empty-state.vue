<script setup lang="ts">
/**
 * To display an empty state when there's no data to show
 */

import { computed } from "vue";

const props = defineProps<{
    icon: string;
}>();

const iconName = computed(() => props.icon.replace(/^icon-/, ""));
</script>

<template>
    <div class="empty-state-flex-centered f-no-margins">
        <sbx-icon :name="iconName" size="2.5rem"></sbx-icon>
        <slot>
            <p>Nothing to display</p>
        </slot>
    </div>
</template>

<style scoped></style>
