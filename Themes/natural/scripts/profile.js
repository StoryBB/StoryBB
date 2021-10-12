// Prevent Chrome from auto completing fields when viewing/editing other members profiles
function disableAutoComplete()
{
	if (is_chrome && document.addEventListener)
		document.addEventListener("DOMContentLoaded", disableAutoCompleteNow, false);
}

// Once DOMContentLoaded is triggered, call the function
function disableAutoCompleteNow()
{
	for (var i = 0, n = document.forms.length; i < n; i++)
	{
		var die = document.forms[i].elements;
		for (var j = 0, m = die.length; j < m; j++)
			// Only bother with text/password fields?
			if (die[j].type == "text" || die[j].type == "password")
				die[j].setAttribute("autocomplete", "off");
	}
}

function calcCharLeft()
{
	var oldSignature = "", currentSignature = document.forms.creator.signature.value;
	var currentChars = 0;

	if (!document.getElementById("signatureLeft"))
		return;

	var editor;
	if (editor = $("#signature").data("sceditor"))
	{
		currentSignature = editor.val();
	}

	if (oldSignature != currentSignature)
	{
		oldSignature = currentSignature;

		var currentChars = currentSignature.replace(/\r/, "").length;
		if (is_opera)
			currentChars = currentSignature.replace(/\r/g, "").length;

		if (currentChars > maxLength)
			document.getElementById("signatureLeft").className = "error";
		else
			document.getElementById("signatureLeft").className = "";

		if (currentChars > maxLength)
			ajax_getSignaturePreview(false);
		// Only hide it if the only errors were signature errors...
		else if (currentChars <= maxLength)
		{
			// Are there any errors to begin with?
			if ($(document).has("#list_errors"))
			{
				// Remove any signature errors
				$("#list_errors").remove(".sig_error");

				// Don't hide this if other errors remain
				if (!$("#list_errors").has("li"))
				{
					$("#profile_error").css({display:"none"});
					$("#profile_error").html('');
				}
			}
		}
	}

	document.getElementById("signatureLeft").innerHTML = maxLength - currentChars;
}

function ajax_getSignaturePreview (showPreview)
{
	showPreview = (typeof showPreview == 'undefined') ? false : showPreview;

	// Is the error box already visible?
	var errorbox_visible = $("#profile_error").is(":visible");

	var editor, currentSignature;
	if (editor = $("#signature").data("sceditor"))
	{
		currentSignature = editor.val();
	}
	else
	{
		currentSignature = $("#signature").val();
	}

	$.ajax({
		type: "POST",
		url: sbb_scripturl + "?action=xmlhttp;sa=previews;xml",
		data: {item: "sig_preview", signature: currentSignature, user: $('input[name="u"]').attr("value")},
		context: document.body,
		success: function(request){
			if (showPreview)
			{
				var signatures = new Array("current", "preview");
				for (var i = 0; i < signatures.length; i++)
				{
					$("#" + signatures[i] + "_signature").css({display:""});
					$("#" + signatures[i] + "_signature_display").css({display:""}).html($(request).find('[type="' + signatures[i] + '"]').text() + '<hr>');
				}
			}

			if ($(request).find("error").text() != '')
			{
				// If the box isn't already visible...
				// 1. Add the initial HTML
				// 2. Make it visible
				if (!errorbox_visible)
				{
					// Build our HTML...
					var errors_html = '<span>' + $(request).find('[type="errors_occurred"]').text() + '</span><ul id="list_errors"></ul>';

					// Add it to the box...
					$("#profile_error").html(errors_html);

					// Make it visible
					$("#profile_error").css({display: ""});
				}
				else
				{
					// Remove any existing signature-related errors...
					$("#list_errors").remove(".sig_error");
				}

				var errors = $(request).find('[type="error"]');
				var errors_list = '';

				for (var i = 0; i < errors.length; i++)
					errors_list += '<li class="sig_error">' + $(errors).text() + '</li>';

				$("#list_errors").html(errors_list);
			}
			// If there were more errors besides signature-related ones, don't hide it
			else
			{
				// Remove any signature errors first...
				$("#list_errors").remove(".sig_error");

				// If it still has content, there are other non-signature errors...
				if (!$("#list_errors").has("li"))
				{
					$("#profile_error").css({display:"none"});
					$("#profile_error").html('');
				}
			}
		return false;
		},
	});
	return false;
}

