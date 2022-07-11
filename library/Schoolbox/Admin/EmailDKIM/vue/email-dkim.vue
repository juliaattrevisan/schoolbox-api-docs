<script>

/**
 * Email DKIM
 */
import Toast from '@schoolboxVue/components/toast';
import RevealModal from '@schoolboxVue/components/reveal-modal';
import { timeout } from '@js/util/jsUtils';


export default {

    components: {
        Toast,
        RevealModal,
    },

    props: {
        srcDomainList: {
            type: String,
            required: true
        },
        srcAddDomain: {
            type: String,
            required: true
        },
        domains: {
            type: Array,
        },
    },

    data () {
        return {
            hasDataLoaded: false, //show loading display until true
            hasLoadError: false, //display load error
            rows: [],
            emptyDomain: {
                id: 0,
                domain: '',
                dkimUrl: '',
                deleteUrl: '',
            },
            selectedDomain: {
                id: 0,
                domain: '',
                dkimUrl: '',
                deleteUrl: '',
            },
            // values for dkim modal
            domainName: '',
            dkimName: '',
            dkimValue: '',
            dkimValueSplit0: '',
            dkimValueSplit1: '',
            tableColumns: ['domain', 'dateModified.formatted', 'edit'],
            tableOptions: {
                columnsClasses: {
                    ['dateModified.formatted']: 'date-modified-td ', //to hide the column for mobile
                    edit: 'edit-td ', //to specify a width
                },
                customSorting: {
                    ['dateModified.formatted']: (ascending) => { //sort date modified accurately by the timestamp
                        return (a, b) => {
                            if (ascending) {
                                return a.dateModified.timestamp >= b.dateModified.timestamp ? 1 : -1;
                            }
                            return a.dateModified.timestamp <= b.dateModified.timestamp ? 1 : -1;
                        }
                    },
                },
                debounce: 100, //faster debounce as data is stored on the client side,
                filterable: false,
                filterByColumn: false,
                headings: {
                    domain: 'Domain',
                    ['dateModified.formatted']: 'Date modified',
                    edit: '' //to not show any heading
                },
                orderBy: { //initial sorting display
                    ascending: true,
                    column: 'domain'
                },
                perPage: 9999, //absurdly large value to avoid pagination as there's no footer
                resizableColumns: false,
                sortable: ['domain', 'dateModified.formatted'], //currently no columns are sortable?
                tabbable: false,
                texts: {
                    count: '' //don't show any 'x record' at the bottom of the table
                },
            },
        }
    },

    mounted () {
        this.loadRows();
    },

    methods: {

        /**
         * Load domains
         */
        async loadRows () {
            try {
                const response = await fetch(this.srcDomainList, {
                    method: 'GET',
                    responseType: 'json'
                });
                this.rows = await response.json();
            } catch (error) {
                console.warn(error);
                this.hasLoadError = true; //display error note in the DOM
            } finally {
                this.selectedDomain = Object.assign({}, this.emptyDomain);
                this.hasDataLoaded = true; //update the display to show the list
            }
        },

        /**
         * Selected modify is complete, so clear the rows and update the data
         */
        onUpdated () {
            this.loadRows(); //refresh the data
        },

        /**
         * Open new domain modal
         */
        onClickAddDomain () {
            this.selectedDomain = Object.assign({}, this.emptyDomain);
            this.$refs.domainModal.open();
        },

        /**
         * Open modify domain modal
         * @param toggledDkim
         */
        onClickModifyDomain (toggledDkim) {
            this.selectedDomain = toggledDkim;
            this.$refs.domainModal.open();
        },

        /**
         * Canceled add/modify domain modal
         */
        onCancelAddDomain () {
            this.$refs.domainModal.close();
            this.selectedDomain = Object.assign({}, this.emptyDomain);
        },

        /**
         * Creating/updating domain
         * @returns {Promise<void>}
         */
        async onAddDomain () {
            this.$refs.toast.activate('Saving...');
            try {
                const response = await fetch(this.srcAddDomain, {
                    method: 'POST',
                    body: this.getFormData(),
                });
                const responseData = await response.json();
                if (response.ok) {
                    this.$refs.toast.timeout('Success', true, 2000);
                } else {
                    this.$refs.toast.timeout('Failed - ' + responseData.error, false, 2000);
                }
                this.$refs.domainModal.close();
                await this.loadRows();
            } catch (error) {
                console.log(error);
                this.$refs.toast.timeout('Failed', false, 2000);
            } finally {
                this.selectedDomain = Object.assign({}, this.emptyDomain);
            }
        },

        /**
         * Open delete domain modal
         * @param toggledDkim
         */
        onClickDeleteDomain (toggledDkim) {
            this.selectedDomain = toggledDkim;
            this.$refs.deleteModal.open();
        },

        /**
         * Cancel delete modal
         */
        onCancelDeleteDomain () {
            this.$refs.deleteModal.close();
            this.selectedDomain = Object.assign({}, this.emptyDomain);
        },

        /**
         * Delete domain
         * @returns {Promise<void>}
         */
        async onDeleteDomain () {
            this.$refs.toast.activate('Deleting...');
            try {
                await fetch(this.selectedDomain.deleteUrl, {
                    method: 'DELETE'
                });
                this.$refs.toast.timeout('Deleted', true, 2000);
                this.$refs.deleteModal.close();
                await this.loadRows();
            } catch (error) {
                this.$refs.toast.timeout('Failed', false, 2000);
                console.log(error);
            } finally {
                this.selectedDomain = Object.assign({}, this.emptyDomain);
            }
        },

        /**
         * Set up domain array to POST
         *
         * @returns {FormData}
         */
        getFormData () {
            const formData = new FormData();
            formData.append('domain', this.selectedDomain.domain);
            formData.append('id', this.selectedDomain.id);
            return formData;
        },

        /**
         * Get DKIM data
         * @param toggledDkim
         * @returns {Promise<void>}
         */
        async onClickGetKey (toggledDkim) {
            try {
                const response = await fetch(toggledDkim.dkimUrl, {
                    method: 'GET',
                    responseType: 'json'
                });
                const dkimData = await response.json();
                this.domainName = dkimData[0].domain;
                this.dkimName = dkimData[0].dkimName;
                this.dkimValue = dkimData[0].dkimValue;
                // TODO: remove hard coded index
                this.dkimValueSplit0 = dkimData[0].dkimValueSplit[0];
                this.dkimValueSplit1 = dkimData[0].dkimValueSplit[1];
                this.$refs.dkimModal.open();
            } catch (error) {
                console.log(error);
                this.hasLoadError = true; //display error note in the DOM
            } finally {
                this.selectedDomain = Object.assign({}, this.emptyDomain);
                this.hasDataLoaded = true; //update the display to show the list
            }
        },

        /**
         * Copy the linked text value 
         *
         * @param { Event }
         * @param { String }
         */
        async onCopySelected (event, id) {
            const copyText = document.getElementById(id);
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value);
            event.target.innerText = 'Copied';
            await timeout(2000);
            event.target.innerText = 'Copy';
        },

    },

}
</script>

