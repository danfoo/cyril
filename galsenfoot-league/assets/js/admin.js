/* GalsenFoot League — match sheet: add / remove event rows. */
(function () {
	'use strict';
	document.addEventListener('click', function (e) {
		var add = e.target.closest('.gsf-add-row');
		if (add) {
			var body = document.querySelector('.gsf-events tbody');
			var tpl = document.getElementById('gsf-event-template');
			if (!body || !tpl) { return; }
			var holder = document.createElement('tbody');
			holder.innerHTML = tpl.innerHTML.replace(/__i__/g, 'n' + Date.now());
			var row = holder.firstElementChild;
			body.appendChild(row);
			var first = row.querySelector('input');
			if (first) { first.focus(); }
			return;
		}
		var remove = e.target.closest('.gsf-remove-row');
		if (remove) {
			var tr = remove.closest('tr');
			if (tr) { tr.parentNode.removeChild(tr); }
		}
	});
	// Pick the team automatically from the selected player.
	document.addEventListener('change', function (e) {
		var select = e.target;
		if (!select.name || !/\[player\]$/.test(select.name)) { return; }
		var option = select.options[select.selectedIndex];
		var group = option && option.parentNode;
		if (group && group.getAttribute && group.getAttribute('data-side')) {
			var side = select.closest('tr').querySelector('select[name$="[side]"]');
			var type = select.closest('tr').querySelector('select[name$="[type]"]');
			if (side && (!type || type.value !== 'own_goal')) { side.value = group.getAttribute('data-side'); }
		}
	});
})();
