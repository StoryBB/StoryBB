function ajax_getSheetPreview ()
{
	$.ajax({
		type: "POST",
		url: sbb_scripturl + "?action=xmlhttp;sa=previews;xml",
		data: {item: "char_sheet_preview", sheet: $("#message").data('sceditor').val()},
		context: document.body,
		success: function(request){
			$("#box_preview").css({display:""});
			$("#sheet_preview").html($(request).find('sheet').text());
			if ($(request).find("error").text() != '')
			{
				$("#errors").css({display:""});
				var errors_html = '';
				var errors = $(request).find('error').each(function() {
					errors_html += $(this).text() + '<br>';
				});

				$(document).find("#error_list").html(errors_html);
			}
			else
			{
				$("#errors").css({display:"none"});
				$("#error_list").html('');
			}
		return false;
		},
	});
	return false;
}

$(document).ready(function() {
	$("#preview_sheet").click(function(e) {
		e.preventDefault();
		return ajax_getSheetPreview();
	});
});