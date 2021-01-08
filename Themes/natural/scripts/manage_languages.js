$('#add-multilang').on("click", function(e) {
	e.preventDefault();
	var txt = $('#multilang').data('remove');
	var html = '<dt>'; 
	html += '<button class="button" role="button">' + txt + '</button>';
	html += '<input name="entry_key[]" type="text" value="">';
	html += '</dt>';
	html += '<dd>';
	html += '<textarea name="entry_value[]"></textarea>';
	html += '</dd>';
	$('#multilang').append(html);
	add_entry();
});
function add_entry() {
	$('#multilang .button').one("click", function() {
		$(this).closest('dt').next('dd').remove();
		$(this).closest('dt').remove();
	});
}