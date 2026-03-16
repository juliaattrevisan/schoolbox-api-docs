<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { useMomentFormStore } from "./store/momentFormStore";

const mfStore = useMomentFormStore();
const isExpanded = window.Vue.ref(false);

/**
 * Toggle folder selection, filtering out label clicks
 * @param folderId - the folder id to toggle
 * @param event - the click event
 */
function onFolderSelected(folderId: number, event?: MouseEvent): void {
    // Ignore label clicks as they naturally trigger the checkbox
    if (event && event.target && (event.target as HTMLElement).nodeName === "LABEL") {
        return;
    }
    mfStore.toggleFolderSelection(folderId);
}
</script>

<template>
    <fieldset v-if="mfStore.folders.length > 0" class="content collapsible">
        <legend :class="isExpanded ? 'icon-up-arrow' : 'icon-down-arrow'" @click="isExpanded = !isExpanded">
            {{ locale.class.properPlural() }} and Groups
        </legend>

        <div
            class="l-grid l-pad-m--left l-pad-m--right mf-sources-grid"
            :style="{ 'grid-template-rows': isExpanded ? '1fr' : '0fr' }"
        >
            <div class="l-grid l-grid--auto-fill l-gap-m mf-sources-grid-row">
                <div v-for="folder in mfStore.folders" :key="folder.id" class="mf-source">
                    <section class="checklist">
                        <div class="v-card small-12">
                            <div
                                class="card-class-image"
                                :style="{
                                    backgroundColor: `#${folder.colour}`,
                                    borderBottom: `10px solid #${folder.colour}`,
                                    /* Cover image is optional */
                                    backgroundImage: folder.coverImage
                                        ? `url('/storage/image.php?hash=${folder.coverImage.hash}&size=cardImageLarge')`
                                        : undefined,
                                    backgroundSize: folder.coverImage ? 'cover' : undefined,
                                    backgroundRepeat: folder.coverImage ? 'no-repeat' : undefined,
                                    backgroundPosition: folder.coverImage ? 'center' : undefined,
                                }"
                                @click="(event) => onFolderSelected(folder.id, event)"
                            >
                                <div class="mf-source-checkbox">
                                    <input
                                        :id="`mf-checkbox-${folder.id}`"
                                        type="checkbox"
                                        :checked="mfStore.selectedFolders.includes(folder.id)"
                                        @click.stop="(event) => onFolderSelected(folder.id, event)"
                                    />
                                    <label :for="`mf-checkbox-${folder.id}`"></label>
                                </div>
                            </div>
                            <div class="card-content classes actions-small-1">
                                <div class="list-item">
                                    <h3>
                                        <a :href="`/homepage/${folder.id}`" :title="`${folder.name}`">{{
                                            folder.name
                                        }}</a>
                                    </h3>
                                    <p class="meta">{{ folder.codeString }}</p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </fieldset>
</template>

<style scoped>
.mf-sources-grid {
    transition: grid-template-rows 300ms;
}

.mf-sources-grid-row {
    overflow: hidden;
}

.mf-source-checkbox {
    position: absolute;
    top: 1rem;
    left: 1rem;
}

.mf-source {
    max-width: 20rem;
}

.card-class-image {
    cursor: pointer;
}
</style>
