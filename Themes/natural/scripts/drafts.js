// The draft save object
function sbb_DraftAutoSave(oOptions)
{
	this.opt = oOptions;
	this.bInDraftMode = false;
	this.sCurDraftId = '';
	this.oCurDraftDiv = null;
	this.interval_id = null;
	this.oDraftHandle = window;
	this.sLastSaved = '';
	this.bPM = this.opt.bPM ? true : false;
	this.sCheckDraft = '';
	var that = this;

	// slight delay on autosave init to allow sceditor to create the iframe
	addLoadEvent(
		function() { setTimeout(function () { that.init() }, 3000) }
	);
}

// Start our self calling routine
sbb_DraftAutoSave.prototype.init = function ()
{
	if (this.opt.iFreq > 0)
	{
		// find the editors wysiwyg iframe and gets its window
		var oIframe = document.getElementsByTagName('iframe')[0];
		var oIframeWindow = oIframe.contentWindow || oIframe.contentDocument;
		// start the autosave timer
		var js = 'draftSave';
		if (this.bPM)
		{
			js = 'draftPMSave';
		}
		else if (this.opt.sType == 'charsheet')
		{
			js = 'draftCharsheetSave';
		}
		this.interval_id = window.setInterval(this.opt.sSelf + '.' + js + '();', this.opt.iFreq);

		// Set up window focus and blur events
		var instanceRef = this;
		this.oDraftHandle.onblur = function (oEvent) {return instanceRef.draftBlur(oEvent, true);};
		this.oDraftHandle.onfocus = function (oEvent) {return instanceRef.draftFocus(oEvent, true);};

		// If we found the iframe window, set body focus/blur events for it
		if (oIframeWindow.document)
		{
			var oIframeDoc = oIframeWindow.document;
			// @todo oDraftAutoSave should use the this.opt.sSelf name not hardcoded
			oIframeDoc.body.onblur = function (oEvent) {return parent.oDraftAutoSave.draftBlur(oEvent, false);};
			oIframeDoc.body.onfocus = function (oEvent) {return parent.oDraftAutoSave.draftFocus(oEvent, false);};
		};
	}
}

// Moved away from the page, where did you go? ... till you return we pause autosaving
sbb_DraftAutoSave.prototype.draftBlur = function(oEvent, source)
{
	if ($('#' + this.opt.sSceditorID).data("sceditor").inSourceMode() == source)
	{
		// save what we have and turn of the autosave
		if (this.bPM)
			this.draftPMSave();
		else if (this.opt.sType == 'charsheet')
			this.draftCharsheetSave();
		else
			this.draftSave();

		if (this.interval_id != "")
			window.clearInterval(this.interval_id);
		this.interval_id = "";
	}
	return;
}

// Since you're back we resume the autosave timer
sbb_DraftAutoSave.prototype.draftFocus = function(oEvent, source)
{
	if ($('#' + this.opt.sSceditorID).data("sceditor").inSourceMode() == source)
	{
		var js = 'draftSave';
		if (this.bPM)
		{
			js = 'draftPMSave';
		}
		else if (this.opt.sType == 'charsheet')
		{
			js = 'draftCharsheetSave';
		}

		if (this.interval_id == "")
			this.interval_id = window.setInterval(this.opt.sSelf + '.' + js + '();', this.opt.iFreq);
	}
	return;
}

// Make the call to save this draft in the background
sbb_DraftAutoSave.prototype.draftSave = function ()
{
	var sPostdata = $('#' + this.opt.sSceditorID).data("sceditor").getText(true);

	// nothing to save or already posting or nothing changed?
	if (isEmptyText(sPostdata) || sbb_formSubmitted || this.sCheckDraft == sPostdata)
		return false;

	// Still saving the last one or other?
	if (this.bInDraftMode)
		this.draftCancel();

	// Flag that we are saving a draft
	document.getElementById('throbber').style.display = '';
	this.bInDraftMode = true;

	// Get the form elements that we want to save
	var aSections = [
		'topic=' + parseInt(document.forms.postmodify.elements['topic'].value),
		'id_draft=' + (('id_draft' in document.forms.postmodify.elements) ? parseInt(document.forms.postmodify.elements['id_draft'].value) : 0),
		'subject=' + escape(document.forms.postmodify['subject'].value.php_to8bit()).replace(/\+/g, "%2B"),
		'message=' + escape(sPostdata.php_to8bit()).replace(/\+/g, "%2B"),
		'save_draft=true',
		sbb_session_var + '=' + sbb_session_id,
	];

	// Get the locked an/or sticky values if they have been selected or set that is
	if (this.opt.sType == 'post')
	{
		if (document.getElementById('check_lock') && document.getElementById('check_lock').checked)
			aSections[aSections.length] = 'lock=1';
		if (document.getElementById('check_sticky') && document.getElementById('check_sticky').checked)
			aSections[aSections.length] = 'sticky=1';
	}

	// keep track of source or wysiwyg
	aSections[aSections.length] = 'message_mode=' + $('#' + this.opt.sSceditorID).data("sceditor").inSourceMode();

	// Send in document for saving and hope for the best
	sendXMLDocument.call(this, sbb_prepareScriptUrl(sbb_scripturl) + "action=post2;board=" + this.opt.iBoard + ";xml", aSections.join("&"), this.onDraftDone);

	// Save the latest for compare
	this.sCheckDraft = sPostdata;
}

