<script setup lang="ts">
/**
 * To provide access to further actions on a result
 */

import { Result } from "../../js/types/Result";

interface Props {
    result: Result; // the result to display
}
const props = defineProps<Props>();
</script>

<template>
    <wc-actions-dropdown :content-id="`dropdown-${props.result.id}`"></wc-actions-dropdown>
    <ul :id="`dropdown-${props.result.id}`" class="f-dropdown" data-dropdown-content>
        <li>
            <a id="updateGroupSettings" href="#update-group-settings" class="icon-settings">Update Group Settings</a>
        </li>
    </ul>
</template>

<style scoped></style>
