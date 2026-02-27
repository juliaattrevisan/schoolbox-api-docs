<script setup lang="ts">
/**
 * Display date
 */

interface Props {
    value: string | null;
}
const props = defineProps<Props>();

/**
 * Formats a date string into a short date format.
 * @returns The formatted date string or null if input is invalid.
 */
function formatDateShort(): string | null {
    if (!props.value) {
        return null;
    }
    return `${window.dayjs(props.value).format(window.dateDisplayFormats.dayjsDateShort)} ${window
        .dayjs(props.value)
        .format(window.dateDisplayFormats.dayjsTime)}`;
}
</script>

<template>
    <div class="l-pad-s">
        <span>
            {{ formatDateShort() }}
        </span>
    </div>
</template>

<style scoped></style>
