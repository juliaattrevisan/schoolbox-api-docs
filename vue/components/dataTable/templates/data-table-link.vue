<script setup lang="ts">
/**
 * To wrap the text within a link
 */

interface ComponentData {
    text: string; // the text to display, typically a data key
    title: string; // the title text for the link
    prependHref?: string; // to prepend the href with something like mailto: or tel:
}

interface Props {
    rowData: object; // the row of data
    componentData: ComponentData; // the component data
}
const props = defineProps<Props>();

/**
 * To return the href for the link
 */
const href = window.Vue.computed(() => {
    if (props.componentData.prependHref) {
        return `${props.componentData.prependHref}${props.rowData[props.componentData.text]}`;
    }
    return props.rowData[props.componentData.text];
});
</script>

<template>
    <a :href="href" :title="componentData.title">
        {{ props.rowData[componentData.text] }}
    </a>
</template>
