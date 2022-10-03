<script setup>

/**
 * The header containing the breadcrumbs and search input
 */

import { computed } from 'vue';
import { useUpdateNodeStore } from '../activeNode/store/mcUpdateNodeStore';
import { useAddSubsectionStore } from '../activeNode/store/mcAddSubsectionStore';
import McViewHeaderBreadcrumbs from './mc-view-header-breadcrumbs.vue';
import McViewHeaderSearchInput from './mc-view-header-search-input.vue';


const updateNodeStore = useUpdateNodeStore();
const addSubsectionStore = useAddSubsectionStore();

/**
 * Can't select the breadcrumbs or search input while updating or adding a node
 *
 * @returns { Boolean }
 */
const isUpdatingOrAdding = computed(() => updateNodeStore.isOpen || addSubsectionStore.isOpen);

</script>

<template>
   <mc-view-header-breadcrumbs :is-updating-or-adding="isUpdatingOrAdding" />
   <mc-view-header-search-input :is-updating-or-adding="isUpdatingOrAdding" />
</template>

<style scoped>

</style>