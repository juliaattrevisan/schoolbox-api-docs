<script setup lang="ts">
/**
 * Displayed when there's currently no Units linked to the Course
 */

import locale from "@js/terminology/rosettaStone";
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
                <sbx-icon name="unit"></sbx-icon>
                <p>
                    This course currently does not have any {{ locale.unit.translate(true, false) }}. To begin building
                    this course start by
                    <button data-test="up-empty-state-create" @click.prevent="onCreateSelected">
                        creating a {{ locale.unit.translate(false, false) }}.
                    </button>
                </p>
            </div>
        </div>
    </div>
</template>
