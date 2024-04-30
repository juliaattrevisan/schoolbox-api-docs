<script setup lang="ts">
/**
 * Displayed when there's currently no Units linked to the Course
 */

import { useAddUnitStore } from "./store/unitPlannerAddUnitStore";

const addUnitStore = useAddUnitStore();

/**
 * To show the add unit display
 */
function onCreateSelected() {
    if (!addUnitStore.isOpen) {
        addUnitStore.prompt();
    }
}
</script>

<template>
    <div class="row">
        <div class="small-12 island">
            <div class="empty-state">
                <i class="icon-unit"></i>
                <p>
                    No Units are currently linked to this Course.
                    <a href="#create-unit" @click.prevent="onCreateSelected">Create Unit</a>
                </p>
            </div>
        </div>
    </div>
</template>
