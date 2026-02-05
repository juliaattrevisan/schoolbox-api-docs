<script setup lang="ts">
/**
 * Display date
 */

const props = defineProps<{
    value: string | null;
}>();

/**
 * Formats a date string into a short date format.
 * @param dateString The date string to format.
 * @returns The formatted date string or null if input is invalid.
 */
function formatDateShort(dateString: string | null): string | null {
    if (!dateString) {
        return null;
    }

    return `${window.dayjs(dateString).format(window.dateDisplayFormats.dayjsDateShort)} ${window
        .dayjs(dateString)
        .format(window.dateDisplayFormats.dayjsTime)}`;
}
</script>

<template>
    <div class="l-pad-s l-gap-m">
        <span>
            {{ formatDateShort(props.value) ?? "—" }}
        </span>
    </div>
</template>

<style scoped></style>
