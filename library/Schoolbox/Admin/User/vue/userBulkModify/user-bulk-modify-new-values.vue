<script>

/**
 * Provides the available options that can be bulk modified
 * for the currently selected users
 */

import RevealModal from '@schoolboxVue/components/reveal-modal';


export default {

    components: {
        RevealModal,
    },

    props: {
        srcUserModify: {
            type: String,
            required: true
        },
        campuses: {
            type: Array,
            required: true,
        },
        roles: {
            type: Array,
            required: true,
        },
        yearLevels: {
            type: Array,
            required: true,
        },
        houses: {
            type: Array,
            required: true
        },
        eportfolioEnabled: {
            type: Boolean,
            required: true,
        },
        selectedRows: {
            type: Array,
            required: true,
        },
        selectedRowsCountWithRowName: {
            type: String,
            required: true,
        },
    },

    emits: ['updated', 'log'],

    data () {
        return {
            updateFields: {
                campusIds: [],
                roleId: -1,
                yearLevelId: -1,
                houseId: -1,
                ePortfolio: -1,
                emailFromUserName: -1,
                externalMailClient: -1,
                webmail: -1,
                accountEnabled: -1,
                hideContacts: -1,
                hideTimetable: -1,
                positionTitle: '',
                displayGradeGraph: -1,
                revokeToken: -1,
            },
            updateFieldsOriginal: {}, //to reset to original values once an update is complete
            isProcessing: false,
            hasUpdated: false,
        }
    },

    computed: {

        /**
         * To change when processing
         *
         * @return { String }
         */
        updateButtonText () {
            if (this.isProcessing) {
                return `<em>Processing, please wait...</em>`;
            } else {
                return `Update Selected`;
            }
        },

    },

    mounted () {
        this.updateFieldsOriginal = Object.assign({}, this.updateFields); //to reset to original values
    },

    methods: {

        /**
         * Update the selected users
         */
        modify () {
            this.$refs.modal.open();
            this.hasUpdated = false; //return to its form state
        },

        /**
         * Update the selected users
         */
        async onUpdateUsersSelected () {
            try {
                this.isProcessing = true;
                const response = await fetch(this.srcUserModify, {
                    method: 'POST',
                    body: this.getUpdateUsersFormData(),
                });
                const responseData = await response.json();
                this.checkUpdateResponse(response, responseData);
            } catch (error) {
                console.warn(error);
                this.isProcessing = false;
            } finally {
                this.hasUpdated = true;
                this.isProcessing = false;
                this.updateFields = Object.assign({}, this.updateFieldsOriginal); //reset to original values
                this.$emit('updated');
            }
        },

        /**
         * Set up updating fields to POST
         *
         * @returns { FormData }
         */
        getUpdateUsersFormData () {
            const usersToModifyIDList = this.selectedRows.map(row => row.id);
            const formData = new FormData();
            usersToModifyIDList.forEach((item) => {
                formData.append('users[]', item);
            });
            for (const [key, value] of Object.entries(this.updateFields)) {
                formData.append(key, value);
            }
            return formData;
        },

        /**
         * Check for any issues in the returned update
         *
         * @param { Object }
         * @param { Object }
         */
        checkUpdateResponse (response, responseData) {
            let logData, hasDataError = false;
            if (response.ok) { //successful response
                hasDataError = false;
                logData = responseData;
            } else if (!response.ok && response.status === 501) { //otherwise check for 501 error
                hasDataError = true;
                logData = [responseData.title]; //which comes in with an error message
            } else {
                hasDataError = true;
                logData = ['Sorry, an unknown error has occurred. Please contact your IT support if the problem persists.'];
            }
            //close this modal before opening the log modal...
            this.onCloseSelected();
            this.$emit('log', {logData, hasDataError});
        },

        /**
         * Close the modal
         */
        onCloseSelected () {
            this.$refs.modal.close();
        }

    }

}
</script>

