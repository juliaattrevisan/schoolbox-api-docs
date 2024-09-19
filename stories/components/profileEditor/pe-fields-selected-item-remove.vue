<script setup lang="ts">
/**
 * The remove panel for a selected field
 */

import { useProfileEditorStore } from "./store/profileEditorStore";
import type { ProfileSelectedField } from "./types/profileTypes";

interface Props {
    field: ProfileSelectedField;
}

const props = defineProps<Props>();
const peStore = useProfileEditorStore();

/**
 * When the user clicks the remove button
 */
function onRemoveClicked(): void {
    if (peStore.selectedRoleType === null) return;

    peStore.selectedRoleType.fields.selected = peStore.selectedRoleType.fields.selected.filter(
        (field) => field.field !== props.field.field,
    );
}
</script>

<template>
    <div class="editPanel small-12" style="display: block">
        <section>
            <div>
                <p>Are you sure you want to remove this field?</p>
                <ul class="flex-list buttons">
                    <li><button class="button submit" @click="onRemoveClicked">Remove</button></li>
                    <li><button class="button">Cancel</button></li>
                </ul>
            </div>
        </section>
    </div>
</template>

<style scoped></style>
