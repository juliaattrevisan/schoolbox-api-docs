<script setup lang="ts">
/**
 * To show an image in the table using src
 * If there's a href, wrap the image in a link
 */

interface Props {
    rowData: object; // the row of data
    componentData: object; // the component data
}
const props = defineProps<Props>();
</script>

<template>
    <template v-if="componentData.src && componentData.href">
        <a :href="props.rowData[componentData.href]" :title="componentData.title">
            <img :src="props.rowData[componentData.src]" :alt="componentData.alt" />
        </a>
    </template>
    <template v-else-if="componentData.src">
        <img :src="props.rowData[componentData.src]" :alt="componentData.alt" />
    </template>
</template>
