<script setup lang="ts">
/**
 * To show an image in the table using src
 * If there's a href, wrap the image in a link
 */

import type { Data, ImageComponentData } from "../types/dataTableTypes";

interface Props {
    rowData: Data; // the row of data
    componentData: ImageComponentData; // the component data
}
const props = defineProps<Props>();
</script>

<template>
    <template v-if="componentData.src && componentData.href">
        <a :href="(props.rowData[componentData.href] as string)" :title="componentData.title">
            <img :src="(props.rowData[componentData.src] as string)" :alt="componentData.alt" />
        </a>
    </template>
    <template v-else-if="componentData.src">
        <img :src="(props.rowData[componentData.src] as string)" :alt="componentData.alt" />
    </template>
</template>
