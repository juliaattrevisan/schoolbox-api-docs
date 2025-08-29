<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { useMomentFormStore } from "./store/momentFormStore";

const mfStore = useMomentFormStore();
</script>

<template>
    <template v-if="mfStore.folders.length > 0">
        <legend>{{ locale.class.properPlural() }} and Groups</legend>
        <div class="small-12 columns checklist">
            <ul class="option-list">
                <li v-for="folder in mfStore.folders" :key="folder.id">
                    <input
                        :id="`mf-checkbox-${folder.id}`"
                        type="checkbox"
                        :checked="mfStore.selectedFolders.includes(folder.id)"
                        @change="mfStore.toggleFolderSelection(folder.id)"
                        todo add more stuff here
                    />
                    <label :for="`mf-checkbox-${folder.id}`">{{ folder.title }}</label>
                </li>
            </ul>
        </div>
    </template>
</template>

<style scoped></style>
