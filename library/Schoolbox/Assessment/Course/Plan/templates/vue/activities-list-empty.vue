<script setup lang="ts">
const activityLocale = window.sbProps.l10n.get("activity");
</script>

<template>
    <i class="icon-criteria"></i>
    <p>No {{ activityLocale.singular() }} have been added to this unit yet</p>
</template>

<style scoped></style>
