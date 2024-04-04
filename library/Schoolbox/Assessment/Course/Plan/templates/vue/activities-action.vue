<script setup lang="ts">
import { Column } from "./unitPlannerConfig";
import { Unit } from "./unitPlannerTypes";
import ActivitiesCreateInner from "./activities-create-inner.vue";

interface Props {
    unit: Unit;
    column: Column;
}

const props = defineProps<Props>();
</script>

<template>
    <div v-if="props.unit.activities._links.create !== null" class="component-action">
        <section>
            <sbx-pop-out
                :content-id="`dropdown-unitplanner-activity-${props.column.id}-${props.unit.id}`"
                button-text="Create Activity"
                move-to="#unitplanner-dropdown-container"
                is-component-action
            ></sbx-pop-out>
            <activities-create-inner
                :content-id="`dropdown-unitplanner-activity-${props.column.id}-${props.unit.id}`"
                :create-url="props.unit.activities._links.create"
            ></activities-create-inner>
        </section>
    </div>
</template>

<style scoped>
.component-action section {
    border-bottom: 0;
    pointer-events: all;
}
</style>
