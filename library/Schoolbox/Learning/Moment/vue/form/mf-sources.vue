<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { useMomentFormStore } from "./store/momentFormStore";

const mfStore = useMomentFormStore();
</script>

<template>
    <template v-if="mfStore.folders.length > 0">
        <legend>{{ locale.class.properPlural() }} and Groups</legend>

        <div class="row">
            <template v-for="folder in mfStore.folders" :key="folder.id">
                <div class="small-12 medium-6 large-4 xlarge-3 island">
                    <section class="checklist">
                        <div class="v-card small-12">
                            <div class="card-content classes actions-small-1">
                                <div class="list-item">
                                    <h3><a title="{{ folder.title }}">{{ folder.name }}</a></h3>
                                    <p class="meta">{{ folder.codeString }}<br>{{ classType ?? null }}</p>

                                    <input
                                        :id="`mf-checkbox-${folder.id}`"
                                        type="checkbox"
                                        :checked="mfStore.selectedFolders.includes(folder.id)"
                                        @change="mfStore.toggleFolderSelection(folder.id)"
                                    />
                                    <label :for="`mf-checkbox-${folder.id}`"></label>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </template>
        </div>
    </template>
</template>

<style scoped></style>
