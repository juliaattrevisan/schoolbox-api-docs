/**
 * 
 */



let __uuid = 0;
 
export default {
 
    data () {
        return {
            isCKEditorLoading: false,
            loadCKEditor_isCKEditorLoaded: false,
        }
    },

    beforeCreate () {
        this.uuid = __uuid.toString();
        __uuid += 1;
    },

    computed: {

        /**
         * The text within the comment prompt button, will change to loading when selected to notify
         *
         * @returns { String }
         */
        loadCKEditor_getAddCommentButtonText () {
            if (this.isCKEditorLoading) return 'Loading';
            else return 'Add a comment'
        },

        /**
         * startupFocus: true on the CKEditor was not working when hiding by more practical display none means...
         *
         * @returns { Object }
         */
        loadCKEditor_getStyles () {
            return this.loadCKEditor_isCKEditorLoaded ? { 'position': 'static' } : { 'position': 'absolute', 'left': '-9999px' };
        },

    },
 
    methods: {
        
        /**
         * Loads ckeditor only when required rather than on page load
         */
        loadCKEditor_init () {

            //to replace 'Add a Comment' with 'Loading' once the prompt button is selected
            this.isCKEditorLoading = true;

            const loadScripts = async () => {
                console.warn(`#comment-${this.uuid}`);
                try {
                    
                    await this.injectScript(this.cdnPrefix + '/js/ckeditor/ckeditor.js?v=' + this.siteVersion);
                    await this.injectScript(this.cdnPrefix + '/js/editor/jquery.editor.js?v=' + this.siteVersion);

                    $.fn.alaressEditor({
                        targetElements: $(`#comment-${this.uuid}`),
                        startupFocus: true,
                        height: '4rem',
                        removePlugins: 'uploadimage,schoolboxanchor',
                        removeButtons: 'Subscript,Superscript,RemoveFormat,Table,HorizontalRule,SpecialChar,Embed',
                        toolbarGroups: [
                            {name: 'basicstyles', groups: ['basicstyles', 'cleanup']},
                            {name: 'paragraph', groups: ['list', 'blocks', 'bidi']},
                            {name: 'insert', groups: ['insert']},
                            {name: 'schoolbox', groups: ['schoolboxlink']}
                        ],
                        on: {
                            change: (event) => {
                                this.pendingComment = CKEDITOR.instances[`comment-${this.uuid}`].getData(); // Bind the content of CKEditor into the comment model on change
                            },
                            instanceReady: (event) => { //can display the loaded CKEditor now
                                this.isCKEditorLoading = false;
                                this.loadCKEditor_isCKEditorLoaded = true;
                            }
                        }
                    });

                } catch (error) {
                    this.$refs.toast.timeout('Loading comments failed', false, 5000);
                }
            }

            loadScripts();

        },

        /**
         * Async inject a <script> tag into the DOM
         * https://stackoverflow.com/a/39008859
         *
         */
        injectScript (src) {
            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = src;
                script.addEventListener('load', resolve);
                script.addEventListener('error', e => reject(e.error));
                document.head.appendChild(script);
            });
        },
        
    }
 
}
 