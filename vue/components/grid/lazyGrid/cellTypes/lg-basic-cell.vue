<script setup lang="ts">
const props = defineProps<{
    value: unknown;
}>();

type LinkObject = {
    title?: string;
    _links?: {
        view?: string;
    };
};

/**
 * Type guard to check if a value is a LinkObject.
 * @param value - The value to check
 * @returns True if value is a LinkObject, false otherwise
 */
function isLinkObject(value: unknown): value is LinkObject {
    return typeof value === "object" && value !== null && "title" in value;
}
</script>

<template>
    <div class="l-flex-col l-pad-s l-gap-m">
        <a v-if="isLinkObject(props.value) && props.value._links?.view" :href="props.value._links.view">
            {{ props.value.title }}
        </a>

        <span v-else-if="isLinkObject(props.value)">
            {{ props.value.title ?? "—" }}
        </span>

        <span v-else>
            {{ props.value ?? "—" }}
        </span>
    </div>
</template>

<style scoped></style>
