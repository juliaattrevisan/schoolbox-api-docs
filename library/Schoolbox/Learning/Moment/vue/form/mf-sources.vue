<script setup lang="ts">
import locale from "@js/terminology/rosettaStone";
import { useMomentFormStore } from "./store/momentFormStore";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";

const mfStore = useMomentFormStore();
const isExpanded = window.Vue.ref(false);
</script>

<template>
    <fieldset v-if="mfStore.folders.length > 0" class="content collapsible">
        <legend :class="isExpanded ? 'icon-up-arrow' : 'icon-down-arrow'" @click="isExpanded = !isExpanded">
            {{ locale.class.properPlural() }} and Groups
        </legend>

        <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
            <div v-show="isExpanded">
                <div class="l-grid l-grid--4-col l-gap-m l-pad-m">
                    <div v-for="folder in mfStore.folders" :key="folder.id">
                        <section class="checklist">
                            <div class="v-card small-12">
                                <div class="card-class-image"
                                   :style="
                                       {
                                           backgroundColor: `#${folder.colour}`,
                                           borderBottom: `10px solid #${folder.colour}`,
                                           /* Cover image is optional */
                                           backgroundImage: folder.coverImage ? `url('/storage/image.php?hash=${folder.coverImage.hash}&size=cardImageLarge')` : undefined,
                                           backgroundSize: folder.coverImage ? 'cover' : undefined,
                                           backgroundRepeat: folder.coverImage ? 'no-repeat' : undefined,
                                           backgroundPosition: folder.coverImage ? 'center' : undefined
                                       }
                                   "
                                   @click="mfStore.toggleFolderSelection(folder.id)"
                                >
                                    <div class="mf-source-checkbox">
                                        <input
                                            :id="`mf-checkbox-${folder.id}`"
                                            type="checkbox"
                                            :checked="mfStore.selectedFolders.includes(folder.id)"
                                            @change="mfStore.toggleFolderSelection(folder.id)"
                                        />
                                        <label :for="`mf-checkbox-${folder.id}`"></label>
                                    </div>
                                </div>
                                <div class="card-content classes actions-small-1">
                                    <div class="list-item">
                                        <h3><a :href="`/homepage/${folder.id}`" title="{{ folder.title }}">{{ folder.name }}</a></h3>
                                        <p class="meta">{{ folder.codeString }}</p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </Transition>
    </fieldset>
</template>

<style scoped>
.mf-source-checkbox {
  position: absolute;
  top: 1rem;
  left: 1rem;
}

.card-class-image {
  cursor: pointer;
}
</style>
