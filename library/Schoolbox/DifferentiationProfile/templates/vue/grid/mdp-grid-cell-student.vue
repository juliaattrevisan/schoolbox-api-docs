<script setup lang="ts">
/**
 * Display a student and access to select linked records and generate with AI
 */

import type { User } from "@schoolboxLibrary/types/User";

const props = defineProps<{
    user: User;
}>();
</script>

<template>
    <p>{{ props.user.fullName }}</p>
</template>

<style scoped></style>
