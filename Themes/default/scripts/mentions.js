var fails = [];

var atwhoConfig = {
	at: '@',
	data: [],
	displayTpl: '<li><span class="avatar"><img src="${avatar}" alt=""></span><span>${name}</span></li>',
	show_the_at: true,
	limit: 10,
	callbacks: {
		matcher: function(flag, subtext, should_start_with_space) {
			var match = '', started = false;
			var string = subtext.split('');
			for (var i = 0; i < string.length; i++)
			{
				if (string[i] == flag && (!should_start_with_space || i == 0 || /[\s\n]/gi.test(string[i - 1])))
				{
					started = true;
					match = '';
				}
				else if (started)
					match = match + string[i];
			}

			if (match.length > 0)
				return match;

			return null;
		},
		remoteFilter: function (query, callback) {
			if (typeof query == 'undefined' || query.length < 2 || query.length > 60)
				return;

			for (i in fails)
				if (query.substr(0, fails[i].length) == fails[i])
					return;

			$.ajax({
				url: sbb_scripturl + '?action=autocomplete;' + sbb_session_var + '=' + sbb_session_id,
				method: 'GET',
				data: {
					term: query,
					type: 'rawcharacter'
				},
				success: function (data) {
					if (data.results.length == 0)
						fails[fails.length] = query;

					var callbackArray = [];
					$.each(data.results, function (index, item) {
						callbackArray[callbackArray.length] = {
							name: item.char_name,
							avatar: item.avatar
						};
					});

					callback(callbackArray);
				}
			});
		}
	}
};
$(function()
{
	$('textarea[name=message]').atwho(atwhoConfig);
	$('.sceditor-container').find('textarea').atwho(atwhoConfig);
	var iframe = $('.sceditor-container').find('iframe')[0];
	if (typeof iframe != 'undefined')
		$(iframe.contentDocument.body).atwho(atwhoConfig);
});
