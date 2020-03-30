function toggleBBCDisabled(section, disable)
{
	elems = document.getElementById(section).getElementsByTagName('*');
	for (var i = 0; i < elems.length; i++)
	{
		if (typeof(elems[i].name) == "undefined" || (elems[i].name.substr((section.length + 1), (elems[i].name.length - 2 - (section.length + 1))) != "enabledTags") || (elems[i].name.indexOf(section) != 0))
			continue;

		elems[i].disabled = disable;
	}
	document.getElementById("bbc_" + section + "_select_all").disabled = disable;
}

function updateInputBoxes()
{
	curType = document.getElementById("field_type").value;
	privStatus = document.getElementById("private").value;
	document.getElementById("max_length_dt").style.display = curType == "text" || curType == "textarea" ? "" : "none";
	document.getElementById("max_length_dd").style.display = curType == "text" || curType == "textarea" ? "" : "none";
	document.getElementById("dimension_dt").style.display = curType == "textarea" ? "" : "none";
	document.getElementById("dimension_dd").style.display = curType == "textarea" ? "" : "none";
	document.getElementById("bbc_dt").style.display = curType == "text" || curType == "textarea" ? "" : "none";
	document.getElementById("bbc_dd").style.display = curType == "text" || curType == "textarea" ? "" : "none";
	document.getElementById("options_dt").style.display = curType == "select" || curType == "radio" ? "" : "none";
	document.getElementById("options_dd").style.display = curType == "select" || curType == "radio" ? "" : "none";
	document.getElementById("default_dt").style.display = curType == "check" ? "" : "none";
	document.getElementById("default_dd").style.display = curType == "check" ? "" : "none";
	document.getElementById("mask_dt").style.display = curType == "text" ? "" : "none";
	document.getElementById("mask").style.display = curType == "text" ? "" : "none";
	document.getElementById("can_search_dt").style.display = curType == "text" || curType == "textarea" || curType == "select" ? "" : "none";
	document.getElementById("can_search_dd").style.display = curType == "text" || curType == "textarea" || curType == "select" ? "" : "none";
	document.getElementById("regex_div").style.display = curType == "text" && document.getElementById("mask").value == "regex" ? "" : "none";
	document.getElementById("display").disabled = false;
	// Cannot show this on the topic
	if (curType == "textarea" || privStatus >= 2)
	{
		document.getElementById("display").checked = false;
		document.getElementById("display").disabled = true;
	}
}

function addOption()
{
	setOuterHTML(document.getElementById("addopt"), '<br><input type="radio" name="default_select" value="' + startOptID + '" id="' + startOptID + '"><input type="text" name="select_option[' + startOptID + ']" value=""><span id="addopt"></span>');
	startOptID++;
}


//Create a named element dynamically - thanks to: https://www.thunderguy.com/semicolon/2005/05/23/setting-the-name-attribute-in-internet-explorer/
function createNamedElement(type, name, customFields)
{
	var element = null;

	if (!customFields)
		customFields = "";

	// Try the IE way; this fails on standards-compliant browsers
	try
	{
		element = document.createElement("<" + type + ' name="' + name + '" ' + customFields + ">");
	}
	catch (e)
	{
	}
	if (!element || element.nodeName != type.toUpperCase())
	{
		// Non-IE browser; use canonical method to create named element
		element = document.createElement(type);
		element.name = name;
	}

	return element;
}

function changeVariant(sVariant)
{
	document.getElementById('variant_preview').src = oThumbnails[sVariant];
}

// The idea here is simple: don't refresh the preview on every keypress, but do refresh after they type.
function setPreviewTimeout()
{
	if (previewTimeout)
	{
		window.clearTimeout(previewTimeout);
		previewTimeout = null;
	}

	previewTimeout = window.setTimeout("refreshPreview(true); previewTimeout = null;", 500);
}

function toggleDuration(toChange)
{
	if (toChange == 'fixed')
	{
		document.getElementById("fixed_area").style.display = "inline";
		document.getElementById("flexible_area").style.display = "none";
	}
	else
	{
		document.getElementById("fixed_area").style.display = "none";
		document.getElementById("flexible_area").style.display = "inline";
	}
}

