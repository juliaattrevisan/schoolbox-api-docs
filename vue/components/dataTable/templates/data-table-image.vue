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
    <template v-if="props.componentData.src && props.componentData.href && props.rowData[props.componentData.href]">
        <a :href="props.rowData[props.componentData.href] as string" :title="props.componentData.title">
            <div :class="`c-img ${props.componentData.imgClass as string}`">
                <div class="c-img__wrapper">
                    <img :src="props.rowData[props.componentData.src] as string" :alt="props.componentData.alt" />
                </div>
            </div>
        </a>
    </template>
    <template v-else-if="props.componentData.src">
        <div :class="`c-img ${props.componentData.imgClass as string}`">
            <div class="c-img__wrapper">
                <img :src="props.rowData[props.componentData.src] as string" :alt="props.componentData.alt" />
            </div>
        </div>
    </template>
</template>