function chars_ajax_getSignaturePreview (showPreview)
{
	showPreview = (typeof showPreview == 'undefined') ? false : showPreview;

	// Is the error box already visible?
	var errorbox_visible = $("#profile_error").is(":visible");

	$.ajax({
		type: "POST",
		url: sbb_scripturl + "?action=xmlhttp;sa=previews;xml",
		data: {item: "sig_preview", signature: $("#char_signature").data("sceditor").getText(), user: $('input[name="u"]').attr("value")},
		context: document.body,
		success: function(request){
			if (showPreview)
			{
				$('#sig_preview, #sig_preview_parsed').show();
				$('#sig_preview_parsed').html($(request).find('[type="preview"]').text() + '<dl></dl>');
			}

			if ($(request).find("error").text() != '')
			{
				// If the box isn't already visible...
				// 1. Add the initial HTML
				// 2. Make it visible
				if (!errorbox_visible)
				{
					// Build our HTML...
					var errors_html = '<span>' + $(request).find('[type="errors_occurred"]').text() + '</span><ul id="list_errors"></ul>';

					// Add it to the box...
					$("#profile_error").html(errors_html);

					// Make it visible
					$("#profile_error").css({display: ""});
				}
				else
				{
					// Remove any existing signature-related errors...
					$("#list_errors").remove(".sig_error");
				}

				var errors = $(request).find('[type="error"]');
				var errors_list = '';

				for (var i = 0; i < errors.length; i++)
					errors_list += '<li class="sig_error">' + $(errors).text() + '</li>';

				$("#list_errors").html(errors_list);
			}
			// If there were more errors besides signature-related ones, don't hide it
			else
			{
				// Remove any signature errors first...
				$("#list_errors").remove(".sig_error");

				// If it still has content, there are other non-signature errors...
				if (!$("#list_errors").has("li"))
				{
					$("#profile_error").css({display:"none"});
					$("#profile_error").html('');
				}
			}
		return false;
		},
	});
	return false;
}

function showAvatar()
{
	if (file.selectedIndex == -1)
		return;

	document.getElementById("avatar").src = avatardir + file.options[file.selectedIndex].value;
	document.getElementById("avatar").alt = file.options[file.selectedIndex].text;
	document.getElementById("avatar").alt += file.options[file.selectedIndex].text == size ? "!" : "";
	document.getElementById("avatar").style.width = "";
	document.getElementById("avatar").style.height = "";
}

function previewExternalAvatar(src)
{
	if (!document.getElementById("avatar"))
		return;

	var tempImage = new Image();

	tempImage.src = src;
	if (maxWidth != 0 && tempImage.width > maxWidth)
	{
		document.getElementById("avatar").style.height = parseInt((maxWidth * tempImage.height) / tempImage.width) + "px";
		document.getElementById("avatar").style.width = maxWidth + "px";
	}
	else if (maxHeight != 0 && tempImage.height > maxHeight)
	{
		document.getElementById("avatar").style.width = parseInt((maxHeight * tempImage.width) / tempImage.height) + "px";
		document.getElementById("avatar").style.height = maxHeight + "px";
	}
	document.getElementById("avatar").src = src;
}

function readfromUpload(input) {
	if (input.files && input.files[0]) {
		var reader = new FileReader();

		reader.onload = function (e) {

			// If there is an image already, hide it...
			if ($('#attached_image').length){
				$('#attached_image').remove();
			}

			if ($('#attached_image_new').length){
				$('#attached_image_new').remove();
			}

			var tempImage = new Image();
				tempImage.src = e.target.result;

			var uploadedImage = $('<img />', {
				id: 'attached_image_new',
				src: e.target.result,
				image: tempImage.width,
				height: tempImage.height,
			});

			if (maxWidth != 0 && uploadedImage.width() > maxWidth)
			{
				uploadedImage.height(parseInt((maxWidth * uploadedImage.height()) / uploadedImage.width()) + "px");
				uploadedImage.width(maxWidth + "px");
			}
			else if (maxHeight != 0 && uploadedImage.height() > maxHeight)
			{
				uploadedImage.width(parseInt((maxHeight * uploadedImage.width) / uploadedImage.height) + "px");
				uploadedImage.height(maxHeight + "px");
			}

			uploadedImage.appendTo($('#avatar_upload'));
		}

		reader.readAsDataURL(input.files[0]);
	}
}
