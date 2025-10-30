<script setup lang="ts">
/**
 * To display an empty state when there's no data to show
 */

const props = defineProps<{
    icon: string;
}>();
</script>

<template>
    <div class="empty-state-flex-centered f-no-margins">
        <i :class="props.icon"></i>
        <slot>
            <p>Nothing to display</p>
        </slot>
    </div>
</template>

<style scoped></style>
