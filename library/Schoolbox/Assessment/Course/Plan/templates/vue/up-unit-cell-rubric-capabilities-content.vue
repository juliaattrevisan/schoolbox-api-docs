<script setup lang="ts">
/**
 * A component that displays a list of rubric capabilities and their indicators
 */
import { RubricCapability } from "./unitPlannerTypes";

interface Props {
    rubricCapabilities: RubricCapability[];
}

const props = defineProps<Props>();

/**
 * Is this cell empty?
 * Used by the parent component to determine whether to show the empty state
 */
const isEmpty = window.Vue.computed(() => {
    return props.rubricCapabilities.length === 0;
});

// Allow the parent component to identify if the cell is empty
defineExpose({ isEmpty });
</script>

<template>
    <table ref="rubricCapabilitiesEl" class="rb rb-capabilities-indicators">
        <tbody>
            <template v-for="capability in props.rubricCapabilities" :key="capability.id">
                <tr>
                    <!-- eslint-disable-next-line vue/no-v-html | capability.name is scrubbed on input, and is sent here via Scrub::markdown() (which can add <p></p> tags for example) -->
                    <th class="markdown border-none" v-html="capability.name"></th>
                </tr>
                <tr v-for="indicator in capability.indicators" :key="indicator.id">
                    <!-- eslint-disable-next-line vue/no-v-html | indicator.name is scrubbed on input, and is sent here via Scrub::markdown() (which can add <p></p> tags for example) -->
                    <td class="markdown border-none" v-html="indicator.name"></td>
                </tr>
            </template>
        </tbody>
    </table>
</template>

<style scoped>
table {
    margin-bottom: 5px;
}
/* Styles for printing */
@media print {
    table td,
    th {
        border: none !important;
    }
}
</style>
