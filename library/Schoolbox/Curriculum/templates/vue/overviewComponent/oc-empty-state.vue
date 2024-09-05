<script setup lang="ts">
/**
 * Displayed when there's currently no nodes linked to the Unit
 */

import { useStore } from "@schoolboxCurriculum/vue/folderNodes/store/folderNodesStore";

interface Props {
    isSuperUser: boolean; // Whether the user is a super user
    hasTagging: boolean; // Whether curriculum tagging is enabled
    localeUnit: string; // the localised term for a Unit
    folderId: number; // The folder id to display the nodes for
    componentInstanceId: number; // The component instance id to display the nodes on
}
const props = defineProps<Props>();

const emit = defineEmits<{
    mapSelected: [];
}>();

const store = useStore(props.folderId, props.componentInstanceId);

/**
 * To open the curriculum code mapping modal
 */
function onMapSelected(): void {
    emit("mapSelected");
}

/**
 * To prompt the user to udpate the currently selected Curriculum Tree Filter
 */
function onFilterSelected(): void {
    const component = document.getElementById(`component${props.componentInstanceId}`) as HTMLDivElement;
    const toggleButton = component.querySelector('[data-target="settings"]') as HTMLLinkElement;
    if (toggleButton.getAttribute("data-state") === "opened") {
        return;
    }
    toggleButton.dispatchEvent(
        new MouseEvent("click", {
            view: window,
            bubbles: true,
            cancelable: true,
        }),
    );
}
</script>

<template>
    <div class="empty-state">
        <i class="icon-curriculum"></i>
        <template v-if="!store().hasNodes">
            <p v-if="props.hasTagging">
                <a href="#map-curriculum-codes" @click.prevent="onMapSelected">Map curriculum codes</a> to your course
                or {{ props.localeUnit }} page to display a list of all of the curriculum codes and matching skill
                descriptors covered in this area of the curriculum.
            </p>
            <template v-else>
                <p v-if="props.isSuperUser">
                    <a href="/adminv2/setting/LMS+Policy">Turn on the Enable Curriculum Tagging toggle</a> in the
                    Administration system to use this feature.
                </p>
                <p v-else>Ask your System Administrator to enable curriculum mapping to use this feature.</p>
            </template>
        </template>
        <template v-else-if="store().hasHiddenNodes">
            <p v-if="props.isSuperUser">
                No curriculum codes are children of the currently selected Curriculum Tree Filter. Consider
                <a href="#map-curriculum-codes" @click.prevent="onMapSelected">mapping additional curriculum codes</a>
                or updating the currently selected
                <a href="#update-curriculum-tree-filter" @click.prevent="onFilterSelected">Curriculum Tree Filter</a>.
            </p>
            <p v-else>No curriculum codes are children of the currently selected Curriculum Tree Filter.</p>
        </template>
    </div>
</template>

<style scoped></style>
