<script setup lang="ts">
/**
 * When selected displays a linked dropdown
 */

interface Props {
    dataDropdown: string; // the id of the linked dropdown to display
}
const props = defineProps<Props>();

/**
 * Initialise the dropdown
 */
window.Vue.onMounted(() => {
    console.warn("mounted");
});
</script>

<template>
    <button type="button" class="c-button c-button--icon-only c-button--no-bkg" aria-label="Open drop down">
        <svg
            version="1.2"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 16 16"
            width="16"
            height="16"
            aria-hidden="true"
            focusable="false"
            class="c-icon"
        >
            <path d="m6 0h4v4h-4z" />
            <path d="m6 6h4v4h-4z" />
            <path d="m6 12h4v4h-4z" />
        </svg>
    </button>
    <!--
        <a href="#" class="icon-more" data-dropdown="tab-control" title="Actions" id="all-actions" data-dropdown-initialized="true" aria-haspopup="true" aria-expanded="true"></a>
    -->
</template>

<style lang="scss" scoped>
@import "../../../www/static/css/core/ui/button/_c-button.scss";
</style>
