
<script>

/**
 * A single group item, can join/leave, add toggle favourites/notifications
 */

import enrolmentTypes from '../js/manageGroupsEnrolmentTypes';
import groupTypes from '../js/manageGroupsGroupTypes';
import { gsap } from 'gsap/all';
import { addGroupToSideMenu, removeGroupFromSideMenu } from '../js/modifyGroupSideMenu';


export default {

    props: {
        endpointAdd: { //to join a group
            type: String,
            required: true,
        },
        endpointDelete: { //to leave a group
            type: String,
            required: true,
        },
        endpointNotify: { //to update a groups notification status
            type: String,
            required: true,
        },
        endpointFavourite: { //to update a groups favourite status
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
        }
    },

    computed: {

        /**
         * To respond to enrolment changes, joining and leaving group
         *
         * @returns { String }
         */
        enrolmentStatus () {
            return this.group.enrolmentStatus;
        },

        /**
         * Shouldn't be able to join/leave a group when an admin
         *
         * @returns { Boolean }
         */
        isAdmin () {
            return this.group.isAccessAdmin;
        },

        /**
         * To show whether the group is a favourite or not
         *
         * @returns { Boolean }
         */
        isFavourite () {
            return this.group.isFavourite;
        },

        /**
         * To show whether notifications is turned or not
         *
         * @returns { Boolean }
         */
        isNotificationsOn () {
            return this.group.isNotificationsOn;
        },

        /**
         * Cant leave private groups, shown so can access other features
         *
         * @returns { Boolean }
         */
        isPrivate () {
            return this.group.type === groupTypes.PRIVATE && this.enrolmentStatus === enrolmentTypes.ENROLED;
        },

        /**
         * Check if is already a member of a non private group, so can leave if requested
         *
         * @returns { Boolean }
         */
        isEnrolled () {
            return (this.group.type === groupTypes.FREE || this.group.type === groupTypes.MODERATED)
                    && this.enrolmentStatus === enrolmentTypes.ENROLED;
        },

        /**
         * Check if membership is already pending
         *
         * @returns { Boolean }
         */
        isMembershipPending () {
            return this.enrolmentStatus === enrolmentTypes.PENDING;
        },

        /**
         * Check if need to request to join
         *
         * @returns { Boolean }
         */
        isRequestToJoin () {
            return this.group.type === groupTypes.MODERATED && this.enrolmentStatus === enrolmentTypes.NOT_ENROLED;
        },

    },

    methods: {

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
         * @param { String }
         * @param { Object }
         *
         * @returns { Object }
         */
        async getResponseData (endpoint, appendList = {}) {
            const formData = new FormData();
            formData.append('group', this.group.id);
            for (const [key, value] of Object.entries(appendList)) {
                formData.append(`${key}`, `${value}`);
            }
            const response = await fetch(endpoint, { method: 'POST', body: formData });
            return await response.json();
        },

        /**
         * Join the group
         */
        async join () {
            this.isProcessing = true;
            this.$emit('on-edit-start', 'Joining group...');
            try {
                const responseData = await this.getResponseData(this.endpointAdd);
                if (responseData.error) {
                    this.$emit('on-edit-error', responseData.error);
                } else if (responseData.status === 400) {
                    this.$emit('on-edit-error', responseData.title);
                } else {
                    this.group.enrolmentStatus = responseData.enrolmentStatus; //could be either 'enroled' or 'pending'
                    if (this.group.enrolmentStatus === 'enroled') {
                        this.group.isFavourite = responseData.isFavourite;
                        this.group.isNotificationsOn = responseData.isNotificationsOn;
                        if (this.group.isFavourite) {
                            await addGroupToSideMenu(this.group.name, `/homepage/${this.group.id}/`);
                        }
                        this.$emit('on-edit-success', 'Group joined');
                    } else {
                        this.$emit('on-edit-success', 'Requested to join group');
                    }
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
                const responseData = await this.getResponseData(this.endpointDelete);
                if (responseData.error) {
                    this.$emit('on-edit-error', responseData.error);
                } else if (responseData.status === 400) {
                    this.$emit('on-edit-error', responseData.title);
                } else {
                    this.group.enrolmentStatus = enrolmentTypes.NOT_ENROLED;
                    this.$emit('on-edit-success', 'Group left');
                    if (this.group.isFavourite) { //assumed that wouldn't be a favourite group if re-added
                        this.group.isFavourite = false;
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
         * Toggle the group favourite status
         *
         * @param { Event }
         */
        async toggleFavourites (event) {
            this.isProcessing = true;
            gsap.from(event.currentTarget, { scale: 0.2, duration: 0.3 } ); //to help indicate selection
            if (this.isFavourite) {
                await this.removeFromFavourites();
            } else {
                await this.addToFavourites();
            }
            this.isProcessing = false;
        },

        /**
         * Remove from the users favourite list
         */
        async removeFromFavourites () {
            this.$emit('on-edit-start', 'Removing from favourites...');
            try {
                const responseData = await this.getResponseData(this.endpointFavourite, { isFavourite: '0' });
                if (responseData.error) {
                    this.$emit('on-edit-error', responseData.error);
                } else {
                    this.group.isFavourite = responseData.isFavourite;
                    this.$emit('on-edit-success', 'Removed from favourites');
                    await removeGroupFromSideMenu(this.group.id);
                }
            } catch (error) {
                console.warn(error);
                this.$emit('on-edit-error', 'Unable to remove from favourites');
            }
        },

        /**
         * Add to the users favourite list
         */
        async addToFavourites () {
            this.$emit('on-edit-start', 'Adding to favourites...');
            try {
                const responseData = await this.getResponseData(this.endpointFavourite, { isFavourite: '1' });
                if (responseData.error) {
                    this.$emit('on-edit-error', responseData.error);
                } else {
                    this.group.isFavourite = responseData.isFavourite;
                    this.$emit('on-edit-success', 'Added to favourites');
                    await addGroupToSideMenu(this.group.name, `/homepage/${this.group.id}/`);
                }
            } catch (error) {
                console.warn(error);
                this.$emit('on-edit-error', 'Unable to add to favourites');
            }
        },

        /**
         * Toggle the group favourite status
         *
         * @param { Event }
         */
        async toggleNotifications (event) {
            this.isProcessing = true;
            gsap.from(event.currentTarget, { scale: 0.2, duration: 0.3 } ); //to help indicate selection
            if (this.isNotificationsOn) {
                await this.removeFromNotifications();
            } else {
                await this.addToNotifications();
            }
            this.isProcessing = false;
        },

        /**
         * Remove the group from the users notifications list
         */
        async removeFromNotifications () {
            this.$emit('on-edit-start', 'Remove from notifications...');
            try {
                const responseData = await this.getResponseData(this.endpointNotify, { isNotificationsOn: '0' });
                if (responseData.error) {
                    this.$emit('on-edit-error', responseData.error);
                } else {
                    this.group.isNotificationsOn = responseData.isNotificationsOn;
                    this.$emit('on-edit-success', 'Removed from notifications');
                }
            } catch (error) {
                console.warn(error);
                this.$emit('on-edit-error', 'Unable to remove from notifications');
            }
        },

        /**
         * Add to the users notifications list
         */
        async addToNotifications () {
            this.$emit('on-edit-start', 'Adding to notifications...');
            try {
                const responseData = await this.getResponseData(this.endpointNotify, { isNotificationsOn: '1' });
                if (responseData.error) {
                    this.$emit('on-edit-error', responseData.error);
                } else {
                    this.group.isNotificationsOn = responseData.isNotificationsOn;
                    this.$emit('on-edit-success', 'Added to notifications');
                }
            } catch (error) {
                console.warn(error);
                this.$emit('on-edit-error', 'Unable to add to notifications');
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
                    <a :href="`/homepage/${group.id}`" :title="`Visit ${group.name}`">
                        <div class="card-class-image" :style="getImageStyles()"></div>
                    </a>
                    <transition name="slide">
                        <div class="group-actions" v-if="isEnrolled || isPrivate" :style="[ { pointerEvents: isProcessing ? 'none' : 'auto' } ]">
                            <button :class="['group-action', isFavourite ? 'icon-favourite' : 'icon-favourite-hollow']" title="Toggle Favourites" @click="toggleFavourites">Toggle Favourites</button>
                            <button v-if="!group.isForceFollow && !group.isMsgDisabled"
                                    :class="['group-action', isNotificationsOn ? 'icon-notifications' : 'icon-notifications-hollow']"
                                    title="Toggle Notifications" @click="toggleNotifications">Toggle Notifications</button>
                            <button v-if="group.isForceFollow || group.isMsgDisabled"
                                    :class="['group-action disabled', group.isForceFollow ? 'icon-notifications' : 'icon-notifications-hollow']"></button>
                        </div>
                    </transition>
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
                    <div v-if="isAdmin" class="expand private" key="admin">Group Admin</div>
                    <div v-else-if="isPrivate" class="expand private" key="private">Private</div>
                    <button v-else-if="isEnrolled" class="button expand icon-logout alert" :title="`Leave ${group.name}`" key="leave" @click="leave" :disabled="isProcessing">Leave</button>
                    <button v-else-if="isMembershipPending" class="button expand icon-logout alert" title="Cancel membership request"  key="membershipPending" @click="leave" :disabled="isProcessing">Cancel membership request</button>
                    <button v-else-if="isRequestToJoin" class="button expand icon-add submit" :title="`Request to join ${group.name}`" key="requestToJoin" @click="join" :disabled="isProcessing">Request to join</button>
                    <button v-else class="button expand icon-add submit" :title="`Join ${group.name}`" key="join" @click="join" :disabled="isProcessing">Join</button>
                </transition>
            </div>
        </section>
    </div>
</template>

<style lang="scss" scoped>

.group-image-container {
    overflow: hidden; //so can slide the .group-actions in and out
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
    transition: all 0.2s;
    &:before {
        color: white;
        font-size: 1.5rem;
        margin: 0;
        padding: 0;
    }
    &:hover {
        opacity: 0.8;
    }
}

.button {
    display: inline-block;
    margin: 0;
    padding: 1rem; //add extra padding
    &:disabled {
        color: white;
        opacity: 1;
    }
}

.disabled {
    pointer-events: none;
}

.private, .membership-pending  {
    background: #DDDDDD;
    color: #999999;
    padding: 1rem; //add extra padding
    text-align: center;
}

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s;
}
.fade-enter, .fade-leave-to {
    opacity: 0;
}

.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s ease-out;
}
.slide-enter-active, .slide-leave-to {
    transform: translateX(100%);
}
.slide-enter-to {
    transform: translateX(0%);
}

</style>
