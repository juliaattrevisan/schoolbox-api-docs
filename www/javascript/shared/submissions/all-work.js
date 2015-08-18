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

        postComment();

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
                    post: '[data-comment-post]'
                }
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Listenenr for adding a comment
         */
        function postComment() {
            $(document).on('click', config.comment.post, function(event) {
                event.preventDefault();

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