function calculateNewValues()
{
	var total = 0;
	for (var i = 1; i <= 6; i++)
	{
		total += parseInt(document.getElementById('weight' + i + '_val').value);
	}
	setInnerHTML(document.getElementById('weighttotal'), total);
	for (var i = 1; i <= 6; i++)
	{
		setInnerHTML(document.getElementById('weight' + i), (Math.round(1000 * parseInt(document.getElementById('weight' + i + '_val').value) / total) / 10) + '%');
	}
}

function swap_database_changes()
{
	db_vis = !db_vis;
	database_changes_area.style.display = db_vis ? "" : "none";
	return false;
}

function testFTP()
{
	ajax_indicator(true);

	// What we need to post.
	var oPostData = {
		0: "ftp_server",
		1: "ftp_port",
		2: "ftp_username",
		3: "ftp_password",
		4: "ftp_path"
	}

	var sPostData = "";
	for (i = 0; i < 5; i++)
		sPostData = sPostData + (sPostData.length == 0 ? "" : "&") + oPostData[i] + "=" + escape(document.getElementById(oPostData[i]).value);

	// Post the data out.
	sendXMLDocument(sbb_prepareScriptUrl(sbb_scripturl) + 'action=admin;area=packages;sa=ftptest;xml;' + sbb_session_var + '=' + sbb_session_id, sPostData, testFTPResults);
}

function expandFolder(folderIdent, folderReal)
{
	// See if it already exists.
	var possibleTags = document.getElementsByTagName("tr");
	var foundOne = false;

	for (var i = 0; i < possibleTags.length; i++)
	{
		if (possibleTags[i].id.indexOf("content_" + folderIdent + ":-:") == 0)
		{
			possibleTags[i].style.display = possibleTags[i].style.display == "none" ? "" : "none";
			foundOne = true;
		}
	}

	// Got something then we're done.
	if (foundOne)
	{
		return false;
	}
	// Otherwise we need to get the wicked thing.
	else if (window.XMLHttpRequest)
	{
		ajax_indicator(true);
		getXMLDocument(sbb_prepareScriptUrl(sbb_scripturl) + 'action=admin;area=packages;onlyfind=' + escape(folderReal) + ';sa=perms;xml;' + sbb_session_var + '=' + sbb_session_id, onNewFolderReceived);
	}
	// Otherwise reload.
	else
		return true;

	return false;
}

function dynamicExpandFolder()
{
	expandFolder(this.ident, this.path);

	return false;
}

function repeatString(sString, iTime)
{
	if (iTime < 1)
		return '';
	else
		return sString + repeatString(sString, iTime - 1);
}

function select_in_category(cat_id, elem, brd_list)
{
	for (var brd in brd_list)
		document.getElementById(elem.value + '_brd' + brd_list[brd]).checked = true;

	elem.selectedIndex = 0;
}

/*
* Attachments Settings
*/
function toggleSubDir ()
{
	var auto_attach = document.getElementById('automanage_attachments');
	var use_sub_dir = document.getElementById('use_subdirectories_for_attachments');
	var dir_elem = document.getElementById('basedirectory_for_attachments');

	use_sub_dir.disabled = !Boolean(auto_attach.selectedIndex);
	if (use_sub_dir.disabled)
	{
		use_sub_dir.style.display = "none";
		document.getElementById('setting_use_subdirectories_for_attachments').parentNode.style.display = "none";
		dir_elem.style.display = "none";
		document.getElementById('setting_basedirectory_for_attachments').parentNode.style.display = "none";
	}
	else
	{
		use_sub_dir.style.display = "";
		document.getElementById('setting_use_subdirectories_for_attachments').parentNode.style.display = "";
		dir_elem.style.display = "";
		document.getElementById('setting_basedirectory_for_attachments').parentNode.style.display = "";
	}
		toggleBaseDir();
}
function toggleBaseDir ()
{
	var auto_attach = document.getElementById('automanage_attachments');
	var sub_dir = document.getElementById('use_subdirectories_for_attachments');
	var dir_elem = document.getElementById('basedirectory_for_attachments');

	if (auto_attach.selectedIndex == 0)
	{
		dir_elem.disabled = 1;
	}
	else
		dir_elem.disabled = !sub_dir.checked;
}