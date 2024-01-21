<script setup lang="ts">
/**
 * To display an icon
 */

interface Props {
    /**
     * The size of the icon
     */
    size: string;
}
const props = withDefaults(defineProps<Props>(), {
    size: "m",
});
</script>

<template>
    <p>Icon</p>
</template>

<style lang="scss" scoped></style>
