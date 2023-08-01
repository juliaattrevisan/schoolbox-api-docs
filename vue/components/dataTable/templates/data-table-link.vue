<script setup lang="ts">
/**
 * To wrap the text within a link
 */

import type { Data, LinkComponentData } from "../types/dataTableTypes";

interface Props {
    rowData: Data; // the row of data
    componentData: LinkComponentData; // the component data
}
const props = defineProps<Props>();

/**
 * To return the href for the link
 */
const href = window.Vue.computed(() => {
    if (props.componentData.prependHref) {
        return `${props.componentData.prependHref}${props.rowData[props.componentData.text] as string}`;
    }
    return props.rowData[props.componentData.href];
});
</script>

<template>
    <a :href="(href as string)" :title="props.componentData.title">
        {{ props.rowData[componentData.text] }}
    </a>
</template>
