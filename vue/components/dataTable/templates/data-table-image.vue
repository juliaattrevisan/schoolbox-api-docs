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
            <div :class="`img ${props.componentData.imgClass as string}`">
                <div class="img__wrapper">
                    <img :src="(props.rowData[componentData.src] as string)" :alt="componentData.alt" />
                </div>
            </div>
        </a>
    </template>
    <template v-else-if="componentData.src">
        <div :class="`img ${props.componentData.imgClass as string}`">
            <div class="img__wrapper">
                <img :src="(props.rowData[componentData.src] as string)" :alt="componentData.alt" />
            </div>
        </div>
    </template>
</template>
