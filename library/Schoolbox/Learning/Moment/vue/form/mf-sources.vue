<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { useMomentFormStore } from "./store/momentFormStore";

const mfStore = useMomentFormStore();
</script>

<template>
    <template v-if="mfStore.folders.length > 0">
        <legend>{{ locale.class.properPlural() }} and Groups</legend>

        <div class="row">
            <div class="small-12 columns">
            <template v-for="folder in mfStore.folders" :key="folder.id">
                <div class="small-12 medium-6 large-4 xlarge-3 island">
                    <section class="checklist">
                        <div class="v-card small-12">
                            <div class="card-class-image"
                               :style="
                                   {
                                       backgroundColor: folder.colour,
                                       borderBottom: `10px solid ${folder.colour}`,
                                       /* Cover image is optional */
                                       backgroundImage: folder.coverImage ? `url('/storage/image.php?hash=${folder.coverImage.hash}&size=cardImageLarge')` : undefined,
                                       backgroundSize: folder.coverImage ? 'cover' : undefined,
                                       backgroundRepeat: folder.coverImage ? 'no-repeat' : undefined,
                                       backgroundPosition: folder.coverImage ? 'center' : undefined
                                   }
                            ">
                                <input
                                    :id="`mf-checkbox-${folder.id}`"
                                    type="checkbox"
                                    :checked="mfStore.selectedFolders.includes(folder.id)"
                                    @change="mfStore.toggleFolderSelection(folder.id)"
                                />
                                <label :for="`mf-checkbox-${folder.id}`" class="mf-source-checkbox"></label>
                            </div>
                            <div class="card-content classes actions-small-1">
                                <div class="list-item">
                                    <h3><a title="{{ folder.title }}">{{ folder.name }}</a></h3>
                                    <p class="meta">{{ folder.codeString }}<br>{{ classType ?? null }}</p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </template>
            </div>
        </div>
    </template>
</template>

<style scoped>
.mf-source-checkbox {
  position: absolute;
  top: 1rem;
  left: 1rem;
}
</style>
