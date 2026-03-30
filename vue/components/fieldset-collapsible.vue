<script setup lang="ts">
/**
 * Collapsible section. Mostly equivalent to using $(...).collapsable() with a fieldset
 */

const isExpanded = defineModel<boolean>("expanded", { default: false });
</script>

<template>
    <fieldset class="content collapsible">
        <legend :class="isExpanded ? 'icon-up-arrow' : 'icon-down-arrow'" @click="isExpanded = !isExpanded">
            <slot name="legend"></slot>
        </legend>

        <div class="l-grid" :style="{ 'grid-template-rows': isExpanded ? '1fr' : '0fr' }">
            <div class="collapsible-inner">
                <slot name="content"></slot>
            </div>
        </div>
    </fieldset>
</template>

<style scoped>
.l-grid {
    transition: grid-template-rows 300ms;
}

.collapsible-inner {
    overflow: hidden;
}
</style>
