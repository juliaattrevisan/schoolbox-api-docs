<script setup lang="ts">
/**
 * The component-action for a rubric capabilities cell on the unit planner
 * Currently just links to the manage rubric page
 */
import locale from "@js/terminology/rosettaStone";
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import type { Unit } from "./unitPlannerTypes";

interface Props {
    link: string|null;
    title: string;
}

const props = defineProps<Props>();
</script>

<template>
    <div class="component-action" v-if="link">
        <section>
          <button disabled>
            {{ title }}
          </button>
          <a class="button" :href="getReturnUrl(`${link}`)">
              Manage rubric {{ locale.rubricGroup.plural() }}
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
