(function() {
  /**
   * The Draft plugin detects differences in the elements listed in config.inputs
   * and config.textareas and asynchronously saves when needed
   *
   * This also quietly updates the url and form action so if you refresh the page
   * without hitting the submit button, you won't reload what the screen originally
   * did which might actually be a Create screen.
   *
   * @param options
   */
  $.fn.draft = function(options) {
    options = options || {};

    var config = init(options, $(this));

    // Watch the content for changes
    initAutosave();

    /**
     * Init
     *
     * @param options
     * @param element
     * @returns {*}
     */
    function init(options, element) {
      var defaults = {
        element: element,

        // Toast element
        toaster: $("#saveDraft"),

        // The grace period for after a user makes a change and when the draft save trigger (in seconds)
        interval: 3,

        // Array of selectors which will have .val() checked for changes
        inputs: [],

        // CKEditors to listen for changes on
        textareas: [],

        // Attachzones to listen for changes on
        attachzones: [],

        // Datepickers to listen for changes on
        // These shouldn't go into 'inputs' because of the strtotime functionality we have on date fields
        datepickers: [],

        /* Activation element
         * The thing that gets disabled while saving
         */
        activator: element.find("button[type=submit]"),

        /**
         * Called on successful save.
         * @param {bool} wasAutoSave
         */
        onSaveSuccess: $.noop,

        // The infamous stallSubmit
        staller: null
      };

      return $.extend(defaults, options);
    }

    /**
     * Init listeners for changes, firing save() when there's changes (with a timeout)
     */
    function initAutosave() {
      var draftTimeout;
      var deferred;

      // Listen for changes on inputs
      config.element.on(
        "change keyup",
        config.inputs.join(", "),
        checkForChanges
      );

      // Listen for changes on CKEditors
      var i;
      for (i = 0; i < config.textareas.length; i++) {
        var key = config.textareas[i];
        CKEDITOR.instances[key].on("change", checkForChanges); // CKE seems to trigger change on keyup so no need to listen for that here
      }

      // Listen for changes to attachzones
      config.element.on(
        "success.attachzone removedfile.attachzone",
        config.attachzones.join(", "),
        checkForChanges
      );

      // Listen for changes to datepicker fields
      config.element.on("datepickerChange", checkForChanges);

      /**
       * The common function triggered on the listeners above:
       * It will reset the draft timeout and deferreds and line up another draft save
       *
       * I've deliberately put this inside the scope of initAutosave in order to directly modify the draftTimeout & deferred vars
       * Alternatively I could've returned the vars in this function and reassigned them above.
       */
      function checkForChanges() {
        // kill any other timeout pending, and resolve the deferred
        clearTimeout(draftTimeout);
        if (deferred !== undefined) {
          deferred.resolve();
        }

        // (re)set the deferred
        deferred = $.Deferred();

        // Set a timeout for the draft to save
        draftTimeout = setTimeout(function() {
          save(deferred);
        }, config.interval * 1000);

        // install the deferred as stalling form submit
        if (config.staller) {
          config.staller.stallSubmit(deferred);
        }
      }
    }

    /**
     * Save the data via ajax, showing a toast for the user
     *
     * @param {jQuery.Deferred} deferred - a deferred object to resolve on successful save
     */
    function save(deferred) {
      // Init toast
      config.toaster.toastActivate({
        text: "Saving..."
      });

      var formContents = $(config.element).serializeArray();

      /* Special treatment because ck reasons
       */
      for (var i = 0; i < config.textareas.length; i++) {
        var key = config.textareas[i];

        formContents.push({
          name: key,
          value: CKEDITOR.instances[key].getData()
        });
      }

      /* Send draft flag
       */
      formContents.push({ name: "draft", value: true });

      /* Transact
       */
      $.ajax({
        type: config.element.attr("method"),
        url: config.element.data("action-draft"),
        data: formContents,
        dataType: "json"
      })
        .done(function(responseText) {
          config.toaster.toastTimeout({
            text: "Saved",
            css: "success"
          });

          config.onSaveSuccess(responseText);

          // Resolve the deferred for stallSubmit
          deferred.resolve();
        })
        .error(function(response) {
          var temp = $.parseJSON(response.responseText);

          config.toaster.toastTimeout({
            text: "Error: " + temp.message,
            css: "alert"
          });
        });
    }
  };
})(jQuery);
