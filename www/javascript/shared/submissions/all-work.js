(function() {

    /**
     * All Work
     */
    $.fn.allWork = function() {
    }

    /**
     * Set up actions for comments
     */
    $.fn.allWork.commentSetup = function(options) {
        options = options || {};

        var config = init(options, $(this));

        setupSaveComment();

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

                /* The button which submits the form when adding a new comment
                 */
                comment: {
                    newForm: {
                        id: '#create-comment',
                        button: '[data-comment-post]'
                    }
                },

                /* Metadata required by backend
                 */
                data: {
                    year: '',
                    semester: '',
                    studentId: '',
                    folderId: ''
                }
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Event listeners for saving new/modified comments
         */
        function setupSaveComment() {
            $(document).on('click', config.comment.newForm.button, function(event) {
                event.preventDefault();

                saveComment($(config.comment.newForm.id));
            });

            // set up other inline forms
        }

        /**
         * Save a new comment
         *
         * @param target
         * @returns {boolean}
         */
        function saveComment(target) {

            /* Don't use submitHandler in here, because that forces you to double-click
             * to actually perform an action - so do it programatically
             */
            $(target).validate({
                rules: {
                    comment: 'required'
                }
            });

            if ($(target).valid()) {

                /* Will be empty if creating (nonexistent)
                 */
                var commentId = $(target).find('input[name=commentId]').val();

                var data = {
                    commentId: commentId,
                    commentBody: $(target).find('textarea').val(),
                    studentVisibility: $('#vis-student-' + commentId).is(':checked') ? 1 : 0,
                    parentVisibility: $('#vis-parent-' + commentId).is(':checked') ? 1 : 0
                };

                /* Additional stuff needed when creating
                 */
                if (commentId == undefined) {
                    var create = {
                        studentVisibility: $('#vis-student').is(':checked') ? 1 : 0,
                        parentVisibility: $('#vis-parent').is(':checked') ? 1 : 0,
                        year: config.data.year,
                        semester: config.data.semester,
                        studentId: config.data.studentId,
                        categoryId: config.data.folderId
                    }

                    data = $.extend(data, create);
                }

                // submit the form
                $.ajax({
                    type: 'POST',
                    url: '/ajax/comment.php',
                    data: data,
                    success: function (data, responseText, xhr) {
                        // later this will be a nice ajax visual update, but for now just reload the page
                        window.location.reload();

                        // Perform loadComment();
                    }
                });
            }

            return false;
        }

        /**
         * Listenenr for adding a comment
         */
        function loadComment() {
            $(document).on('click', config.comment.post, function(event) {
                event.preventDefault();

                saveComment(event.target);

                var btn = $(this);
                var row = btn.closest('li');
                var commentId = row.attr('data-id');

                // retrieve comment
                $.ajax({
                    type: 'GET',
                    url: '/ajax/comment.php',
                    data: {
                        commentId: commentId
                    },
                    success: function(data) {

                        /* Set up modify forms
                         */
//                        // on success, replace row elements with form inputs
//                        var editForm = $('#create-comment').clone();
//                        editForm.removeAttr('id');
//                        editForm.addClass('edit-comment');
//                        editForm.find('[data-post-comment]').html('Update');
//                        editForm.find('textarea').text(data.body);
//
//                        $.each(['vis-student', 'vis-parent'], function (k, v) {
//                            editForm.find('#' + v).attr('id', v + '-' + commentId);
//                            editForm.find('label[for=' + v + ']').attr('for', v + '-' + commentId);
//                        });
//
//                        editForm.prepend(
//                            $('<input type="hidden" />').attr('name', 'commentId').val(commentId)
//                        );
//
//                        // safety check
//                        if (data.visibility) {
//                            var svis = editForm.find('input[name=visibility\\[student\\]]');
//                            var pvis = editForm.find('input[name=visibility\\[parent\\]]');
//
//                            if (data.visibility.student) {
//                                svis.prop('checked', true);
//                            }
//                            else {
//                                svis.prop('checked', false);
//                            }
//
//                            if (data.visibility.parent) {
//                                pvis.prop('checked', true);
//                            }
//                            else {
//                                pvis.prop('checked', false);
//                            }
//                        }
//
//                        row.find('td.actions').remove();
//                        row.find('td').last().attr('colspan', '2').html(editForm);
//                        row.removeClass('locked');
                    },
                    error: function (xhr) {
//                        // on failure, indicate failure
//                        row.removeClass('locked');
//                        alert.log(xhr.responseText);
                    }
                });
            });
        }
    }
}(jQuery));