<script setup lang="ts">
/**
 * A component showing all available fields that can be added to the role type
 */

import type Sortable from "sortablejs";
import SortableWrapper from "@schoolboxVue/components/sortable-wrapper.vue";
import PeFieldsAvailableItem from "./pe-fields-available-item.vue";
import PeFieldsActiveItem from "./pe-fields-active-item.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";

const peStore = useProfileEditorStore();

const sortableOptions = window.Vue.computed<Sortable.Options>(() => ({
    // Link the available fields sortable to the selected fields sortable so that we can drag between them
    group: {
        name: `fields-${peStore.selectedRoleType ? peStore.selectedRoleType.roleType : "-"}`,
        pull: "clone", // dragging out of the available fields will create a clone
        put: false, // can't drag into the available fields
    },
    // We use this to force the html5 drag and drop logic to be ignored and use the fallback so we can style it
    forceFallback: true,
    // The class to apply to the clone when dragging
    fallbackClass: "dragged-item",
    // Prevent dragging on fields that are already selected
    filter: "[data-pe-prevent-drag]",
    // How long the animation should take when items shift
    animation: 200,
    // Prevent sorting inside the available fields list
    sort: false,
    // Removes the clone element when it is not showing, rather than just hiding it.
    removeCloneOnHide: true,
}));

const fieldCategories = window.Vue.computed(() => {
    if (peStore.selectedRoleType === null) return [];
    const categoryMap: Record<string, number> = {};

    // Count occurrences of each category
    peStore.selectedRoleType.fields.available.forEach((field) => {
        categoryMap[field.category] = (categoryMap[field.category] || 0) + 1;
    });

    // Convert the category map into an array and sort reverse-alphabetically by category (so "Internal" appears first for now...)
    return Object.entries(categoryMap)
        .map(([category, count]) => ({ category, count }))
        .sort((a, b) => b.category.localeCompare(a.category));
});

const selectedCategory = window.Vue.ref<(typeof fieldCategories.value)[0] | null>(null);

const availableFieldsToShow = window.Vue.computed(() => {
    if (peStore.selectedRoleType === null) return [];
    const allFields = peStore.selectedRoleType.fields.available;

    // If there is only one category, show all fields
    const category = selectedCategory.value;
    if (category === null || fieldCategories.value.length <= 1) return allFields;

    // Otherwise, filter the fields by the selected category
    return allFields.filter((field) => field.category === category.category);
});

window.Vue.onMounted(() => {
    // Set the selected category to the first one
    const firstCategory = fieldCategories.value[0];
    if (firstCategory) {
        selectedCategory.value = firstCategory;
    }
});
</script>

<template>
    <dl v-if="fieldCategories.length > 1" class="tabs">
        <dd
            v-for="category in fieldCategories"
            :key="category.category"
            :class="{ active: category.category === selectedCategory?.category }"
        >
            <a @click="selectedCategory = category">{{ category.category }} ({{ category.count }})</a>
        </dd>
    </dl>
    <section v-if="peStore.selectedRoleType !== null" class="pe-fields-palette">
        <sortable-wrapper
            :list="availableFieldsToShow"
            :item-key="(item) => item.field"
            :options="sortableOptions"
            class="l-grid l-grid--4-col"
        >
            <template #item="item">
                <div :data-pe-field="item.element.field">
                    <pe-fields-available-item :field="item.element" class="pe-fields-available-item" />
                    <pe-fields-active-item
                        v-if="item.isDragging"
                        :field="item.element"
                        :dragging="true"
                        class="pe-dragged-selected-item"
                    />
                </div>
            </template>
        </sortable-wrapper>
    </section>
</template>

<style scoped>
/* Allow the opacity of the fields to show through, rather than the default white */
.pe-fields-palette {
    background: transparent;
}

/* Hide the selected preview while we are still in the palette area */
.pe-fields-palette .pe-dragged-selected-item {
    display: none !important;
}
</style>
