<script setup>

/**
 * 
 */

import { ref  } from 'vue';
import RevealModal from '@schoolboxVue/components/reveal-modal';

const updateSettingsModal = ref(null);

/**
 * 
 */
function onUpdateSettingsSelected () {
    updateSettingsModal.value.open();
}

/**
 * 
 */
function onEmailGroupSelected () {
    
}

/**
 * 
 */
function onEmailGroupParentsSelected () {
    
}

/**
 * 
 */
function onDisbandSelected () {
    
}

</script>

<template>
    <div class="row actions-small-1">
        <div class="list-item small-12 columns">
            <h1>Manage Group</h1>
        </div>
        <nav>
            <a href="#" class="icon-more" data-dropdown="tab-control" title="Actions"></a>
        </nav>
        <ul id="tab-control" class="f-dropdown" data-dropdown-content>
            <li>
                <a href="#" class="icon-settings" id="updateGroupSettings" @click.prevent="onUpdateSettingsSelected">Update Group Settings</a>
            </li>
            <li>
                <a href="#" class="icon-email" id="emailAllGroup" @click.prevent="onEmailGroupSelected">Email Group Members</a>
            </li>
            <li>
                <a href="#" class="icon-staff" id="emailAllParents" title="Includes parents of students in the group, as well as parents directly in the group." @click.prevent="onEmailGroupParentsSelected">Email Group Parents</a>
            </li>
            <li>
                <a href="/group/modifyForm.php?disband=1&categoryID={CATEGORY_ID}" class="icon-delete" @click.prevent="onDisbandSelected">Disband Group</a>
            </li>
        </ul>
    </div>
    <reveal-modal ref="updateSettingsModal">
        <h2>Update Group Settings</h2>
        <input type="hidden" name="categoryID" value="" />
        <div class="row">
            <div class="small-12 medium-6 columns no-pad">
                <legend>Access</legend>
                <ul class="option-list">
                    <li>
                        <input id="type-1" name="type" type="radio" value="1" />
                        <label for="type-1" class="radio-label">
                            Restricted
                            <span data-tooltip class="has-tip tip-top hint" title="Membership is only by invitation from a group admin.">?</span>
                        </label>
                    </li>
                    <li>
                        <input id="type-2" name="type" type="radio" value="2" />
                        <label for="type-2" class="radio-label">
                            Moderated
                            <span data-tooltip class="has-tip tip-top hint" title="Users may request membership, but must be approved by a group admin.">?</span>
                        </label>
                    </li>
                    <li>
                        <input id="type-3" name="type" type="radio" value="3" checked />
                        <label for="type-3" class="radio-label">
                            Free
                            <span data-tooltip class="has-tip tip-top hint" title="Users may join and leave at any time, without approval.">?</span>
                        </label>
                    </li>
                </ul>
            </div><!-- small-12 medium-6 columns -->
            <div class="small-12 medium-6 columns no-pad">
                <legend>Notifications</legend>  
                <input type="hidden" name="msg" value="0" />
                <input id="msg-enabled" type="checkbox" name="msg" value="1" checked />
                <label for="msg-enabled" class="checkbox-label">Notifications enabled</label>
                <!-- Hide this if messaging isn't enabled -->
                <div data-hide-watch="#msg-enabled">
                    <input name="msg" type="hidden" value="0" />
                    <input id="msg-force-follow" name="msg" type="checkbox" value="1" />
                    <label for="msg-force-follow" class="checkbox-label">
                        Force Follow
                        <span data-tooltip class="has-tip tip-top hint" title="Group members may not opt out of receiving messages if Force Follow is enabled.">?</span>
                    </label>
                </div>
            </div>
        </div><!-- small-12 medium-6 columns -->
        <!-- END msg_enabled -->
        <input type="submit" name="Submit" value="Submit" />
    </reveal-modal>
</template>

<style scoped>

</style>