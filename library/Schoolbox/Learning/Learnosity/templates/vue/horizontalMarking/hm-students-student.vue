<script setup lang="ts">
/**
 * Display a single student's responses
 */

import type { Student } from "./types/Student";

interface Props {
    student: Student; //
}
const props = defineProps<Props>();

const isOpen = window.Vue.ref(false); // whether the filters are shown

/**
 * To show/hide the filters when selected
 */
function onToggleSelected() {
    isOpen.value = !isOpen.value;
}

/**
 * To show/hide the filters container
 * @returns {string} - the height of the sub types container
 */
const containerHeight = window.Vue.computed(() => {
    return isOpen.value ? "1fr" : "0fr";
});
</script>

<template>
    <div class="c-island l-flex-col l-pad-m">
        <div class="l-flex-row l-gap-m f-no-margins">
            <button
                :class="`toggle-button ${isOpen ? 'icon-up-arrow' : 'icon-down-arrow'}`"
                @click.prevent="onToggleSelected"
            ></button>
            <h2>{{ props.student.name }}</h2>
        </div>
        <div class="response l-grid">
            <div class="response__inner-container">
                <div :id="props.student.elementId"></div>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.toggle-button {
    background: none;
    color: var(--content-ui-foreground);
}
.response {
    background-color: var(--foreground-colour);
    grid-template-rows: v-bind(containerHeight);
    transition: grid-template-rows 300ms;
    &__inner-container {
        overflow: hidden;
    }
}
</style>
