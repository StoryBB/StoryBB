$('#add-multilang').on("click", function(e) {
	e.preventDefault();
	var txt = $('#multilang').data('remove');
	var html = '<dt>' + 
			   '<button class="button" role="button">' + txt + '</button>' +
			   '<input name="entry_key[]" type="text" value="">' +
			   '</dt>' +
			   '<dd>' +
			   '<textarea name="entry_value[]"></textarea>' +
			   '</dd>';
	$('#multilang').append(html);
	add_entry();
});
function add_entry() {
	$('#multilang .button').one("click", function() {
		$(this).closest('dt').next('dd').remove();
		$(this).closest('dt').remove();
	});
}