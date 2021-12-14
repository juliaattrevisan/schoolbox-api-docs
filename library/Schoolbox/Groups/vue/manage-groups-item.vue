
<script>

/**
 * A single group item, can join/leave, add toggle favourites/notifications
 */

import enrolmentTypes from '../js/manageGroupsEnrolmentTypes';
import groupTypes from '../js/manageGroupsGroupTypes';
import { addGroupToSideMenu, removeGroupFromSideMenu } from '../js/modifyGroupSideMenu';


export default {

    props: {
        endpoint: { //to join/leave/favourite etc a group
            type: String,
            required: true,
        },
        group: { //all the group data
            type: Object,
            required: true,
        }
    },

    data () {
        return {
            isProcessing: false, //to disable other actions while one is already taking place
            enrolmentStatus: null, //to respond to enrolment changes, joining and leaving group
            isFavourite: null, //to show whether the group is a favourite or not
            isNotificationsOn: null, //to show whether notifications is turned or not
        }
    },

    created () {
        this.initGroupProps();    
    },

    computed: {

        /**
         * Cant leave private groups, shown so can access other features
         * 
         * @returns { Boolean }
         */
        isPrivate () {
            return this.group.type === groupTypes.PRIVATE && this.enrolmentStatus === enrolmentTypes.ENROLLED;
        },

        /**
         * Check if is already a member of a non private group, so can leave if requested
         * 
         * @returns { Boolean }
         */
        isEnrolled () {
            return this.group.type === groupTypes.FREE && this.enrolmentStatus === enrolmentTypes.ENROLLED;
        },

        /**
         * Check if membership is already pending
         * 
         * @returns { Boolean }
         */
        isEnrolmentPending () {
            return this.enrolmentStatus === enrolmentTypes.PENDING;
        },

        /**
         * Check if need to request to join
         * 
         * @returns { Boolean }
         */
        isRequestToJoin () {
            return this.group.type === groupTypes.MODERATED && this.enrolmentStatus === enrolmentTypes.NOT_ENROLLED;
        },

    },

    methods: {

        /**
         * Set up the properties on the group object that need to be reactive
         */
        initGroupProps () {
            this.enrolmentStatus = this.group.enrolmentStatus;
            this.isFavourite = this.group.isFavourite;
            this.isNotificationsOn = this.group.isNotificationsOn;
        },

        /**
         * Get the image style, adding in the cover image only if available
         * 
         * @returns { Object }
         */
        getImageStyles () {
            let imageStyles = {
                backgroundColor: `#${this.group.colour}`,
            }
            if (this.group.coverImageHash) {
                imageStyles = { 
                    ...imageStyles,
                    backgroundImage: `url('/storage/image.php?hash=${this.group.coverImageHash}&size=cardImageLarge')`,
                    backgroundSize: 'cover',
                    backgroundRepeat: 'no-repeat',
                    backgroundPosition: 'center',
                }
            }
            return imageStyles;
        },

        /**
         * A simple wrapper to handle the shared post request behaviour
         * 
         * @param { Object }
         * 
         * @returns { Object }
         */
        async getResponseData (appendList) {
            const formData = new FormData();
            formData.append('group', this.group.id);
            for (const [key, value] of Object.entries(appendList)) {
                formData.append(`${key}`, `${value}`);
            }
            const response = await fetch(this.endpoint, {
                method: 'POST',
                body: formData,
            })
            return await response.json();
        },

        /**
         * Join the group
         */
        async join () {
            this.isProcessing = true;
            this.$emit('on-edit-start', 'Joining group...');
            try {
                const responseData = await this.getResponseData({
                    action: 'add'
                });
                if (responseData.error) {
                    this.$emit('on-edit-error', responseData.error);
                } else {
                    this.enrolmentStatus = responseData.enrolmentStatus; //could be either 'enrolled' or 'pending'
                    this.$emit('on-edit-success', 'Group joined');
                }
            } catch (error) {
                console.warn(error);
                this.$emit('on-edit-error', 'Unable to join group');
            } finally {
                this.isProcessing = false;
            }
        },

        /**
         * Leave the group 
         */
        async leave () {
            this.isProcessing = true;
            this.$emit('on-edit-start', 'Leaving group...');
            try {
                const responseData = await this.getResponseData({
                    action: 'delete'
                });
                if (responseData.error) {
                    this.$emit('on-edit-error', responseData.error);
                } else {
                    this.enrolmentStatus = enrolmentTypes.NOT_ENROLLED;
                    this.$emit('on-edit-success', 'Group left');
                    if (this.isFavourite) {
                        removeGroupFromSideMenu(this.group.id);
                    }
                }
            } catch (error) {
                console.warn(error);
                this.$emit('on-edit-error', 'Unable to leave group');
            } finally {
                this.isProcessing = false;
            }
        },

        /**
         * Add to the users favourite list
         */
        async addToFavourites () {
            this.isProcessing = true;
            this.$emit('on-edit-start', 'Adding to favourites...');
            try {
                const responseData = await this.getResponseData({
                    action: 'edit',
                    isFavourite: '1'
                });
                if (responseData.error) {
                    this.$emit('on-edit-error', responseData.error);
                } else {
                    this.isFavourite = responseData.isFavourite;
                    this.$emit('on-edit-success', 'Added to favourites');
                    addGroupToSideMenu(this.group.name, `/homepage/${this.group.id}/`);
                }
            } catch (error) {
                console.warn(error);
                this.$emit('on-edit-error', 'Unable to add to favourites');
            } finally {
                this.isProcessing = false;
            }
        },

        /**
         * Remove from the users favourite list
         */
        async removeFromFavourites () {
            this.isProcessing = true;
            this.$emit('on-edit-start', 'Removing from favourites...');
            try {
                const responseData = await this.getResponseData({
                    action: 'edit',
                    isFavourite: '0'
                });
                if (responseData.error) {
                    this.$emit('on-edit-error', responseData.error);
                } else {
                    this.isFavourite = responseData.isFavourite;
                    this.$emit('on-edit-success', 'Removed from favourites');
                    removeGroupFromSideMenu(this.group.id);
                }
            } catch (error) {
                console.warn(error);
                this.$emit('on-edit-error', 'Unable to remove from favourites');
            } finally {
                this.isProcessing = false;
            }
        },

        /**
         * Add to the users notifications list
         */
        async addToNotifications () {
            this.isProcessing = true;
            this.$emit('on-edit-start', 'Adding to notifications...');
            try {
                const responseData = await this.getResponseData({
                    action: 'edit',
                    isNotificationsOn: '1'
                });
                if (responseData.error) {
                    this.$emit('on-edit-error', responseData.error);
                } else {
                    this.isNotificationsOn = responseData.isNotificationsOn;
                    this.$emit('on-edit-success', 'Added to notifications');
                }
            } catch (error) {
                console.warn(error);
                this.$emit('on-edit-error', 'Unable to add to notifications');
            } finally {
                this.isProcessing = false;
            }
        },

        /**
         * Remove from the users notifications list
         */
        async removeFromNotifications () {
            this.isProcessing = true;
            this.$emit('on-edit-start', 'Remove from notifications...');
            try {
                const responseData = await this.getResponseData({
                    action: 'edit',
                    isNotificationsOn: '0'
                });
                if (responseData.error) {
                    this.$emit('on-edit-error', responseData.error);
                } else {
                    this.isNotificationsOn = responseData.isNotificationsOn;
                    this.$emit('on-edit-success', 'Removed from notifications');
                }
            } catch (error) {
                console.warn(error);
                this.$emit('on-edit-error', 'Unable to remove from notifications');
            } finally {
                this.isProcessing = false;
            }
        },

    }

}

