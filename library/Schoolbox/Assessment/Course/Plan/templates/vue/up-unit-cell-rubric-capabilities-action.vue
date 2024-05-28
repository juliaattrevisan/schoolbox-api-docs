<script setup lang="ts">
/**
 * The component-action for a rubric capabilities cell on the unit planner
 * Currently just links to the manage rubric page
 */
import { getReturnUrl } from "@js/util/sbx/urlUtils";
import type { Unit } from "./unitPlannerTypes";

interface Props {
    unit: Unit;
}

const props = defineProps<Props>();

const rubricGroupLocale = window.sbProps.l10n.get("rubricGroup");
</script>

<template>
    <div class="component-action">
        <section>
            <a class="button" :href="getReturnUrl(`/rubric/manager/${props.unit.folderId}`)">
                Manage rubric {{ rubricGroupLocale.plural() }}
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
