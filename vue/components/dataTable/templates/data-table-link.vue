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
        const textValue = props.rowData[props.componentData.text];
        if (typeof textValue === "string") {
            return `${props.componentData.prependHref}${textValue}`;
        }
    }
    const hrefValue = props.rowData[props.componentData.href];
    if (typeof hrefValue === "string") {
        return hrefValue;
    }
    return undefined;
});
/**
 * Check if the link exists
 */
const linkExists = window.Vue.computed(() => {
    return !!href.value;
});
</script>

<template>
    <a v-if="linkExists" :href="href" :title="props.componentData.title" :data-no-wrap="props.componentData.hasNoWrap">
        {{ props.rowData[props.componentData.text] }}
    </a>
    <span v-else :data-no-wrap="props.componentData.hasNoWrap">
        {{ props.rowData[props.componentData.text] }}
    </span>
</template>

<style scoped>
/* prevent links from wrapping */
[data-no-wrap="true"] {
    white-space: nowrap;
}
</style>
