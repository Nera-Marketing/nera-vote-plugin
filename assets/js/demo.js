(function () {
	'use strict';

	var cfg = window.NeraVoteDemo;
	if (!cfg) { return; }
	var i18n = cfg.i18n || {};

	var createBtn = document.getElementById('nv-demo-create');
	var removeBtn = document.getElementById('nv-demo-remove');
	var hideBtn = document.getElementById('nv-demo-hide');
	var countInput = document.getElementById('nv-demo-count');
	var bar = document.getElementById('nv-demo-bar');
	var label = document.getElementById('nv-demo-label');
	var logEl = document.getElementById('nv-demo-log');
	var rowsEl = document.getElementById('nv-demo-rows');
	var busy = false;

	function el(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) { n.className = cls; }
		if (text !== undefined) { n.textContent = text; }
		return n;
	}

	function log(line) {
		logEl.textContent += '[' + new Date().toLocaleTimeString() + '] ' + line + '\n';
		logEl.scrollTop = logEl.scrollHeight;
	}

	function setProgress(percent, text, isError) {
		bar.style.width = percent + '%';
		bar.classList.toggle('is-error', !!isError);
		label.textContent = text || '';
	}

	function render(status) {
		document.getElementById('nv-demo-votes').textContent = status.votes.length;
		document.getElementById('nv-demo-members').textContent = status.members;
		document.getElementById('nv-demo-ballots').textContent = status.ballots;
		document.getElementById('nv-demo-images').textContent = status.images;

		rowsEl.textContent = '';
		if (!status.votes.length) {
			var tr = el('tr');
			var td = el('td', '', i18n.none || 'No demo data yet.');
			td.colSpan = 4;
			tr.appendChild(td);
			rowsEl.appendChild(tr);
		}
		status.votes.forEach(function (v) {
			var row = el('tr');
			row.appendChild(el('td', '', v.title));
			var st = el('td');
			st.appendChild(el('span', 'nera-vote-pill nera-vote-pill--' + v.status, v.status.charAt(0).toUpperCase() + v.status.slice(1)));
			row.appendChild(st);
			var sc = el('td');
			sc.appendChild(el('code', '', v.shortcode));
			row.appendChild(sc);
			var act = el('td');
			var a = el('a', 'button button-small', i18n.edit || 'Edit');
			a.href = v.edit;
			act.appendChild(a);
			row.appendChild(act);
			rowsEl.appendChild(row);
		});

		var any = status.votes.length > 0 || status.members > 0 || status.images > 0;
		createBtn.disabled = busy || !cfg.allowed || any;
		removeBtn.disabled = busy || !any;
		hideBtn.disabled = busy || any;
	}

	function post(data) {
		var body = new URLSearchParams();
		body.set('action', 'nera_vote_demo_step');
		body.set('nonce', cfg.nonce);
		Object.keys(data).forEach(function (k) { body.set(k, data[k]); });
		return fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) {
			return r.json().catch(function () { throw new Error('The server did not return JSON (a PHP error?)'); });
		});
	}

	function run(op) {
		if (busy) { return; }
		busy = true;
		createBtn.disabled = true;
		removeBtn.disabled = true;
		hideBtn.disabled = true;
		countInput.disabled = true;
		setProgress(0, i18n.working || 'Working…', false);
		log('=== ' + (op === 'create' ? 'Create demo data' : 'Remove demo data') + ' ===');

		function next(params) {
			var data = { op: op, step: params.step, offset: params.offset || 0, members: countInput.value };
			return post(data).then(function (res) {
				if (!res.success) { throw new Error((res.data && res.data.message) || 'Request failed'); }
				var d = res.data;
				log(d.message);
				setProgress(d.percent, d.message, false);
				if (d.status) { render(d.status); }
				return d.next ? next(d.next) : null;
			});
		}

		next({ step: 'start', offset: 0 }).then(function () {
			setProgress(100, i18n.done || 'Done', false);
			log(i18n.done || 'Done');
		}).catch(function (err) {
			setProgress(100, (i18n.failed || 'Failed') + ': ' + err.message, true);
			log('FAILED: ' + err.message);
		}).then(function () {
			busy = false;
			countInput.disabled = false;
			refresh();
		});
	}

	function refresh() {
		var body = new URLSearchParams();
		body.set('action', 'nera_vote_demo_status');
		body.set('nonce', cfg.nonce);
		return fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (res) { if (res.success) { render(res.data); } });
	}

	createBtn.addEventListener('click', function () { run('create'); });
	removeBtn.addEventListener('click', function () {
		if (window.confirm(i18n.confirmRemove || 'Remove all demo data?')) { run('remove'); }
	});

	hideBtn.addEventListener('click', function () {
		if (busy || !window.confirm(i18n.confirmHide || 'Hide this tool?')) { return; }
		busy = true;
		hideBtn.disabled = true;
		setProgress(50, i18n.working || 'Working…', false);
		log('=== Hide tool ===');
		var body = new URLSearchParams();
		body.set('action', 'nera_vote_demo_hide');
		body.set('nonce', cfg.nonce);
		fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res.success) { throw new Error((res.data && res.data.message) || 'Request failed'); }
				setProgress(100, i18n.done || 'Done', false);
				log('Tool hidden. Redirecting…');
				window.location.href = res.data.redirect;
			})
			.catch(function (err) {
				busy = false;
				setProgress(100, (i18n.failed || 'Failed') + ': ' + err.message, true);
				log('FAILED: ' + err.message);
				refresh();
			});
	});

	render(cfg.status);
})();