<template>
    <div class="actions-small-1">
        <div class="list-item">
            <h2 class="subheader">Domains</h2>
        </div>
        <nav>
            <a @click.prevent="onClickAddDomain" class="icon-add" title="Add a domain"></a>
        </nav>
    </div>
    <div>
        <div v-if="hasLoadError">
            <section class="content">
                <div tableData-alert class="alert-box alert">
                    Sorry, we are currently unable to load. Please contact your support it the problem persists.
                </div>
            </section>
        </div>
        <div v-else-if="hasDataLoaded && rows.length === 0" id="listEmptyState" class="empty-state">
            <i class="icon-email"></i>
            <p>DKIM, is an email standard that helps validate emails have come from a trusted source.</p>
            <p>By using public/private keys a 3rd party can authorise emails signed by your Schoolbox server as
                valid and trusted to avoid spam filters and impersonation. For each domain that you control and your
                users send email from you must setup a DKIM key and add then add the appropriate records to your DNS
                zone.</p>
        </div>
        <v-client-table
            v-else-if="hasDataLoaded && rows.length > 0"
            :data="rows"
            :columns="tableColumns"
            :options="tableOptions"
        >
            <template #domain="props">
                <a @click.prevent="onClickGetKey(props.row)" title="View DKIM TXT Record">{{ props.row.domain }}</a>
            </template>
            <template #edit="props">
                <nav class="edit">
                    <a class="icon-eye" @click.prevent="onClickGetKey(props.row)" title="View DKIM TXT Record"></a>
                    <a class="icon-modify" @click.prevent="onClickModifyDomain(props.row)" title="Modify domain"></a>
                    <a class="icon-delete" @click.prevent="onClickDeleteDomain(props.row)" title="Delete domain"></a>
                </nav>
            </template>
        </v-client-table>
        <div v-else>
            <table class="skeleton">
                <tbody>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                    <tr><td></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <toast ref="toast"></toast>
    <reveal-modal ref="domainModal">
        <h2>Domain name</h2>
        <form ref="form">
            <input type="text" placeholder="Domain" v-model="selectedDomain.domain">
            <input type="hidden" v-model="selectedDomain.id">
            <ul class="flex-list buttons">
                <li>
                    <button @click.prevent="onAddDomain" class="button submit">
                        <template v-if="selectedDomain.id === 0">Add</template>
                        <template v-else>Modify</template>
                    </button>
                </li>
                <li>
                    <button @click.prevent="onCancelAddDomain" class="button">Cancel</button>
                </li>
            </ul>
        </form>
    </reveal-modal>
    <reveal-modal ref="dkimModal">
        <h2>DKIM TXT Record for <strong>{{ domainName }}</strong></h2>
        <h3>TXT Record Name</h3>
        <div class="input-group">
            <input id="dkimName" readonly type="text" v-model="dkimName">
            <button class="icon-copy" @click.prevent="onCopySelected($event, 'dkimName')">Copy</button>
        </div>
        <h3>TXT Record Value</h3>
        <div class="input-group">
            <input id="dkimValue" readonly type="text" v-model="dkimValue">
            <button class="icon-copy" @click.prevent="onCopySelected($event, 'dkimValue')">Copy</button>
        </div>
        <h3>TXT Record Value (split to 255 character parts)</h3>
        <div class="input-group">
            <input id="dkimValueSplit0" readonly type="text" v-model="dkimValueSplit0">
            <button class="icon-copy" @click.prevent="onCopySelected($event, 'dkimValueSplit0')">Copy</button>
        </div>
        <div class="input-group">
            <input id="dkimValueSplit1" readonly type="text" v-model="dkimValueSplit1">
            <button class="icon-copy" @click.prevent="onCopySelected($event, 'dkimValueSplit1')">Copy</button>
        </div>
    </reveal-modal>
    <reveal-modal ref="deleteModal">
        <h2>Please Confirm</h2>
        <p>You are about to delete the DKIM keys for <b>{{ selectedDomain.domain }}</b>, are you sure you want to proceed?</p>
        <form>
            <ul class="flex-list buttons">
                <li>
                    <button @click.prevent="onDeleteDomain" class="button submit">Delete</button>
                </li>
                <li>
                    <button @click.prevent="onCancelDeleteDomain" class="button">Cancel</button>
                </li>
            </ul>
        </form>
    </reveal-modal>
</template>

<style lang="scss">

@import '@schoolboxVue/components/vueTables2/vueTables2';

.VueTables table {
    table-layout: fixed; //to ensure the flex within can shrink all the way down
}

.VueTables__table td {
    padding: 0.25rem 0.5rem 0.25rem 0.5rem;
    vertical-align: middle;
    @media (max-width: 700px) {
        padding: 0.5rem 0.5rem 0 0;
    }
}

.VueTables__table .edit-td {
    padding-left: 0; //bring it inline with the right side border
    width: 7rem; //keep the edit icon on the right side
}

.edit {
    display: flex;
    height: 2.5rem;
    width: 7rem;
}

.edit a {
    color: var(--content-ui-foreground);
    line-height: 2.5;
    opacity: 0.7;
    padding-left: 8px; //move the pencil into the centre
}

.date-modified-td {
    width: 15.625rem; //keep on the right hand side, and don't alter width when filtering changes
    @media (max-width: 1000px) {
        width: 9.375rem;
    }
    @media (max-width: 700px) {
        display: none; //hide on mobile, as the date will then be shown within view-all-files-name.vue
    }
}


</style>
