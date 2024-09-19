<script setup lang="ts">
/**
 * A component showing all available fields that can be added to the role type
 */

import PeFieldsAvailableItem from "./pe-fields-available-item.vue";
import { useProfileEditorStore } from "./store/profileEditorStore";

const peStore = useProfileEditorStore();
</script>

<template>
    <div v-if="peStore.selectedRoleType !== null" class="component l-mar-m--bottom">
        <div class="component-titlebar">
            <div class="actions-small-2">
                <div class="list-item">
                    <h2 title="Fields">Fields</h2>
                </div>
            </div>
        </div>
        <div class="pe-fields-palette">
            <div class="small-12 island" style="display: block">
                <section class="content">
                    <!-- grid of all fields as rectangles -->
                    <div class="l-grid l-grid--12">
                        <pe-fields-available-item
                            v-for="availableField in peStore.selectedRoleType.fields.available"
                            :key="availableField.field"
                            :field="availableField"
                        />
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
/* Undo the padding by .island */
.pe-fields-palette {
    margin-left: -1rem;
    margin-right: -1rem;
}
</style>
