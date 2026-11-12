/* Écurie Prestige — sélecteur de galerie dans la fiche du cheval. */
jQuery(function ($) {
	var frame;
	$(document).on('click', '.ep-gallery-pick', function (e) {
		e.preventDefault();
		var $field = $(this).closest('.ep-admin__field');
		var $input = $field.find('.ep-gallery-input');
		var $preview = $field.find('.ep-gallery-preview');

		frame = wp.media({
			title: epAdmin.title,
			button: { text: epAdmin.button },
			library: { type: 'image' },
			multiple: 'add'
		});
		frame.on('open', function () {
			var selection = frame.state().get('selection');
			($input.val() || '').split(',').filter(Boolean).forEach(function (id) {
				var attachment = wp.media.attachment(id);
				attachment.fetch();
				selection.add(attachment);
			});
		});
		frame.on('select', function () {
			var ids = [];
			$preview.empty();
			frame.state().get('selection').each(function (attachment) {
				var data = attachment.toJSON();
				var url = data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail.url : data.url;
				ids.push(data.id);
				$preview.append($('<img>', { src: url, alt: '' }));
			});
			$input.val(ids.join(','));
		});
		frame.open();
	});
	$(document).on('click', '.ep-gallery-clear', function (e) {
		e.preventDefault();
		var $field = $(this).closest('.ep-admin__field');
		$field.find('.ep-gallery-input').val('');
		$field.find('.ep-gallery-preview').empty();
	});
});
