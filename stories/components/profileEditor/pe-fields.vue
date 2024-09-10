<script setup lang="ts">
/**
 * To customise the fields displayed on a user profile page
 */

interface Props {
    fields: object;
}

const props = defineProps<Props>();
</script>

<template>
    <div>
        <p>{{ props.fields.available[0].name }}</p>
    </div>
</template>

<style scoped></style>
