<script>

/**
 * Email DKIM
 */
import Toast from '@schoolboxVue/components/toast';
import RevealModal from '@schoolboxVue/components/reveal-modal';
import footerUI from '@schoolboxVue/components/vueTables2/mixinFooterUI.js';
import ClipboardJs from 'clipboard/dist/clipboard';

export default {

    components: {
        Toast,
        RevealModal,
        ClipboardJs
    },

    mixins: [
        footerUI //to place and keep the tables UI in the footer
    ],

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
                debounce: 100, //faster debounce as data is stored on the client side,
                headings: {
                    domain: 'Domain',
                    ['dateModified.formatted']: 'Date modified',
                    edit: ''
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
                orderBy: {
                    ascending: true,
                    column: 'domain'
                },
                perPage: 25,
                perPageValues: [25, 50, 100, 150, 200],
                resizableColumns: false,
                selectable: {
                    mode: '',
                },
                filterable: false,
                filterByColumn: false,
                sortable: ['domain', 'dateModified.formatted'], //the edit column doesn't need to be sortable
                sortIcon: {
                    is: 'glyphicon-sort',
                    base: 'glyphicon',
                    up: 'glyphicon-chevron-up',
                    down: 'glyphicon-chevron-down'
                },
                tabbable: false,
            },
            selectContainer: null,
            isShiftKeySelected: false, //to multi-select fields,
            hasSelectedShiftKey: false, //to hide a UI note
        }
    },
    mounted () {
        new ClipboardJs(
            this.$refs.copyValueButton,
            {
                target: () => this.$refs.dkimValue,
            }
        );
        new ClipboardJs(
            this.$refs.copyNameButton,
            {
                target: () => this.$refs.dkimName,
            }
        );
        new ClipboardJs(
            this.$refs.copyValueSplit0Button,
            {
                target: () => this.$refs.dkimValueSplit0,
            }
        );
        new ClipboardJs(
            this.$refs.copyValueSplit1Button,
            {
                target: () => this.$refs.dkimValueSplit1,
            }
        );
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
                console.log(error);
                this.hasLoadError = true; //display error note in the DOM
            } finally {
                this.selectedDomain = Object.assign({}, this.emptyDomain);
                this.hasDataLoaded = true; //update the display to show the list
            }
        },

        /**
         * To update the table to show the UI in the footer
         */
        onLoaded () {
            this.$nextTick(() => this.footerUI_update());
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
                const responseDat = await response.json();
                if (response.ok) {
                    this.$refs.toast.timeout('Success', true, 2000);
                } else {
                    this.$refs.toast.timeout('Failed - ' + responseDat.error, false, 2000);
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
                const response = await fetch(this.selectedDomain.deleteUrl, {
                    method: 'DELETE'
                });
                const responseData = await response.json();
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
                let dkimData = await response.json();
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
    },
}
</script>

<template>
    <div class="row actions-small-1">
        <div class="list-item">
            <div class="small-12 island">
                <h2 class="subheader">Domains</h2>
            </div>
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
        <v-client-table
            v-else-if="hasDataLoaded"
            ref="table"
            :data="rows"
            :columns="tableColumns"
            :options="tableOptions"
            @loaded="onLoaded"
        >
            <template #beforeTable>
            </template>
            <template #edit="props">
                <nav class="edit">
                    <a class="icon-eye" @click.prevent="onClickGetKey(props.row)" title="Get key"></a>
                    <a class="icon-modify" @click.prevent="onClickModifyDomain(props.row)" title="Modify domain"></a>
                    <a class="icon-delete" @click.prevent="onClickDeleteDomain(props.row)" title="Delete domain"></a>
                </nav>
            </template>
            <template #afterTable>
                <div class="VueTables__table-footer" ref="tableFooter"></div>
            </template>
        </v-client-table>
        <div v-else>
            <table class="skeleton">
                <thead>
                <tr>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td></td>
                </tr>
                <tr>
                    <td></td>
                </tr>
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
        <h2>DKIM for <strong>{{ domainName }}</strong></h2>
        <h3>Name</h3>
        <div class="input-group">
            <div class="small-12"><input ref="dkimName" readonly type="text" v-model="dkimName"></div>
            <div>
                <button ref="copyNameButton" class="icon-copy">Copy</button>
            </div>
        </div>
        <h3>Value (Full)</h3>
        <div class="input-group">
            <div class="small-12"><input ref="dkimValue" readonly type="text" v-model="dkimValue"></div>
            <div>
                <button ref="copyValueButton" class="icon-copy">Copy</button>
            </div>
        </div>
        <h3>Value (Split)</h3>
        <div class="input-group">
            <div class="small-12"><input ref="dkimValueSplit0" readonly type="text" v-model="dkimValueSplit0"></div>
            <div>
                <button ref="copyValueSplit0Button" class="icon-copy">Copy</button>
            </div>
        </div>
        <div class="input-group">
            <div class="small-12"><input ref="dkimValueSplit1" readonly type="text" v-model="dkimValueSplit1"></div>
            <div>
                <button ref="copyValueSplit1Button" class="icon-copy">Copy</button>
            </div>
        </div>
    </reveal-modal>
    <reveal-modal ref="deleteModal">
        <h2>Delete {{ selectedDomain.domain }}</h2>
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

</style>
