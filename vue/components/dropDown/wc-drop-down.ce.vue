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
    <button>
        <span>{{ props.dataDropdown }}</span>
    </button>
    <!--
        <a href="#" class="icon-more" data-dropdown="tab-control" title="Actions" id="all-actions" data-dropdown-initialized="true" aria-haspopup="true" aria-expanded="true"></a>
    -->
</template>