<template>
    <reveal-modal ref="modal" size="xlarge" :close-on-background-click="false" :is-close-disabled="isProcessing">
        <Transition name="fade" mode="out-in">
            <div class="new-values">
                <div class="new-values__full-width">
                    <h2>Bulk modify {{ selectedRowsCountWithRowName }} selected</h2>
                </div>
                <div>
                    <label>
                        Campus Involvement
                        <span data-tooltip class="hint has-tip tip-top"
                                title="One or more campuses that the users will be involved in.
                                Leave blank if you don't want to update their campuses.">?</span>
                    </label>
                    <ul class="option-list">
                        <li v-for="campus in campuses">
                            <input :id="'apply_campus-' + String(campus.id)" name='apply_campus[]'
                                    :value=campus.id v-model="updateFields.campusIds" type="checkbox"/>
                            <label class="checkbox-label"
                                    :for="'apply_campus-' + String(campus.id)">{{ campus.name }}</label>
                        </li>
                    </ul>
                    <label for="apply_role">Role</label>
                    <select id="apply_role" name="apply_role" v-model="updateFields.roleId">
                        <option value="-1">Unchanged</option>
                        <option v-for="role in roles" :value="role.id">{{ role.name }}</option>
                    </select>
                    <label for="apply_year_id">Year</label>
                    <select id="apply_year_id" name="apply_year_id" v-model="updateFields.yearLevelId">
                        <option value="-1" selected="selected">Unchanged</option>
                        <option value="">Not set</option>
                        <option v-for="yearLevel in yearLevels" :value="yearLevel.id">{{ yearLevel.name }}</option>
                    </select>
                </div>
                <div>
                    <label for="apply_position_title">
                        Position Title
                        <span data-tooltip class="hint has-tip tip-top"
                                title="Leave blank if you don't want to update their position title.">?</span>
                    </label>
                    <input id="apply_position_title" name="apply_position_title" type="text" v-model="updateFields.positionTitle">
                    <label for="apply_house_id">
                        House
                        <span data-tooltip class="hint has-tip tip-top"
                                title="Leave blank if you don't want to update their house.">?</span>
                    </label>
                    <select id="apply_house_id" name="apply_house_id" v-model="updateFields.houseId">
                        <option value="-1" selected="selected">Unchanged</option>
                        <option value="">Not set</option>
                        <option v-for="house in houses" :value="house.id">{{ house.name }}</option>
                    </select>
                    <label for="apply_eportfolio">ePortfolio</label>
                    <select id="apply_eportfolio" name="apply_eportfolio" v-model="updateFields.ePortfolio"
                            :disabled="!eportfolioEnabled">
                        <option value="-1">Unchanged</option>
                        <option value="1">Enabled</option>
                        <option value="0">Disabled</option>
                    </select>
                </div>
                <div>
                    <label for="apply_emailfromusername">Email Address From Username</label>
                    <select id="apply_emailfromusername" name="apply_emailfromusername"
                            v-model="updateFields.emailFromUserName">
                        <option value="-1">Unchanged</option>
                        <option value="1">Enabled</option>
                        <option value="0">Disabled</option>
                    </select>
                    <label for="apply_externalmailclient">Use External Mail Client</label>
                    <select id="apply_externalmailclient" name="apply_externalmailclient"
                            v-model="updateFields.externalMailClient">
                        <option value="-1">Unchanged</option>
                        <option value="1">Enabled</option>
                        <option value="0">Disabled</option>
                    </select>
                    <label for="apply_webmail">Enable Webmail Tab</label>
                    <select id="apply_webmail" name="apply_webmail" v-model="updateFields.webmail">
                        <option value="-1">Unchanged</option>
                        <option value="1">Enabled</option>
                        <option value="0">Disabled</option>
                    </select>
                    <label for="apply_accountenabled">Account Enabled</label>
                    <select id="apply_accountenabled" name="apply_accountenabled"
                            v-model="updateFields.accountEnabled">
                        <option value="-1">Unchanged</option>
                        <option value="1">Enabled</option>
                        <option value="0">Disabled</option>
                    </select>
                </div>
                <div class="new-values__column">
                    <label for="apply_hidecontacts">Hide Contact Details</label>
                    <select id="apply_hidecontacts" name="apply_hidecontacts" v-model="updateFields.hideContacts">
                        <option value="-1">Unchanged</option>
                        <option value="1">Enabled</option>
                        <option value="0">Disabled</option>
                    </select>
                    <label for="apply_hidetimetable">Hide Timetable</label>
                    <select id="apply_hidetimetable" name="apply_hidetimetable"
                            v-model="updateFields.hideTimetable">
                        <option value="-1">Unchanged</option>
                        <option value="1">Enabled</option>
                        <option value="0">Disabled</option>
                    </select>
                    <label for="apply_displaygradegraph">Display Comparative Graph on Grades</label>
                    <select id="apply_displaygradegraph" name="apply_displaygradegraph"
                            v-model="updateFields.displayGradeGraph">
                        <option value="-1">Unchanged</option>
                        <option value="1">Enabled</option>
                        <option value="0">Disabled</option>
                    </select>
                    <label for="revoke_token">Revoke Token</label>
                    <select id="revoke_token" name="revoke_token" v-model="updateFields.revokeToken">
                        <option value="-1">Unchanged</option>
                        <option value="1">Revoke</option>
                    </select>
                </div>
                <div v-if="selectedRows.length > 1000" class="new-values__full-width">
                    <div class="alert-box warning">Please note, as a large number of users has been selected, processing may take some time.</div>
                </div>
                <div class="new-values__full-width">
                    <button @click.prevent="onUpdateUsersSelected" class="button submit" :disabled="isProcessing" v-html="updateButtonText"></button>
                </div>
            </div>
        </Transition>
    </reveal-modal>
</template>

<style lang="scss" scoped>

$toTwoColumns: 1200px;
$toOneColumn: 600px;

.new-values {
    display: grid;
    grid-gap: 0 1.5rem;
    grid-template-columns: repeat(4, 1fr); //four equal columns
    padding: 0.5rem;
    @media (max-width: $toTwoColumns) {
        grid-template-columns: repeat(2, 1fr);
    }
    @media (max-width: $toOneColumn) {
        display: block;
    }
}

    .new-values__full-width {
        grid-column: 1 / span 4; //extend full grid width
        @media (max-width: $toTwoColumns) {
            grid-column: 1 / span 2;
        }
    }

textarea { //to prevent the potential for a massively long modal
    min-height: 200px;
    max-height: 600px;
}

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s;
}

.fade-enter, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
    opacity: 0;
}

</style>
