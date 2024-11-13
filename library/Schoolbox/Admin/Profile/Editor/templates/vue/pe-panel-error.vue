<script setup lang="ts">
/**
 * The error panel for an active field or tab that has an error
 */

interface Props {
    message: string;
    type: "field" | "tab";
}

const props = defineProps<Props>();
</script>

<template>
    <div class="alert alert-box" style="opacity: 1">
        <b>Warning:</b> There was a problem saving changes to this {{ props.type }}. Please
        <a href="">refresh your page</a> to ensure you're up to date.
        <br />
        {{ props.message }}
    </div>
</template>

<style scoped>
.alert-box {
    margin-bottom: 0; /* Remove bottom margin from the alert box's default styling, the component does it anyway */
}
</style>
