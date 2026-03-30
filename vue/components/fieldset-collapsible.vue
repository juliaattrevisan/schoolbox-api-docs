<script setup lang="ts">
/**
 * Collapsible section. Mostly equivalent to using $(...).collapsable() with a fieldset
 */

interface Props {
    initiallyExpanded?: boolean;
    /**
     * Depending on what is being rendered, you may wish to delay rendering the components inside the content slot
     * until the fieldset is expanded for the first time so that elements have the chance to calculate their
     * dimensions correctly.
     */
    delayContentRender?: boolean;
}
const props = defineProps<Props>();

const isExpanded = defineModel<boolean>("expanded", { default: false });
const hasBeenExpanded = window.Vue.ref(isExpanded.value);

/**
 * Toggle the expanded state
 */
function toggleExpanded(): void {
    isExpanded.value = !isExpanded.value;
    if (isExpanded.value) {
        hasBeenExpanded.value = true;
    }
}

window.Vue.onMounted(() => {
    if (props.initiallyExpanded && !isExpanded.value) {
        toggleExpanded();
    }
});
</script>

<template>
    <fieldset class="content collapsible">
        <legend :class="isExpanded ? 'icon-up-arrow' : 'icon-down-arrow'" @click="toggleExpanded">
            <slot name="legend"></slot>
        </legend>

        <div class="l-grid" :style="{ 'grid-template-rows': isExpanded ? '1fr' : '0fr' }">
            <div class="collapsible-inner">
                <slot v-if="!props.delayContentRender || hasBeenExpanded" name="content"></slot>
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
