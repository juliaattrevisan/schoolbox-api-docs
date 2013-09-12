$(function() {
	$('#trigger').click(function() {
		$('.questionForm').each(function(index, element) {
			var id = $(element).attr('id').replace('questionForm-', '');

			var hadoken = new Function("$.fn." + $(element).data('plugin') + ".saveOne($('#questionForm-" + id + "'))");
			hadoken();
		});
	});
});