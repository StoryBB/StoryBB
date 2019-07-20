/**
 * SBBtooltip, Basic JQuery function to provide styled tooltips
 *
 * - will use the hoverintent plugin if available
 * - shows the tooltip in a div with the class defined in tooltipClass
 * - moves all selector titles to a hidden div and removes the title attribute to
 *   prevent any default browser actions
 * - attempts to keep the tooltip on screen
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

(function($) {
	$.fn.SBBtooltip = function(oInstanceSettings) {
		$.fn.SBBtooltip.oDefaultsSettings = {
			followMouse: 1,
			hoverIntent: {sensitivity: 10, interval: 300, timeout: 50},
			positionTop: 12,
			positionLeft: 12,
			tooltipID: 'sbb_tooltip', // ID used on the outer div
			tooltipTextID: 'sbb_tooltipText', // as above but on the inner div holding the text
			tooltipClass: 'tooltip', // The class applied to the outer div (that displays on hover), use this in your css
			tooltipSwapClass: 'sbb_swaptip', // a class only used internally, change only if you have a conflict
			tooltipContent: 'html' // display captured title text as html or text
		};

		// account for any user options
		var oSettings = $.extend({}, $.fn.SBBtooltip.oDefaultsSettings , oInstanceSettings || {});

		// move passed selector titles to a hidden span, then remove the selector title to prevent any default browser actions
		$(this).each(function()
		{
			var sTitle = $('<span class="' + oSettings.tooltipSwapClass + '">' + htmlspecialchars(this.title) + '</span>').hide();
			$(this).append(sTitle).attr('title', '');
		});

		// determine where we are going to place the tooltip, while trying to keep it on screen
		var positionTooltip = function(event)
		{
			var iPosx = 0;
			var iPosy = 0;

			if (!event)
				var event = window.event;

			if (event.pageX || event.pageY)
			{
				iPosx = event.pageX;
				iPosy = event.pageY;
			}
			else if (event.clientX || event.clientY)
			{
				iPosx = event.clientX + document.body.scrollLeft + document.documentElement.scrollLeft;
				iPosy = event.clientY + document.body.scrollTop + document.documentElement.scrollTop;
			}

			// Position of the tooltip top left corner and its size
			var oPosition = {
				x: iPosx + oSettings.positionLeft,
				y: iPosy + oSettings.positionTop,
				w: $('#' + oSettings.tooltipID).width(),
				h: $('#' + oSettings.tooltipID).height()
			}

			// Display limits and window scroll postion
			var oLimits = {
				x: $(window).scrollLeft(),
				y: $(window).scrollTop(),
				w: $(window).width() - 24,
				h: $(window).height() - 24
			};

			// don't go off screen with our tooltop
			if ((oPosition.y + oPosition.h > oLimits.y + oLimits.h) && (oPosition.x + oPosition.w > oLimits.x + oLimits.w))
			{
				oPosition.x = (oPosition.x - oPosition.w) - 45;
				oPosition.y = (oPosition.y - oPosition.h) - 45;
			}
			else if ((oPosition.x + oPosition.w) > (oLimits.x + oLimits.w))
			{
				oPosition.x = oPosition.x - (((oPosition.x + oPosition.w) - (oLimits.x + oLimits.w)) + 24);
			}
			else if (oPosition.y + oPosition.h > oLimits.y + oLimits.h)
			{
				oPosition.y = oPosition.y - (((oPosition.y + oPosition.h) - (oLimits.y + oLimits.h)) + 24);
			}

			// finally set the position we determined
			$('#' + oSettings.tooltipID).css({'left': oPosition.x + 'px', 'top': oPosition.y + 'px'});
		}

		// used to show a tooltip
		var showTooltip = function(){
			$('#' + oSettings.tooltipID + ' #' + oSettings.tooltipTextID).show();
		}

		// used to hide a tooltip
		var hideTooltip = function(valueOfThis){
			$('#' + oSettings.tooltipID).fadeOut('slow').trigger("unload").remove();
		}

		// used to keep html encoded
		function htmlspecialchars(string)
		{
			return $('<span>').text(string).html();
		}

		// for all of the elements that match the selector on the page, lets set up some actions
		return this.each(function(index)
		{
			// if we find hoverIntent use it
			if ($.fn.hoverIntent)
			{
				$(this).hoverIntent({
					sensitivity: oSettings.hoverIntent.sensitivity,
					interval: oSettings.hoverIntent.interval,
					over: sbb_tooltip_on,
					timeout: oSettings.hoverIntent.timeout,
					out: sbb_tooltip_off
				});
			}
			else
			{
				// plain old hover it is
				$(this).hover(sbb_tooltip_on, sbb_tooltip_off);
			}

			// create the on tip action
			function sbb_tooltip_on(event)
			{
				// If we have text in the hidden span element we created on page load
				if ($(this).children('.' + oSettings.tooltipSwapClass).text())
				{
					// create a ID'ed div with our style class that holds the tooltip info, hidden for now
					$('body').append('<div id="' + oSettings.tooltipID + '" class="' + oSettings.tooltipClass + '"><div id="' + oSettings.tooltipTextID + '" style="display:none;"></div></div>');

					// load information in to our newly created div
					var tt = $('#' + oSettings.tooltipID);
					var ttContent = $('#' + oSettings.tooltipID + ' #' + oSettings.tooltipTextID);

					if (oSettings.tooltipContent == 'html')
						ttContent.html($(this).children('.' + oSettings.tooltipSwapClass).html());
					else
						ttContent.text($(this).children('.' + oSettings.tooltipSwapClass).text());

					oSettings.tooltipContent

					// show then position or it may postion off screen
					tt.show();
					showTooltip();
					positionTooltip(event);
				}

				return false;
			};

			// create the Bye bye tip
			function sbb_tooltip_off(event)
			{
				hideTooltip(this);
				return false;
			};

			// create the tip move with the cursor
			if (oSettings.followMouse)
			{
				$(this).on("mousemove", function(event){
					positionTooltip(event);
					return false;
				});
			}

			// clear the tip on a click
			$(this).on("click", function(event){
				hideTooltip(this);
				return true;
			});

		});
	};

	// A simple plugin for deleting an element from the DOM.
	$.fn.fadeOutAndRemove = function(speed){
		$(this).fadeOut(speed,function(){
			$(this).remove();
		});
	};

	// Range to percent.
	$.fn.rangeToPercent = function(number, min, max){
		return ((number - min) / (max - min));
	};

	// Percent to range.
	$.fn.percentToRange = function(percent, min, max){
		return((max - min) * percent + min);
	};

})(jQuery);

/**
 * AnimaDrag
 * Animated jQuery Drag and Drop Plugin
 * Version 0.5.1 beta
 * Author Abel Mohler
 * Released with the MIT License: https://opensource.org/licenses/mit-license.php
 */
