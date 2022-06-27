<script setup>

/**
 * All the functionality to be able to manage a group
 */

import { ref, computed, onMounted  } from 'vue'

const props = defineProps({
    /**
     * the endpoint to retrieve all the group data from
     */
    endpoint: {
        type: String,
        required: true,
    },
});

onMounted(() => {
    $(document).foundation(); //to enable the Foundation drop down, the [data-dropdown] element
    $('[data-collapser]').collapsable(); //to open clsoe the Update settings
});

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
            <li><a href="#" class="icon-email" id="emailAllGroup">Email Group Members</a></li>
            <li><a href="#" class="icon-staff" id="emailAllParents" title="Includes parents of students in the group, as well as parents directly in the group.">Email Group Parents</a></li>
            <li><a href="/group/modifyForm.php?disband=1&categoryID={CATEGORY_ID}" class="icon-delete" data-action="disband">Disband Group</a></li>
        </ul>
    </div><!-- row actions-small-1 -->
    <div class="row">
        <div class="small-12 island">
            <section>
                <fieldset class="content collapsible">
                    <legend data-collapser="optional-section" class="icon-down-arrow">Update Group Settings</legend>
                    <div class="small-12 columns" data-collapsable="optional-section" data-collapse-state="collapsed" style="display: none;">
                        <fieldset class="content">
                            <input type="hidden" name="categoryID" value="{CATEGORY_ID}" />
                            <div class="row">
                                <div class="small-12 medium-6 columns">
                                    <fieldset>
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
                                                <input id="type-3" name="type" type="radio" value="3" />
                                                <label for="type-3" class="radio-label">
                                                    Free
                                                    <span data-tooltip class="has-tip tip-top hint" title="Users may join and leave at any time, without approval.">?</span>
                                                </label>
                                            </li>
                                        </ul>
                                    </fieldset>
                                </div>
                                <!-- BEGIN msg_enabled -->
                                <div class="small-12 medium-6 columns">
                                    <fieldset>
                                        <legend>Notifications</legend>
                                        <input type="hidden" name="msg[enabled]" value="0" />
                                        <input id="msg-enabled" type="checkbox" name="msg[enabled]" value="1" />
                                        <label for="msg-enabled" class="checkbox-label">Notifications enabled</label>
                                        <!-- Hide this if messaging isn't enabled -->
                                        <div data-hide-watch="#msg-enabled">
                                            <input name="msg[force_follow]" type="hidden" value="0" />
                                            <input id="msg-force-follow" name="msg[force_follow]" type="checkbox" value="1" />
                                            <label for="msg-force-follow" class="checkbox-label">
                                                Force Follow
                                                <span data-tooltip class="has-tip tip-top hint" title="Group members may not opt out of receiving messages if Force Follow is enabled.">?</span>
                                            </label>
                                        </div>
                                    </fieldset>
                                </div>
                            </div>
                            <!-- END msg_enabled -->
                            <input type="submit" value="Update" />
                        </fieldset>
                    </div>
                </fieldset>
            </section>
        </div><!-- row -->
    </div><!-- small-12 island -->
</template>

<style scoped>
    
</style>