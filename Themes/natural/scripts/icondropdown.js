$(document).ready(function()
{
	$('#membergroup_has_badge, #icon_count_input, #icon_image_input').on('change', refreshIconPreview);
	refreshIconPreview();
});

function refreshIconPreview()
{
	if (!$('#membergroup_has_badge').prop('checked')) {
		$('#badge_config').hide();
		$('#icon_count_input').attr('min', 0);
		return;
	} else {
		$('#icon_count_input').attr('min', 1);
	}

	$('#badge_config').show();
	$('#badge_preview .image').empty();

	// Get the icon count element.
	var icon_count = $('#icon_count_input').val();
	if (icon_count == 0) {
		return;
	}

	var select_box = $('select#icon_image_input').val();

	var img = '<img alt="" src="' + sbb_default_theme_url + '/images/membericons/' + select_box + '">';
	var finalimg = '';
	for (var i = 0; i < icon_count; i++) {
		finalimg += img;
	}
	$('#badge_preview .image').html(finalimg);
}