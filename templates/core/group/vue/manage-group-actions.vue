<script setup>

/**
 * 
 */


</script>

<template>
   <nav>
        <a href="#" class="icon-more" data-dropdown="tab-control" title="Actions"></a>
    </nav>
    <ul id="tab-control" class="f-dropdown" data-dropdown-content>
        <li>
            <a href="#" class="icon-settings" id="updateGroupSettings">Update Group Settings</a>
        </li>
        <li>
            <a href="#" class="icon-email" id="emailAllGroup">Email Group Members</a>
        </li>
        <li>
            <a href="#" class="icon-staff" id="emailAllParents" title="Includes parents of students in the group, as well as parents directly in the group.">Email Group Parents</a>
        </li>
        <li>
            <a href="/group/modifyForm.php?disband=1&categoryID={CATEGORY_ID}" class="icon-delete" data-action="disband">Disband Group</a>
        </li>
    </ul>
</template>

<style scoped>

</style>