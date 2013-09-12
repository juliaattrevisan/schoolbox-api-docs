$(function() {
	$('#trigger').click(function() {
		$('.questionForm').each(function(index, element) {
			var id = $(element).attr('id').replace('questionForm-', '');

			var plugin = "$.fn." + $(element).data('plugin') + ".saveOne($('#questionForm-" + id + "'))";

			var hadoken = new Function(plugin);
			hadoken();
		});
	});
});