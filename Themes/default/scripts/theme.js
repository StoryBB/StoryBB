$(function() {
	// Tooltips
	$('.preview').SBBtooltip();

	// Find all nested linked images and turn off the border
	$('a.bbc_link img.bbc_img').parent().css('border', '0');

	// Simple toggle for general use
	$('.toggle').click(function() {

		// Does it have a specific target? (A target is a valid css selector)
		if ($(this).is('[toggle-target]')) {

			var target = $(this).attr('toggle-target');
			$(target).toggleClass('active');
		}

		// Otherwise, just toggle itself
		else
			$(this).toggleClass('active');
	});
});

// Let jump_buttons know where the page is
$(window).on('load scroll', function() {
	var scrollBottom = $(document).height() - $(window).height() - $(window).scrollTop();

	// Only show the buttons if there is something to scroll
	if ($(document).height() - $(window).height() > 500)
		$('.jump_buttons').show();

	// Top of the page
	if ($(window).scrollTop() < 600)
		$('.jump_buttons').removeClass('bottom').addClass('top');

	// Bottom of the page
	else if (scrollBottom < 600)
		$('.jump_buttons').removeClass('top').addClass('bottom');

	else
		$('.jump_buttons').removeClass('top bottom');
});

// The purpose of this code is to fix the height of overflow: auto blocks, because some browsers can't figure it out for themselves.
function sbb_codeBoxFix()
{
	var codeFix = $('code');
	$.each(codeFix, function(index, tag)
	{
		if (is_webkit && $(tag).height() < 20)
			$(tag).css({height: ($(tag).height + 20) + 'px'});

		else if (is_ff && ($(tag)[0].scrollWidth > $(tag).innerWidth() || $(tag).innerWidth() == 0))
			$(tag).css({overflow: 'scroll'});

		// Holy conditional, Batman!
		else if (
			'currentStyle' in $(tag) && $(tag)[0].currentStyle.overflow == 'auto'
			&& ($(tag).innerHeight() == '' || $(tag).innerHeight() == 'auto')
			&& ($(tag)[0].scrollWidth > $(tag).innerWidth() || $(tag).innerWidth == 0)
			&& ($(tag).outerHeight() != 0)
		)
			$(tag).css({height: ($(tag).height + 24) + 'px'});
	});
}

// Add a fix for code stuff?
if (is_ie || is_webkit || is_ff)
	addLoadEvent(sbb_codeBoxFix);

// Toggles the element height and width styles of an image.
function smc_toggleImageDimensions()
{
	var images = $('img.bbc_img');

	$.each(images, function(key, img)
	{
		if ($(img).hasClass('resized'))
		{
			$(img).css({cursor: 'pointer'});
			$(img).on('click', function()
			{
				var size = $(this)[0].style.width == 'auto' ? '' : 'auto';
				$(this).css({width: size, height: size});
			});
		}
	});
}

// Add a load event for the function above.
addLoadEvent(smc_toggleImageDimensions);

function sbb_addButton(stripId, image, options)
{
	$('#' + stripId).append(
		'<a href="' + options.sUrl + '" class="button" ' + ('sCustom' in options ? options.sCustom : '') + ' ' + ('sId' in options ? ' id="' + options.sId + '_text"' : '') + '>' + options.sText + '</a>'
	);
}