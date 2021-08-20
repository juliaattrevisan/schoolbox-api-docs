/**
 * To load CKEditor on request and ensure consistent display of CKEditor options etc
 */


let __uniqueId = 0; //to ensure multiple instances of CKEditor on the same page have unique ids
 
export default {
 
    data () {
        return {
            cKEditor_isCKEditorLoading: false,
            cKEditor_isCKEditorLoaded: false,
        }
    },

    beforeCreate () {
        this.cKEditor_uniqueId = __uniqueId.toString();
        __uniqueId += 1;
    },

    computed: {

        /**
         * The text within the comment prompt button, will change to loading when selected to notify
         *
         * @returns { String }
         */
        cKEditor_getAddCommentButtonText () {
            if (this.cKEditor_isCKEditorLoading) return 'Loading';
            else return 'Add a comment'
        },

        /**
         * startupFocus: true on the CKEditor was not working when hiding by more practical display none means...
         *
         * @returns { Object }
         */
        cKEditor_getStyles () {
            return this.cKEditor_isCKEditorLoaded ? { 'position': 'static' } : { 'position': 'absolute', 'left': '-9999px' };
        },

        /**
         * Ensure there's something (anything) in the comment and that the form is not already processing
         *
         * @returns {Boolean}
         */
        cKEditor_isPostCommentDisabled () {
            if (this.newComment.trim() === '' || this.isProcessing) {
                return true;
            } else {
                return false;
            }
        }

    },
 
    methods: {

        /**
         * Loads ckeditor only when required rather than on page load
         */
        cKEditor_init () {

            this.cKEditor_isCKEditorLoading = true; //to replace 'Add a Comment' with 'Loading' once the prompt button is selected

            const loadScripts = async () => {
                
                try {
                    
                    await this.injectScript(this.cdnPrefix + '/js/ckeditor/ckeditor.js?v=' + this.siteVersion);
                    await this.injectScript(this.cdnPrefix + '/js/editor/jquery.editor.js?v=' + this.siteVersion);

                    $.fn.alaressEditor({
                        targetElements: $(`#comment-${this.cKEditor_uniqueId}`),
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
                                this.newComment = CKEDITOR.instances[`comment-${this.cKEditor_uniqueId}`].getData(); // Bind the content of CKEditor into the comment model on change
                            },
                            instanceReady: (event) => { //can display the loaded CKEditor now
                                this.cKEditor_isCKEditorLoading = false;
                                this.cKEditor_isCKEditorLoaded = true;
                            }
                        }
                    });

                } catch (error) {
                    this.$refs.toast.timeout('Loading comment box failed', false, 5000);
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
 