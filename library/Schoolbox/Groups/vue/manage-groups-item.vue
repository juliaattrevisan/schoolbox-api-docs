
<script>

/**
 * A single group item, can join/leave, add toggle favourites/notifications
 */

import enrolmentTypes from './mangeGroupsEnrolmentTypes';
import groupTypes from './manageGroupsGroupTypes';


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
        }
    },

    created () {
        this.initGroupProps();    
    },

    computed: {

        /**
         * Check if the group is private
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
         * 
         */
        initGroupProps () {
            this.enrolmentStatus = this.group.enrolmentStatus;
        },

        /**
         * Get the image style, adding in the cover image only if available
         * 
         * @returns { Object }
         */
        getImageStyles () {
            let imageStyles = {
                backgroundColor: `#${this.group.colour}`,
                borderBottom: `10px solid #${this.group.colour}`,
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
         * The default data to pass through
         * 
         * @returns { FormData }
         */
        getFormData () {
            const formData = new FormData();
            formData.append('group', this.group.id);
            return formData;
        },

        /**
         * 
         */
        async join () {
            this.isProcessing = true;
            this.$emit('on-edit-start', 'Updating');
            try {
                const formData = this.getFormData();
                formData.append('action', 'add');
                const response = await fetch(this.endpoint, {
                    method: 'POST',
                    body: formData,
                })
                const responseData = await response.json();
                if (!responseData.error) {
                    this.enrolmentStatus = enrolmentTypes.ENROLLED;
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
            this.$emit('on-edit-start', 'Updating');
            try {
                const formData = this.getFormData();
                formData.append('action', 'delete');
                const response = await fetch(this.endpoint, {
                    method: 'POST',
                    body: formData,
                })
                const responseData = await response.json();
                if (!responseData.error) {
                    this.enrolmentStatus = enrolmentTypes.NOT_ENROLLED;
                    this.$emit('on-edit-success', 'Group left');
                }
            } catch (error) {
                console.warn(error);
                this.$emit('on-edit-error', 'Unable to leave group');
            } finally {
                this.isProcessing = false;
            }
        },

        /**
         * 
         */
        addToFavourites () {
            
        },

        /**
         * 
         */
        removeFromFavourites () {
            
        },

        /**
         * 
         */
        notify () {
            
        },

        /**
         * 
         */
        unNotify () {
            
        },

    }

}

</script>

<template>
    <div class="small-12 medium-6 large-4 xlarge-3 island">
        <section>
            <div class="v-card small-12">
                <a class="group-image-link" :href="`/homepage/${group.id }`">
                    <div class="card-class-image" :style="getImageStyles()"></div>
                    <div class="group-actions">
                        <button class="group-action icon-favourite" title="Add to Favourites" @click="addToFavourites">Add to Favourites</button>
                        <button class="group-action icon-favourite-hollow" title="Remove from Favourites" @click="removeFromFavourites">Remove from Favourites</button>
                        <button class="group-action icon-notifications" title="Turn Group Notifications On" @click="removeFromFavourites">Turn Group Notifications On</button>
                        <button class="group-action icon-notifications-hollow" title="Turn Group Notifications Off" @click="removeFromFavourites">Turn Group Notifications Off</button>
                    </div>
                </a>
                <transition name="fade" mode="out-in">
                    <button v-if="isPrivate" class="button expand disabled" key="private" disabled>Private</button>
                    <button v-else-if="isEnrolled" class="button expand icon-logout alert" :title="`Leave ${group.name}`"key="leave" @click="leave">Leave</button>
                    <button v-else-if="isEnrolmentPending" class="button expand disabled" key="membershipPending" disabled>Membership pending</button>
                    <button v-else-if="isRequestToJoin" class="button expand icon-add" :title="`Request to join ${group.name}`" key="requestToJoin" @click="requestToJoin">Request to join</button>
                    <button v-else class="button expand icon-add" :title="`Join ${group.name}`" key="join" @click="join">Join</button>
                </transition>
                <div class="card-content classes groups actions-small-1">
                    <div class="list-item">
                        <h3><a :href="`/homepage/${group.id}`" :title="group.name">{{ group.name }}</a></h3>
                    </div>
                    <template v-if="group.isAccessWrite || group.isAccessAdmin">
                        <nav>
                            <a href="#view-actions" class="icon-more" :data-dropdown="`class-options${ group.id }`" aria-expanded="false" title="Actions"></a>
                        </nav>
                        <ul data-dropdown-content class="f-dropdown" :id="`class-options${ group.id }`">
                            <li v-if="group.isAccessWrite || group.isAccessAdmin">
                                <a href="#" class="icon-modify">Modify Folder</a>
                            </li>
                            <li v-if="group.isAccessWrite || group.isAccessAdmin">
                                <a :href="`/statistics/homepage/${group.id}`" class="icon-report">View Statistics</a>
                            </li>
                            <li v-if="group.isAccessAdmin">
                                <a :href="`/group/modify/${group.id}`" class="icon-group">Modify Group</a>
                            </li>
                        </ul>
                    </template>
                </div>
            </div>
        </section>
    </div>
</template>

<style lang="scss" scoped>

.group-image-link {
    display: block;
    position: relative;
}

.group-actions {
    display: inline-block;
    position: absolute;
    right: 0.5rem;
    top: 0.5rem;
}

.button {
    padding: 1rem; //add extra padding
}

.group-action {
    background: none;
    font-size: 0;
    &:before {
        color: var(--content-ui-foreground);
        font-size: 1.5rem;
        margin: 0;
        padding: 0;
        text-shadow: 1px 1px 2px rgb(73, 73, 73);
    }
}

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s;
}
.fade-enter, .fade-leave-to {
    opacity: 0;
}

</style>