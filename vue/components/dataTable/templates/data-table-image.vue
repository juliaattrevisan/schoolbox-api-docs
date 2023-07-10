<script setup lang="ts">
/**
 * To show an image in the table using src
 * If there's a href, wrap the image in a link
 */

interface ComponentData {
    src: string; // the src of the image, typically a data key
    alt: string; // alternative text for the image
    href?: string; // to link the image, typically a data key
    title?: string; // the title text for the link
}

interface Props {
    rowData: object; // the row of data
    componentData: ComponentData; // the component data
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