sbb_DraftAutoSave.prototype.draftCharsheetSave = function ()
{
	var sPostdata = $('#' + this.opt.sSceditorID).data("sceditor").getText(true);

	// nothing to save or already posting or nothing changed?
	if (isEmptyText(sPostdata) || sbb_formSubmitted || this.sCheckDraft == sPostdata)
		return false;

	// Still saving the last one or other?
	if (this.bInDraftMode)
		this.draftCancel();

	if (!this.opt.iCharacter)
	{
		var aSections = [
			'char_name=' + escape(document.forms.creator['char_name'].value.php_to8bit()).replace(/\+/g, "%2B"),
			'id_draft=' + (('id_draft' in document.forms.creator.elements) ? parseInt(document.forms.creator.elements['id_draft'].value) : 0),
			'message=' + escape(sPostdata.php_to8bit()).replace(/\+/g, "%2B"),
			'save_draft=true',
			sbb_session_var + '=' + sbb_session_id,
		];

		var endpoint = "action=profile;u=" + this.opt.iMember + ";area=character_create;xml";
	}
	else
	{
		var aSections = [
			'id_draft=' + (('id_draft' in document.forms.postmodify.elements) ? parseInt(document.forms.postmodify.elements['id_draft'].value) : 0),
			'message=' + escape(sPostdata.php_to8bit()).replace(/\+/g, "%2B"),
			'save_draft=true',
			sbb_session_var + '=' + sbb_session_id,
		];

		var endpoint = "action=profile;u=" + this.opt.iMember + ";area=character_sheet;char=" + this.opt.iCharacter + ";edit;xml";
	}

	// keep track of source or wysiwyg
	aSections[aSections.length] = 'message_mode=' + $('#' + this.opt.sSceditorID).data("sceditor").inSourceMode();

	// Send in document for saving and hope for the best
	sendXMLDocument.call(this, sbb_prepareScriptUrl(sbb_scripturl) + endpoint, aSections.join("&"), this.onDraftDone);

	// Save the latest for compare
	this.sCheckDraft = sPostdata;
}

// Make the call to save this PM draft in the background
sbb_DraftAutoSave.prototype.draftPMSave = function ()
{
	var sPostdata = $('#' + this.opt.sSceditorID).data("sceditor").getText();

	// nothing to save or already posting or nothing changed?
	if (isEmptyText(sPostdata) || sbb_formSubmitted || this.sCheckDraft == sPostdata)
		return false;

	// Still saving the last one or some other?
	if (this.bInDraftMode)
		this.draftCancel();

	// Flag that we are saving
	document.getElementById('throbber').style.display = '';
	this.bInDraftMode = true;

	// Get the to values
	var aTo = this.draftGetRecipient('recipient_to[]');

	// Get the rest of the form elements that we want to save, and load them up
	var aSections = [
		'replied_to=' + parseInt(document.forms.postmodify.elements['replied_to'].value),
		'id_pm_draft=' + (('id_pm_draft' in document.forms.postmodify.elements) ? parseInt(document.forms.postmodify.elements['id_pm_draft'].value) : 0),
		'subject=' + escape(document.forms.postmodify['subject'].value.php_to8bit()).replace(/\+/g, "%2B"),
		'message=' + escape(sPostdata.php_to8bit()).replace(/\+/g, "%2B"),
		'save_draft=true',
		sbb_session_var + '=' + sbb_session_id,
	];
	if (aTo.length > 0)
	{
		for (var i = 0, n = aTo.length; i < n; i++)
		{
			aSections[aSections.length] = 'recipient_to[]=' + aTo[i];
		}
	}

	// account for wysiwyg
	if (this.opt.sType == 'post')
		aSections[aSections.length] = 'message_mode=' + parseInt(document.forms.postmodify.elements['message_mode'].value);

	// Send in (post) the document for saving
	sendXMLDocument.call(this, sbb_prepareScriptUrl(sbb_scripturl) + "action=pm;sa=send;xml", aSections.join("&"), this.onDraftDone);

	// Save the latest for compare
	this.sCheckDraft = sPostdata;
}

// Callback function of the XMLhttp request for saving the draft message
sbb_DraftAutoSave.prototype.onDraftDone = function (XMLDoc)
{
	// If it is not valid then clean up
	if (!XMLDoc || !XMLDoc.getElementsByTagName('draft'))
		return this.draftCancel();

	// Grab the returned draft id and saved time from the response
	this.sCurDraftId = XMLDoc.getElementsByTagName('draft')[0].getAttribute('id');
	this.sLastSaved = XMLDoc.getElementsByTagName('draft')[0].childNodes[0].nodeValue;

	// Update the form to show we finished, if the id is not set, then set it
	document.getElementById(this.opt.sLastID).value = this.sCurDraftId;
	oCurDraftDiv = document.getElementById(this.opt.sLastNote);
	oCurDraftDiv.innerHTML = this.sLastSaved;

	// hide the saved draft infobox in the event they pressed the save draft button at some point
	if (this.opt.sType == 'post')
		document.getElementById('draft_section').style.display = 'none';

	// thank you sir, may I have another
	this.bInDraftMode = false;
	document.getElementById('throbber').style.display = 'none';
}

// function to retrieve the to values from the pseudo arrays
sbb_DraftAutoSave.prototype.draftGetRecipient = function (sField)
{
	var oRecipient = document.forms.postmodify.elements[sField];
	var aRecipient = []

	if (typeof(oRecipient) != 'undefined')
	{
		var opts = oRecipient.options;
		for (i = 0, n = opts.length; i < n; i++)
		{
			if (opts[i].selected)
			{
				aRecipient.push(parseInt(opts[i].value));
			}
		}
	}
	return aRecipient;
}

// If another auto save came in with one still pending
sbb_DraftAutoSave.prototype.draftCancel = function ()
{
	// can we do anything at all ... do we want to (e.g. sequence our async events?)
	// @todo if not remove this function
	this.bInDraftMode = false;
	document.getElementById('throbber').style.display = 'none';
}
