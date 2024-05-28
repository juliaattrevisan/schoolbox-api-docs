<script setup lang="ts">
/**
 * The empty state for a rubric capabilities cell on the unit planner when no capabilities are present
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
    <i class="icon-criteria"></i>
    <p>
        No rubric {{ rubricGroupLocale.plural() }} have been added to this unit yet,
        <a :href="getReturnUrl(`/rubric/manager/${props.unit.folderId}`)">
            start by adding rubric {{ rubricGroupLocale.plural() }}</a
        >
    </p>
</template>

<style scoped></style>