(function($){
	$.fn.animaDrag = function(o, callback) {
		var defaults = {
			speed: 400,
			interval: 300,
			easing: null,
			cursor: 'move',
			boundary: document.body,
			grip: null,
			overlay: true,
			after: function(e) {},
			during: function(e) {},
			before: function(e) {},
			afterEachAnimation: function(e) {}
		}
		if(typeof callback == 'function') {
				defaults.after = callback;
		}
		o = $.extend(defaults, o || {});
		return this.each(function() {
			var id, startX, startY, draggableStartX, draggableStartY, dragging = false, Ev, draggable = this,
			grip = ($(this).find(o.grip).length > 0) ? $(this).find(o.grip) : $(this);
			if(o.boundary) {
				var limitTop = $(o.boundary).offset().top, limitLeft = $(o.boundary).offset().left,
				limitBottom = limitTop + $(o.boundary).innerHeight(), limitRight = limitLeft + $(o.boundary).innerWidth();
			}
			grip.mousedown(function(e) {
				o.before.call(draggable, e);

				var lastX, lastY;
				dragging = true;

				Ev = e;

				startX = lastX = e.pageX;
				startY = lastY = e.pageY;
				draggableStartX = $(draggable).offset().left;
				draggableStartY = $(draggable).offset().top;

				$(draggable).css({
					position: 'absolute',
					left: draggableStartX + 'px',
					top: draggableStartY + 'px',
					cursor: o.cursor,
					zIndex: '1010'
				}).addClass('anima-drag').appendTo(document.body);
				if(o.overlay && $('#anima-drag-overlay').length == 0) {
					$('<div id="anima-drag-overlay"></div>').css({
						position: 'absolute',
						top: '0',
						left: '0',
						zIndex: '1000',
						width: $(document.body).outerWidth() + 'px',
						height: $(document.body).outerHeight() + 'px'
					}).appendTo(document.body);
				}
				else if(o.overlay) {
					$('#anima-drag-overlay').show();
				}
				id = setInterval(function() {
					if(lastX != Ev.pageX || lastY != Ev.pageY) {
						var positionX = draggableStartX - (startX - Ev.pageX), positionY = draggableStartY - (startY - Ev.pageY);
						if(positionX < limitLeft && o.boundary) {
							positionX = limitLeft;
						}
						else if(positionX + $(draggable).innerWidth() > limitRight && o.boundary) {
							positionX = limitRight - $(draggable).outerWidth();
						}
						if(positionY < limitTop && o.boundary) {
							positionY = limitTop;
						}
						else if(positionY + $(draggable).innerHeight() > limitBottom && o.boundary) {
							positionY = limitBottom - $(draggable).outerHeight();
						}
						$(draggable).stop().animate({
							left: positionX + 'px',
							top: positionY + 'px'
						}, o.speed, o.easing, function(){o.afterEachAnimation.call(draggable, Ev)});
					}
					lastX = Ev.pageX;
					lastY = Ev.pageY;
				}, o.interval);
				($.browser.safari || e.preventDefault());
			});
			$(document).mousemove(function(e) {
				if(dragging) {
					Ev = e;
					o.during.call(draggable, e);
				}
			});
			$(document).mouseup(function(e) {
				if(dragging) {
					$(draggable).css({
						cursor: '',
						zIndex: '990'
					}).removeClass('anima-drag');
					$('#anima-drag-overlay').hide().appendTo(document.body);
					clearInterval(id);
					o.after.call(draggable, e);
					dragging = false;
				}
			});
		});
	}
})(jQuery);

/* Mobile Pop */
$(function() {
	$( '.mobile_act' ).click(function() {
		$( '#mobile_action' ).show();
		});
	$( '.hide_popup' ).click(function() {
		$( '#mobile_action' ).hide();
	});
	$( '.mobile_mod' ).click(function() {
		$( '#mobile_moderation' ).show();
	});
	$( '.hide_popup' ).click(function() {
		$( '#mobile_moderation' ).hide();
	});
	$( '.mobile_user_menu' ).click(function() {
		$( '#mobile_user_menu' ).show();
		});
	$( '.hide_popup' ).click(function() {
		$( '#mobile_user_menu' ).hide();
	});
});