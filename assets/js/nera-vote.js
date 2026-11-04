(function () {
	'use strict';

	var cfg = window.NeraVote || {};
	var i18n = cfg.i18n || {};

	/* ------------------------------------------------------------ helpers */

	function ready(fn) {
		if (document.readyState !== 'loading') { fn(); }
		else { document.addEventListener('DOMContentLoaded', fn); }
	}

	function el(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) { n.className = cls; }
		if (text !== undefined) { n.textContent = text; }
		return n;
	}

	function api(path, method, body) {
		var opts = {
			method: method || 'GET',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce || '' }
		};
		if (body) {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify(body);
		}
		return fetch(cfg.rest + path, opts).then(function (r) {
			return r.json().then(function (json) {
				if (!r.ok) { throw new Error((json && json.message) || 'Request failed'); }
				return json;
			});
		});
	}

	/* The cookie only remembers ticks for display; the server is the count. */
	function cookieName(voteId) { return 'nera_vote_' + voteId; }

	function readCookie(voteId) {
		var m = document.cookie.match(new RegExp('(?:^|; )' + cookieName(voteId) + '=([^;]*)'));
		return m && m[1] ? decodeURIComponent(m[1]).split(',').filter(Boolean) : [];
	}

	function writeCookie(voteId, ids) {
		document.cookie = cookieName(voteId) + '=' + encodeURIComponent(ids.join(',')) +
			'; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
	}

	/* ------------------------------------------- feedback: toast + chime */

	var audioCtx = null;

	// Same two notes as the theme's add-to-cart sound, for sites without it.
	function fallbackChime() {
		try {
			audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
			var ctx = audioCtx;
			var play = function () {
				[[523, 0], [659, 0.15]].forEach(function (n) {
					var osc = ctx.createOscillator();
					var gain = ctx.createGain();
					osc.connect(gain);
					gain.connect(ctx.destination);
					osc.frequency.value = n[0];
					osc.type = 'sine';
					gain.gain.setValueAtTime(0.3, ctx.currentTime + n[1]);
					gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + n[1] + 0.4);
					osc.start(ctx.currentTime + n[1]);
					osc.stop(ctx.currentTime + n[1] + 0.4);
				});
			};
			if (ctx.state === 'suspended') { ctx.resume().then(play); } else { play(); }
		} catch (e) { /* no Web Audio: stay silent */ }
	}

	function fallbackToast(message, isError) {
		var host = document.getElementById('nera-vote-toasts');
		if (!host) {
			host = el('div', 'nera-vote-toasts');
			host.id = 'nera-vote-toasts';
			host.setAttribute('aria-live', 'polite');
			document.body.appendChild(host);
		}
		var t = el('div', 'nera-vote-toast' + (isError ? ' nera-vote-toast--error' : ''), message);
		host.appendChild(t);
		requestAnimationFrame(function () { t.classList.add('is-in'); });
		setTimeout(function () {
			t.classList.remove('is-in');
			setTimeout(function () { t.remove(); }, 250);
		}, isError ? 5000 : 2500);
	}

	function themeToast() {
		return cfg.themeFeedback && window.Alpine && window.Alpine.store && window.Alpine.store('toast') || null;
	}

	function notifySaved(withSound) {
		var message = i18n.saved || 'Saved';
		var toast = themeToast();
		if (toast) {
			toast.success(message);
			if (withSound) {
				// The theme's cart-sound listens for this; the source tag keeps its other listeners out.
				document.dispatchEvent(new CustomEvent('nera:cart:updated', { detail: { source: 'nera-vote' } }));
			}
		} else {
			fallbackToast(message, false);
			if (withSound) { fallbackChime(); }
		}
	}

	function notifyError(message) {
		var toast = themeToast();
		if (toast) { toast.error(message); } else { fallbackToast(message, true); }
	}

	/* ------------------------------------------------------------- popup */

	function buildDialog(root) {
		var dialog = document.createElement('dialog');
		dialog.className = 'nera-vote__dialog';
		dialog.addEventListener('click', function (e) {
			if (e.target === dialog) { closeDialog(dialog); }
		});
		dialog.addEventListener('close', function () {
			var back = dialog._opener;
			if (back && document.contains(back)) { back.focus(); }
		});
		root.appendChild(dialog);
		return dialog;
	}

	function closeDialog(dialog) {
		if (dialog.close) { dialog.close(); } else { dialog.removeAttribute('open'); }
	}

	function openOption(root, data, optionId, opener) {
		var info = data[optionId];
		if (!info) { return; }
		var dialog = root._dialog || (root._dialog = buildDialog(root));
		dialog._opener = opener;
		dialog.textContent = '';

		var close = el('button', 'nera-vote__dialog-close', '×');
		close.type = 'button';
		close.setAttribute('aria-label', i18n.close || 'Close');
		close.addEventListener('click', function () { closeDialog(dialog); });
		dialog.appendChild(close);

		if (info.image) {
			var img = el('img', 'nera-vote__dialog-image');
			img.src = info.image;
			img.alt = info.name;
			dialog.appendChild(img);
		}
		var body = el('div', 'nera-vote__dialog-body');
		var title = el('h4', 'nera-vote__dialog-title', info.name);
		title.id = root.id + '-dialog-title';
		dialog.setAttribute('aria-labelledby', title.id);
		body.appendChild(title);
		if (info.description) { body.appendChild(el('p', 'nera-vote__dialog-text', info.description)); }
		if (info.link) {
			var a = el('a', 'nera-vote__dialog-link', i18n.learn || 'Learn more');
			a.href = info.link;
			a.target = '_blank';
			a.rel = 'noopener';
			body.appendChild(a);
		}
		dialog.appendChild(body);

		if (dialog.showModal) { dialog.showModal(); } else { dialog.setAttribute('open', ''); }
		close.focus();
	}

	/* ------------------------------------------------------------ state */

	function setResult(li, r) {
		var holder = li.querySelector('.nera-vote__result');
		if (!holder) { return; }
		holder.textContent = '';
		if (!r) { holder.hidden = true; return; }
		var bar = el('span', 'nera-vote-bar');
		bar.setAttribute('role', 'presentation');
		var fill = el('span', 'nera-vote-bar__fill nera-vote-bar__fill--' + r.band);
		fill.style.width = r.pctLeader + '%';
		bar.appendChild(fill);
		var fig = el('span', 'nera-vote__figures');
		fig.appendChild(el('strong', '', String(r.count)));
		fig.appendChild(document.createTextNode(' ' + (i18n.ballots || 'Ballots') + ' · ' + r.pctVoters + '% ' + (i18n.ofVoters || 'of voters')));
		holder.appendChild(bar);
		holder.appendChild(fig);
		holder.hidden = false;
	}

	function applyState(root, state) {
		var ids = state.checked || [];
		root.querySelectorAll('.nera-vote__input').forEach(function (input) {
			input.checked = ids.indexOf(input.value) !== -1;
		});
		if (state.results) {
			root.querySelectorAll('.nera-vote__item').forEach(function (li) {
				setResult(li, state.results[li.getAttribute('data-option-id')]);
			});
		}
		writeCookie(root.getAttribute('data-vote-id'), ids);
		if (state.status && state.status !== root.getAttribute('data-status')) {
			root.setAttribute('data-status', state.status);
			if (state.status !== 'open') { location.reload(); }
		}
	}

	/* ------------------------------------------------------------- init */

	function init(root) {
		var voteId = root.getAttribute('data-vote-id');
		var status = root.getAttribute('data-status');
		var loggedIn = root.getAttribute('data-logged-in') === '1';
		var sound = root.getAttribute('data-sound') === '1';
		var data = {};
		var script = root.querySelector('.nera-vote__data');
		if (script) {
			try { data = JSON.parse(script.textContent); } catch (e) { data = {}; }
		}

		root.addEventListener('click', function (e) {
			var btn = e.target instanceof Element ? e.target.closest('.nera-vote__name') : null;
			if (btn) { openOption(root, data, btn.getAttribute('data-option-id'), btn); }
		});

		if (status !== 'open' || !loggedIn) { return; }

		// Show remembered ticks straight away; the server state then replaces them.
		var remembered = readCookie(voteId);
		root.querySelectorAll('.nera-vote__input').forEach(function (input) {
			if (remembered.indexOf(input.value) !== -1) { input.checked = true; }
		});

		api('votes/' + voteId).then(function (state) { applyState(root, state); }).catch(function () { /* keep server-rendered state */ });

		root.addEventListener('change', function (e) {
			var input = e.target;
			if (!(input instanceof HTMLInputElement) || !input.classList.contains('nera-vote__input')) { return; }
			var wanted = input.checked;
			input.disabled = true;
			api('votes/' + voteId + '/ballot', 'POST', { option_id: input.value, checked: wanted })
				.then(function (state) {
					applyState(root, state);
					notifySaved(sound);
				})
				.catch(function (err) {
					input.checked = !wanted;
					notifyError(i18n.error || err.message);
				})
				.then(function () { input.disabled = false; });
		});
	}

	ready(function () {
		document.querySelectorAll('.nera-vote').forEach(init);
	});
})();
