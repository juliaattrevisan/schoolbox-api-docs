<script setup lang="ts">
/**
 * Provide access to the unit actions for a unit cell in the unit planner,
 * displaying a dropdown with modify/delete options when applicable.
 */

import { getReturnUrl } from "@js/util/sbx/urlUtils";
import type { Unit } from "./unitPlannerTypes";

const props = defineProps<{
    unit: Unit;
}>();
</script>

<template>
    <div v-if="props.unit._routes.modify || props.unit._routes.delete" class="component-action">
        <section>
            <sbx-pop-out
                :content-id="`dropdown-unit-${props.unit.id}-modify`"
                button-text="Manage"
                move-to="#unitplanner-dropdown-container"
                is-component-action
            ></sbx-pop-out>
            <ul :id="`dropdown-unit-${props.unit.id}-modify`" class="f-dropdown" data-dropdown-content>
                <li v-if="props.unit._routes.modify">
                    <a class="icon-modify" :href="getReturnUrl(props.unit._routes.modify)"> Modify </a>
                </li>
                <li v-if="props.unit._routes.delete">
                    <a class="icon-delete" :href="getReturnUrl(props.unit._routes.delete)"> Delete </a>
                </li>
            </ul>
        </section>
    </div>
</template>

<style scoped>
.component-action section {
    border-bottom: 0;
    pointer-events: all; /* to allow the button to sit above the overlay */
}
</style>
