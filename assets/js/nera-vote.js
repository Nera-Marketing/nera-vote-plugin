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
			dialog.classList.remove('is-open');
			var back = dialog._opener;
			if (back && document.contains(back)) { back.focus(); }
		});
		wireCancel(dialog);
		root.appendChild(dialog);
		return dialog;
	}

	/* Dialogs fade and slide in and out. The `is-open` class drives the CSS transition;
	   the real close() waits for it, so Escape, the backdrop and the buttons all animate. */
	function reducedMotion() {
		return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function openDialog(dialog) {
		clearTimeout(dialog._closeTimer);
		dialog._closing = false;
		if (dialog.showModal) { dialog.showModal(); } else { dialog.setAttribute('open', ''); }
		// Two frames, so the browser paints the closed state before the transition starts.
		requestAnimationFrame(function () {
			requestAnimationFrame(function () { dialog.classList.add('is-open'); });
		});
	}

	function closeDialog(dialog) {
		if (!dialog.open || dialog._closing) { return; }
		var finish = function () {
			dialog._closing = false;
			clearTimeout(dialog._closeTimer);
			if (dialog.close) { dialog.close(); } else { dialog.removeAttribute('open'); }
		};
		dialog.classList.remove('is-open');
		if (reducedMotion()) { finish(); return; }
		dialog._closing = true;
		dialog._closeTimer = setTimeout(finish, 260); // a little over the 200ms transition
	}

	/* Escape cancels a modal dialog: close it through the animation instead. */
	function wireCancel(dialog) {
		// Chrome only lets a `cancel` be cancelled in some cases, so take Escape first.
		dialog.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				e.preventDefault();
				closeDialog(dialog);
			}
		});
		dialog.addEventListener('cancel', function (e) {
			e.preventDefault();
			closeDialog(dialog);
		});
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

		openDialog(dialog);
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
		root._ids = ids;
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

	/* --------------------------------------------------------- deadline */

	function plural(n, one, many) { return (n === 1 ? one : many).replace('%d', String(n)); }

	function spanText(secs) {
		var d = Math.floor(secs / 86400);
		var h = Math.floor((secs % 86400) / 3600);
		var m = Math.floor((secs % 3600) / 60);
		var u = i18n.units || {};
		var parts;
		if (d >= 1) {
			parts = [plural(d, u.day, u.days)];
			if (h) { parts.push(plural(h, u.hour, u.hours)); }
		} else if (h >= 1) {
			parts = [plural(h, u.hour, u.hours)];
			if (m) { parts.push(plural(m, u.minute, u.minutes)); }
		} else if (m >= 1) {
			parts = [plural(m, u.minute, u.minutes)];
		} else {
			parts = [u.lessThanMinute];
		}
		return parts.join(' ');
	}

	function initDeadline(root) {
		var box = root.querySelector('.nera-vote__deadline');
		if (!box) { return; }
		var at = parseInt(box.getAttribute('data-at'), 10) * 1000;
		var offset = parseInt(box.getAttribute('data-now'), 10) * 1000 - Date.now(); // server clock, not the visitor's
		var kind = box.getAttribute('data-kind');
		var out = box.querySelector('.nera-vote__countdown');
		var voteId = root.getAttribute('data-vote-id');
		var timer = null;

		function tick() {
			var left = Math.floor((at - (Date.now() + offset)) / 1000);
			if (left > 0) {
				var span = spanText(left);
				out.textContent = (kind === 'opens' ? i18n.opensIn : i18n.left).replace('%s', span);
				return;
			}
			clearInterval(timer);
			if (kind === 'opens') {
				// Reload once so the Options appear; the flag stops a loop if the clocks disagree.
				var flag = 'nera_vote_opened_' + voteId;
				try {
					if (!sessionStorage.getItem(flag)) { sessionStorage.setItem(flag, '1'); location.reload(); return; }
				} catch (e) { /* storage blocked: leave the text */ }
				out.textContent = i18n.opensNow;
				return;
			}
			out.textContent = i18n.ended;
			box.classList.add('is-ended');
			root.setAttribute('data-status', 'closed');
			root.querySelectorAll('.nera-vote__input').forEach(function (n) { n.disabled = true; });
		}

		tick();
		timer = setInterval(tick, 15000);
	}

	/* ------------------------------------------------- suggest a bottle */

	function initSuggest() {
		var dialog = document.getElementById('nera-vote-suggest');
		document.addEventListener('click', function (e) {
			var t = e.target instanceof Element ? e.target : null;
			if (!t) { return; }
			var open = t.closest('[data-nera-vote-suggest]');
			if (open && dialog) {
				dialog._opener = open;
				openDialog(dialog);
				var first = dialog.querySelector('input, textarea');
				if (first) { first.focus(); }
				return;
			}
			var close = t.closest('[data-nera-vote-close]');
			if (close && close.closest('dialog')) { closeDialog(close.closest('dialog')); }
		});
		if (dialog) {
			dialog.addEventListener('click', function (e) { if (e.target === dialog) { closeDialog(dialog); } });
			dialog.addEventListener('close', function () {
				dialog.classList.remove('is-open');
				var back = dialog._opener;
				if (back && document.contains(back)) { back.focus(); }
			});
			wireCancel(dialog);
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

		initDeadline(root);

		root.addEventListener('click', function (e) {
			var btn = e.target instanceof Element ? e.target.closest('.nera-vote__name, .nera-vote__thumb') : null;
			if (btn) { openOption(root, data, btn.getAttribute('data-option-id'), btn); }
		});

		if (status !== 'open' || !loggedIn) { return; }

		// Show remembered ticks straight away; the server state then replaces them.
		var single = root.getAttribute('data-mode') === 'single';
		var remembered = readCookie(voteId);
		root.querySelectorAll('.nera-vote__input').forEach(function (input) {
			if (remembered.indexOf(input.value) !== -1 && (!single || !root.querySelector('.nera-vote__input:checked'))) { input.checked = true; }
		});
		root._ids = remembered;

		api('votes/' + voteId).then(function (state) { applyState(root, state); }).catch(function () { /* keep server-rendered state */ });

		root.addEventListener('change', function (e) {
			var input = e.target;
			if (!(input instanceof HTMLInputElement) || !input.classList.contains('nera-vote__input')) { return; }
			var wanted = input.checked;
			var previous = root._ids || [];
			// One Option only: a move is a single request, so hold every radio until it lands.
			var locked = single ? root.querySelectorAll('.nera-vote__input') : [input];
			locked.forEach(function (n) { n.disabled = true; });
			api('votes/' + voteId + '/ballot', 'POST', { option_id: input.value, checked: wanted })
				.then(function (state) {
					applyState(root, state);
					notifySaved(sound);
				})
				.catch(function (err) {
					if (single) {
						root.querySelectorAll('.nera-vote__input').forEach(function (n) { n.checked = previous.indexOf(n.value) !== -1; });
					} else {
						input.checked = !wanted;
					}
					notifyError(i18n.error || err.message);
				})
				.then(function () { locked.forEach(function (n) { n.disabled = false; }); });
		});
	}

	ready(function () {
		document.querySelectorAll('.nera-vote').forEach(init);
		initSuggest();
	});
})();
