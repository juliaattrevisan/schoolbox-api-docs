<script setup lang="ts">
/**
 * The empty state for a rubric capabilities cell on the unit planner when no capabilities are present
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
    <i class="icon-criteria"></i>
    <p>
        No rubric {{ locale.rubricGroup.plural() }} have been added to this unit yet,
        <a :href="getReturnUrl(`/rubric/manager/${props.unit.folderId}`)">
            start by adding rubric {{ locale.rubricGroup.plural() }}
        </a>
    </p>
</template>

<style scoped></style>
