<script setup lang="ts">
/**
 * The component-action for a rubric capabilities cell on the unit planner
 * Currently just links to the manage rubric page
 */
import locale from "@js/terminology/rosettaStone";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import type { Unit } from "./unitPlannerTypes";

interface Props {
    routes: {
      manageUnit: string | null;
      manageCourse: string | null;
    };
}

const props = defineProps<Props>();
</script>

<template>
    <div class="component-action" v-if="routes.manageCourse || routes.manageUnit">
        <section>
          <a v-if="routes.manageCourse" class="button" :href="getReturnUrl(`${routes.manageCourse}`)">
            Manage Course {{ locale.rubricGroup.plural() }}
          </a>
          <a v-if="routes.manageUnit" class="button" :href="getReturnUrl(`${routes.manageUnit}`)">
            Manage {{ locale.unit.plural() }} {{ locale.rubricGroup.plural() }}
          </a>
        </section>
    </div>
</template>

<style scoped>
.component-action section {
    border-bottom: 0;
    pointer-events: all;
}
</style>
