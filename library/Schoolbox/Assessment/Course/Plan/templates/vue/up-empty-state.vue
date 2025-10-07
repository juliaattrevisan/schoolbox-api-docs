<script setup lang="ts">
/**
 * Displayed when there's currently no Units linked to the Course
 */
import { useAddUnitsStore } from "./store/unitPlannerAddUnitsStore";

const addUnitsStore = useAddUnitsStore();

/**
 * To show the add units display
 */
function onCreateSelected(): void {
    if (!addUnitsStore.isOpen) {
        addUnitsStore.prompt();
    }
}
</script>

<template>
    <div class="row">
        <div class="small-12 island">
            <div class="empty-state-flex-centered">
                <i class="icon-unit"></i>
                <p>
                    This course currently does not have any units. To begin building this course start by
                    <button @click.prevent="onCreateSelected">creating a unit.</button>
                </p>
            </div>
        </div>
    </div>
</template>
