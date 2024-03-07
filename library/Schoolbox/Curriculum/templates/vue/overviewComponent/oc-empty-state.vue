<script setup lang="ts">
/**
 * Displayed when there's currently no nodes linked to the Unit
 */

interface Props {
    isSuperUser: boolean; // Whether the user is a super user
    hasTagging: boolean; // Whether curriculum tagging is enabled
    localeUnit: string; // the localised term for a Unit
}
const props = defineProps<Props>();
</script>

<template>
    <div class="empty-state">
        <i class="icon-curriculum"></i>
        <template v-if="props.hasTagging">
            <p v-if="props.isSuperUser">
                <a href="/adminv2/setting/LMS+Policy">Turn on the Enable Curriculum Tagging toggle</a> in the
                Administration system to use this feature.
            </p>
            <p v-else>Ask your System Administrator to enable curriculum mapping to use this feature.</p>
        </template>
        <p v-else>
            <a href="#">Map a curriculum code</a> to your course or {{ props.localeUnit }} page to display a list of all
            of the curriculum codes and matching skill descriptors covered in this area of the curriculum.
        </p>
    </div>
</template>

<style scoped></style>
