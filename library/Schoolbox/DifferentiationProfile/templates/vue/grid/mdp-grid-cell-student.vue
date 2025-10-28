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
    <div class="l-pad-s">
        <p>{{ props.user.fullName }}</p>
    </div>
</template>

<style scoped></style>
