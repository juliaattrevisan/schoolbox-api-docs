<script>

/**
 * Provides the available options that can be bulk modified 
 * for the currently selected users
 */


export default {

    props: {
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
        isModifying: {
            type: Boolean, 
            required: true,
        }
    },

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
        }
    },

    methods: {

        /**
         * Update the selected users
         */
        async onUpdateUsersSelected () {
            // fetch(this.srcUserModify, {
            //     method: 'POST',
            //     body: this.getFormData(),
            // }).then((response) => response.json())
            //     .then((data) => {
            //             this.logData = data;
            //             this.$refs.modal.open();
            //             this.clearRows();
            //         }
            //     ).catch((error) => {
            //     console.log(error);
            // })
            try {
                const response = await fetch(this.srcUserModify, {
                    method: 'POST',
                    body: this.getUpdateUsersFormData(),
                });
                const responseData = await response.json();
                this.logData = responseData;
                this.$refs.modal.open();
            } catch (error) {
                console.warn(error);
            } finally {
                this.clearRows();
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

    }

}
</script>

<template>
    <div class="new-values">
        <div class="new-values__full-width">
            <h2>Bulk modify selected users</h2>
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
        <div class="new-values__full-width">
            <button @click.prevent="onUpdateUsersSelected" class="button submit">Update Selected</button>
        </div>
    </div>
</template>

<style lang="scss" scoped>

// @import './similarity-report';

.new-values {
    background: white;
    display: grid;
    grid-gap: 1.5rem;
    grid-template-columns: repeat(4, 1fr);
    grid-template-columns: repeat(auto-fill, minmax(max(var(300px), var(--grid-item--max-width)), 1fr));
    padding: 0.5rem;
    // @media (max-width: $tableToSingleColumn) {
    //     flex-direction: column;
    // }
}

    // .new-values__column { 
    //     // flex: 1 1 0; //keep both sides equal
    //     flex: 1 0 21%; /* explanation below */
    // }

    .new-values__full-width {
        grid-column: 1 / 5;
    }

.VueTables input[type=checkbox] { //override back to foundation styles *_*
    height: 0;
    min-height: 0;
    opacity: 0;
    position: absolute;
    width: 0;
}

// legend { 
//     margin-bottom: 0.5rem; //extra gap between it and the form element below
// }

// .option-list li {
//     display: inline-block; //rather than block, to line up with similarity score filter better
//     margin-right: 2rem;
//     @media (max-width: $tableToSingleColumn) {
//         display: block;
//     }
// }

</style>