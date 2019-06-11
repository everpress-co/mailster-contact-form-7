(function ($) {

	"use strict"

	$('#mailster-cf7-settings')
		.on('click', '.cf7-mailster-add-field', function (event) {
			event.preventDefault();

			$('#mailster-map').find('li').last().clone().appendTo('#mailster-map').find('select').val(0).parent().find('input').focus().select();

		})
		.on('click', '.cf7-mailster-remove-field', function (event) {
			event.preventDefault();

			if ($('#mailster-map').find('li').length > 1)
				$(this).parent().remove();

		});

})(jQuery);