(function () {
	'use strict';

	var cfg = window.NeraVoteAdmin || {};
	var list = document.getElementById('nera-vote-options');
	var tpl = document.getElementById('nera-vote-option-template');
	var addBtn = document.getElementById('nera-vote-add-option');
	var counter = list ? list.querySelectorAll('.nera-vote-option').length : 0;

	function addRow() {
		if (!list || !tpl) { return; }
		var wrap = document.createElement('div');
		wrap.innerHTML = tpl.innerHTML.replace(/__i__/g, String(counter++)).trim();
		var row = wrap.firstElementChild;
		list.appendChild(row);
		var name = row.querySelector('input[type="text"]');
		if (name) { name.focus(); }
	}

	function pickImage(row) {
		if (!window.wp || !wp.media) { return; }
		var frame = wp.media({
			title: cfg.chooseImage || 'Choose an image',
			button: { text: cfg.useImage || 'Use this image' },
			library: { type: 'image' },
			multiple: false
		});
		frame.on('select', function () {
			var att = frame.state().get('selection').first().toJSON();
			var url = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
			row.querySelector('.nera-vote-option__image-id').value = att.id;
			var img = row.querySelector('.nera-vote-option__preview');
			img.src = url;
			img.hidden = false;
			row.querySelector('.nera-vote-option__unpick').hidden = false;
		});
		frame.open();
	}

	function unpickImage(row) {
		row.querySelector('.nera-vote-option__image-id').value = '';
		var img = row.querySelector('.nera-vote-option__preview');
		img.removeAttribute('src');
		img.hidden = true;
		row.querySelector('.nera-vote-option__unpick').hidden = true;
	}

	if (addBtn) { addBtn.addEventListener('click', addRow); }

	if (list) {
		list.addEventListener('click', function (e) {
			var t = e.target;
			if (!(t instanceof Element)) { return; }
			var row = t.closest('.nera-vote-option');
			if (!row) { return; }

			if (t.closest('.nera-vote-option__pick')) { pickImage(row); }
			else if (t.closest('.nera-vote-option__unpick')) { unpickImage(row); }
			else if (t.closest('.nera-vote-option__remove')) { row.remove(); }
			else if (t.closest('.nera-vote-option__up') && row.previousElementSibling) {
				list.insertBefore(row, row.previousElementSibling);
			} else if (t.closest('.nera-vote-option__down') && row.nextElementSibling) {
				list.insertBefore(row.nextElementSibling, row);
			}
		});

		list.addEventListener('change', function (e) {
			var t = e.target;
			if (t instanceof HTMLInputElement && t.type === 'checkbox') {
				var row = t.closest('.nera-vote-option');
				if (row) { row.classList.toggle('is-hidden', t.checked); }
			}
		});
	}

	document.addEventListener('click', function (e) {
		var btn = e.target instanceof Element ? e.target.closest('.nera-vote-copy') : null;
		if (!btn) { return; }
		var text = btn.getAttribute('data-copy') || '';
		var done = function () {
			var old = btn.textContent;
			btn.textContent = cfg.copied || 'Copied';
			setTimeout(function () { btn.textContent = old; }, 1500);
		};
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done);
		} else {
			var input = document.querySelector('.nera-vote-shortcode');
			if (input) { input.select(); document.execCommand('copy'); done(); }
		}
	});
})();
