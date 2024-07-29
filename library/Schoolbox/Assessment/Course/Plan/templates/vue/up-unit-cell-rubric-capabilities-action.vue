<script setup lang="ts">
/**
 * The component-action for a rubric capabilities cell on the unit planner
 * Currently just links to the manage rubric page
 */
import locale from "@js/terminology/rosettaStone";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import type { Unit } from "./unitPlannerTypes";

interface Props {
    unit: Unit;
}

const props = defineProps<Props>();
</script>

<template>
    <div
        v-if="props.unit.rubricCapabilities._routes.manageCourse || props.unit.rubricCapabilities._routes.manageUnit"
        class="component-action"
    >
        <section>
            <sbx-pop-out
                :content-id="`dropdown-unit-${props.unit.id}-rubric-capabilities`"
                button-text="Manage"
                move-to="#unitplanner-dropdown-container"
                is-component-action
            ></sbx-pop-out>

            <ul :id="`dropdown-unit-${props.unit.id}-rubric-capabilities`" class="f-dropdown" data-dropdown-content>
                <li>
                    <a
                        v-if="props.unit.rubricCapabilities._routes.manageCourse"
                        class="icon-criteria"
                        :href="getReturnUrl(props.unit.rubricCapabilities._routes.manageCourse)"
                    >
                        Manage Course {{ locale.rubricGroup.translate(true, true) }}
                    </a>
                </li>
                <li>
                    <a
                        v-if="props.unit.rubricCapabilities._routes.manageUnit"
                        class="icon-criteria"
                        :href="getReturnUrl(props.unit.rubricCapabilities._routes.manageUnit)"
                    >
                        Manage {{ locale.unit.translate(false, true) }} {{ locale.rubricGroup.translate(true, true) }}
                    </a>
                </li>
            </ul>
        </section>
    </div>
</template>

<style scoped>
.component-action section {
    border-bottom: 0;
    pointer-events: all;
}
</style>
