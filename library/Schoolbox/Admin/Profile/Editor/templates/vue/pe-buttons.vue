<script setup lang="ts">
/**
 * The buttons available on the profile page for this role type,
 * each with a link to the relevant modify page
 */

import { getReturnUrl } from "@js/util/sbx/urlUtils";
import { useProfileEditorStore } from "./store/profileEditorStore";

const peStore = useProfileEditorStore();
</script>

<template>
    <div v-if="peStore.selectedRoleType" class="small-12 island">
        <h3 class="subheader">Buttons</h3>
        <section class="content">
            <p class="meta">
                This is a list of the current buttons configured for this role type. You can manage the profile buttons
                <a :href="peStore.selectedRoleType._links.manageButtons">here.</a>
            </p>
            <ul class="flex-list flex-wrap buttons">
                <li v-for="button in peStore.selectedRoleType.buttons" :key="button.id">
                    <a :class="`icon-${button.icon}`" :title="button.title" :href="getReturnUrl(button._links.modify)">
                        {{ button.text }}
                    </a>
                </li>
            </ul>
        </section>
    </div>
</template>

<style scoped></style>
