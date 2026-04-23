<script setup lang="ts">
/**
 * Provide access to the unit actions for a unit cell in the unit planner,
 * displaying a dropdown with modify/delete options when applicable.
 */

import { getReturnUrl } from "@js/util/sbx/urlUtils";
import locale from "@js/terminology/rosettaStone";
import type { Unit } from "./unitPlannerTypes";
import { useDeleteUnitsStore } from "./store/unitPlannerDeleteUnitStore";

const props = defineProps<{
    unit: Unit;
}>();

/**
 * Display the delete unit prompt
 */
function onDeleteUnit(): void {
    useDeleteUnitsStore().prompt(props.unit);
}
</script>

<template>
    <div
        v-if="
            props.unit._routes.modify ||
            props.unit._routes.delete ||
            props.unit._routes.courseMarkbook ||
            props.unit._routes.importIntoClasses
        "
        class="component-action"
    >
        <section>
            <sbx-pop-out
                :content-id="`dropdown-unit-${props.unit.id}-modify`"
                button-text="Manage"
                move-to="#unitplanner-dropdown-container"
                :data-test="`sbx-pop-out-unit-cell-unit-action-${props.unit.id}`"
                is-component-action
            ></sbx-pop-out>
            <ul :id="`dropdown-unit-${props.unit.id}-modify`" class="f-dropdown" data-dropdown-content>
                <li v-if="props.unit._routes.courseMarkbook">
                    <a
                        class="sbx-button--has-icon"
                        :data-test="`up-unit-cell-unit-action-${props.unit.id}-course-link`"
                        :href="getReturnUrl(props.unit._routes.courseMarkbook!)"
                        ><sbx-icon name="curriculum"></sbx-icon>Course Markbook
                    </a>
                </li>
                <li v-if="props.unit._routes.importIntoClasses">
                    <a
                        :data-test="`up-unit-cell-unit-action-${props.unit.id}-import-link`"
                        class="sbx-button--has-icon"
                        :href="getReturnUrl(props.unit._routes.importIntoClasses!)"
                        ><sbx-icon name="import"></sbx-icon>Import into {{ locale.class.translate(true, true) }}
                    </a>
                </li>
                <li v-if="props.unit._routes.modify">
                    <a
                        :data-test="`up-unit-cell-unit-action-${props.unit.id}-modify-link`"
                        class="sbx-button--has-icon"
                        :href="getReturnUrl(props.unit._routes.modify!)"
                        ><sbx-icon name="modify"></sbx-icon>Modify</a
                    >
                </li>
                <!-- prettier-ignore -->
                <li v-if="props.unit._routes.delete">
                    <!-- we ignore this li as the formatting is adding whitespace -->
                    <button
                        :data-test="`up-unit-cell-unit-action-${props.unit.id}-delete-button`"
                        class="sbx-button--has-icon"
                        @click.prevent="onDeleteUnit"
                    ><sbx-icon name="delete"></sbx-icon>Delete
                    </button>
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
