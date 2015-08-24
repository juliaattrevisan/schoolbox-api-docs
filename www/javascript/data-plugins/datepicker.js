/**
 * Generic datepicker setup, based on data attributes
 */
(function($){
    $(function(){
        $('[data-datepicker]').datepicker({
            constrainInput: false,
            onClose:function (string) {
                var input = $(this);
                $.getJSON('/ajax/strtotime.php', {string:string}, function (data) {
                    input.val(data.time).blur();
                })
            }
        });
    })
})(jQuery);
