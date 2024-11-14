<script setup lang="ts">
/**
 * A component showing all available fields that can be added to the role type
 */

import type Sortable from "sortablejs";
import SortableWrapper from "@schoolboxVue/components/sortable-wrapper.vue";
import PeFieldsAvailableItem from "./pe-fields-available-item.vue";
import PeFieldsSelectedItem from "./pe-fields-selected-item.vue";
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

/**
 * A list of all category names that are available for the selected role type
 * Sorted alphabetically
 */
const fieldCategories = window.Vue.computed(() => {
    if (peStore.selectedRoleType === null) return [];
    const uniqueCategories = [...new Set(peStore.selectedRoleType.fields.available.map((field) => field.category))];
    return uniqueCategories.sort();
});

const selectedCategory = window.Vue.ref<string | null>(null);

const availableFieldsToShow = window.Vue.computed(() => {
    if (peStore.selectedRoleType === null) return [];
    const allFields = peStore.selectedRoleType.fields.available;

    // Need to group the fields by icon then sort alphabetically
    allFields.sort((a, b) => {
        // First, sort by `.icon`, null values come last
        if (a.icon === null && b.icon !== null) return 1;
        if (a.icon !== null && b.icon === null) return -1;
        if (a.icon !== null && b.icon !== null) {
            if (a.icon < b.icon) return -1;
            if (a.icon > b.icon) return 1;
        }

        // Then, sort by `.displayAs` alphabetically
        return a.displayAs.localeCompare(b.displayAs);
    });

    // If no category is selected, show all fields
    if (selectedCategory.value === null) {
        return allFields;
    }

    // Filter the fields by the selected category
    return allFields.filter((field) => field.category === selectedCategory.value);
});

// When the selected role type changes, reset the selected category
window.Vue.watch(
    () => peStore.selectedRoleType,
    () => {
        selectedCategory.value = fieldCategories.value[0] ?? null;
    },
    { immediate: true },
);
</script>

<template>
    <dl class="tabs">
        <dd v-for="category in fieldCategories" :key="category" :class="{ active: category === selectedCategory }">
            <a @click="selectedCategory = category">{{ category }}</a>
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
                    <pe-fields-selected-item
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
