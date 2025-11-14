<script setup lang="ts">
import { fromEntriesOf } from "@js/util/arrayUtils";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { DialogAgentResponseStatus } from "@schoolboxAssessment/Types/dialogAgent";
import type { DialogAgentParticipant, DialogAgentScenarioCriteria } from "./dialogAgentStatusTypes";
import DasFilters from "./das-filters.vue";
import DasGrid from "./das-grid.vue";

interface Props {
    criteria: DialogAgentScenarioCriteria[];
    aImpls: {
        id: number;
        folder: {
            id: number;
            title: string;
        };
        participants: DialogAgentParticipant[];
    }[];
    isSpecification?: boolean;
}

const props = defineProps<Props>();

const collapsedImpls = window.Vue.ref<number[]>([]);

/**
 * Toggle the collapsed state of an assessment implementation
 * @param aImplId The assessment implementation ID to toggle
 */
function toggleCollapse(aImplId: number): void {
    const index = collapsedImpls.value.indexOf(aImplId);
    if (index === -1) {
        collapsedImpls.value.push(aImplId);
    } else {
        collapsedImpls.value.splice(index, 1);
    }
}
</script>

<template>
    <div class="row">
        <!-- Metadata about the activity -->
        <div class="small-12 large-4 columns">
            <slot name="meta"></slot>
        </div>

        <!-- Status filters -->
        <div class="small-12 large-8 columns text-right">
            <das-filters />
        </div>
    </div>

    <div id="dialog-agent-status-dropdown-container"></div>
    <div v-for="aImpl in props.aImpls" :key="aImpl.id" class="component-container">
        <div v-if="props.isSpecification" class="row">
            <div class="small-12 columns">
                <div class="component-titlebar">
                    <div class="actions-small-1">
                        <div class="list-item">
                            <h2>
                                <a :href="`/homepage/${aImpl.folder.id}`">{{ aImpl.folder.title }}</a>
                            </h2>
                        </div>
                        <nav>
                            <a
                                href="#"
                                class="collapse"
                                :class="collapsedImpls.includes(aImpl.id) ? 'icon-down-arrow' : 'icon-up-arrow'"
                                title="Expand/collapse content"
                                @click="toggleCollapse(aImpl.id)"
                            ></a>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        <Transition @enter="slideDownOnEnter" @leave="slideUpOnLeave">
            <div v-show="!collapsedImpls.includes(aImpl.id)" class="row">
                <div class="small-12 island">
                    <section>
                        <das-grid :criteria="props.criteria" :participants="aImpl.participants" />
                    </section>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped lang="scss"></style>