</script>

<template>
    <div class="small-12 medium-6 large-4 xlarge-3 island">
        <section>
            <div class="v-card small-12">
                <div class="group-image-container">
                    <div class="card-class-image" :style="getImageStyles()"></div>
                    <div class="group-actions" v-if="isEnrolled || isPrivate">
                        <button v-if="isFavourite" class="group-action icon-favourite" title="Remove from Favourites" key="remove-favourite" @click="removeFromFavourites">Remove from Favourites</button>
                        <button v-else class="group-action icon-favourite-hollow" title="Add to Favourites" key="add-favourite" @click="addToFavourites">Add to Favourites</button>
                        <template v-if="!group.isForceFollow">
                            <button v-if="isNotificationsOn" class="group-action icon-notifications" title="Remove from Notifications" key="remove-notification" @click="removeFromNotifications">Remove from Notifications</button>
                            <button v-else class="group-action icon-notifications-hollow" title="Add to Notifications" key="add-notification" @click="addToNotifications">Add to Notifications</button>
                        </template>
                    </div>
                </div>
                <div class="card-content classes groups actions-small-1">
                    <div class="list-item">
                        <h3><a :href="`/homepage/${group.id}`" :title="`Visit ${group.name}`">{{ group.name }}</a></h3>
                    </div>
                    <template v-if="group.isAccessWrite || group.isAccessAdmin">
                        <nav>
                            <a href="#view-actions" class="icon-more" :data-dropdown="`class-options${ group.id }`" aria-expanded="false" title="View actions"></a>
                        </nav>
                        <ul data-dropdown-content class="f-dropdown" :id="`class-options${ group.id }`">
                            <li v-if="group.isAccessWrite || group.isAccessAdmin">
                                <a :href="group.modifyLink" class="icon-modify" title="Modify Folder">Modify Folder</a>
                            </li>
                            <li v-if="group.isAccessWrite || group.isAccessAdmin">
                                <a :href="`/statistics/homepage/${group.id}`" class="icon-report" title="View Statistics">View Statistics</a>
                            </li>
                            <li v-if="group.isAccessAdmin">
                                <a :href="`/group/modify/${group.id}`" class="icon-group" title="Modify Group">Modify Group</a>
                            </li>
                        </ul>
                    </template>
                </div>
                <transition name="fade" mode="out-in">
                    <button v-if="isPrivate" class="button expand private disabled" key="private" disabled>Private</button>
                    <button v-else-if="isEnrolled" class="button expand icon-logout alert" :title="`Leave ${group.name}`" key="leave" @click="leave">Leave</button>
                    <button v-else-if="isEnrolmentPending" class="button expand submit disabled" key="membershipPending" disabled>Membership pending</button>
                    <button v-else-if="isRequestToJoin" class="button expand icon-add submit" :title="`Request to join ${group.name}`" key="requestToJoin" @click="join">Request to join</button>
                    <button v-else class="button expand icon-add submit" :title="`Join ${group.name}`" key="join" @click="join">Join</button>
                </transition>
            </div>
        </section>
    </div>
</template>

<style lang="scss" scoped>

.group-image-container {
    position: relative;
}

.group-actions { //to put them in the top right corner
    background: rgba(0, 102, 176, 0.85);
    display: inline-block;
    position: absolute;
    right: 10px;
    top: 10px;
}

.group-action {
    background: none;
    font-size: 0;
    &:before {
        color: white;
        font-size: 1.5rem;
        margin: 0;
        padding: 0;
    }
}

.button {
    display: inline-block;
    margin: 0;
    padding: 1rem; //add extra padding
}

.private {
    background: #DDDDDD;
    color: #5e5e5e;
}

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s;
}
.fade-enter, .fade-leave-to {
    opacity: 0;
}

</style>