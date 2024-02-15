/*!
 * Copyright 2022 Tino Didriksen <mail@tinodidriksen.com>
 *
 * This project is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This project is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this project.  If not, see <http://www.gnu.org/licenses/>.
 */

(function (root, factory) {
	if (typeof define === 'function' && define.amd) {
		define([], factory);
	}
	else if (typeof module === 'object' && module.exports) {
		module.exports = factory();
	}
	else {
		root.corpus = factory();
	}
}(typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	const Defs = {
		focus: 'word',
		focus_n: 0,
		context: 15,
		context_chars: 60,
		pagesize: 50,
		offset: 1,
		};

	let state = {
		h: '',
		rs: {},
		ts: {},
		cs: {},
		fs: {},
		focus: Defs.focus,
		focus_n: Defs.focus_n,
		context: Defs.context,
		pagesize: Defs.pagesize,
		max_n: 0,
		prev_max_n: 0,
		offset: Defs.offset,
		named: [],
		last_rv: null,
		depc: 0,
		histgs: {},
		groupgs: {},
		popup: null,
		popup_info: null,
		};

	let options = {
		optVisible: {},
	};

	let fields = {};
	'word	lex	extra	pos	morph	func	sem	role	dself	dparent	word_lc	word_nd	lex_lc	lex_nd'.split(/\t/).forEach(function(e, i) {
		fields[e] = i;
	});

	// From https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Regular_Expressions
	function escapeRegExp(string) {
		return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); // $& means the whole matched string
	}

	// From https://github.com/eligrey/FileSaver.js/issues/774
	function saveAs(blob, name) {
		const a = document.createElementNS('http://www.w3.org/1999/xhtml', 'a');
		a.download = name;
		a.rel = 'noopener';
		a.href = URL.createObjectURL(blob);

		setTimeout(() => URL.revokeObjectURL(a.href), 40000);
		setTimeout(() => a.click(), 100);
	}

	function haveLocalStorage() {
		try {
			let storage = window.localStorage;
			let x = 'LocalStorageTest';
			storage.setItem(x, x);
			storage.removeItem(x);
		}
		catch (e) {
			return false;
		}
		return true;
	}

	function ls_get(key, def) {
		let v = null;
		try {
			v = window.localStorage.getItem(key);
		}
		catch (e) {
		}
		if (v === null) {
			if (def !== null && typeof def === 'object') {
				v = Object.assign({}, def);
			}
			else {
				v = def;
			}
		}
		else {
			v = JSON.parse(v);
		}
		return v;
	}

	function ls_set(key, val) {
		try {
			window.localStorage.setItem(key, JSON.stringify(val));
		}
		catch (e) {
		}
	}

	function ls_del(key) {
		window.localStorage.removeItem(key);
	}

	function u_reverse(str) {
		return [...str].reverse().join('');
	}

	function u_length(str) {
		return [...str].length;
	}

	function common_prefix(strs) {
		if (!strs[0] || strs.length ==  1) {
			return strs[0] || '';
		}

		let i = 0;
		while (strs[0][i] && strs.every(w => w[i] === strs[0][i])) {
			i++;
		}

		return strs[0].substr(0, i);
	}

	function common_suffix(strs) {
		strs = [].concat(strs);
		for (let i=0 ; i<strs.length ; ++i) {
			strs[i] = u_reverse(strs[i]);
		}
		return u_reverse(common_prefix(strs));
	}

	function escHTML(t) {
		if (typeof(t) !== 'string') {
			t = t.toString();
		}
		return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
	}

	function applyOptions() {
		for (let k in options.optVisible) {
			if (options.optVisible[k]) {
				$('.'+k).show();
			}
			else {
				$('.'+k).hide();
			}
		}
	}

	function loadOptions() {
		let opts = ls_get('corp-options', options);
		if (opts.hasOwnProperty('optVisible')) for (let k in opts.optVisible) {
			$('.arrOptVisible[value="'+k+'"]').prop('checked', opts.optVisible[k]);
			options['optVisible'][k] = opts.optVisible[k];
		}
	}

	function changeOption() {
		let e = $(this);
		if (e.hasClass('arrOptVisible')) {
			options['optVisible'][e.attr('value')] = e.prop('checked');
		}
		ls_set('corp-options', options);
		applyOptions();
	}

	function chkCompare() {
		let q = [];
		let q2 = [];
		$('.chkCompare:checked').slice(0, 10).each(function() {
			let t = $(this);
			q.push(t.attr('data-q'));
			q2.push(t.attr('data-q2'));
		});

		$('.chkCompare').attr('disabled', false);
		if ($('.chkCompare:checked').length >= 10) {
			$('.chkCompare').attr('disabled', true);
			$('.chkCompare:checked').attr('disabled', false);
		}

		if (q.length == 0) {
			q.push($('#formFreq input[name="q"]').val());
			q2.push($('#formFreq input[name="q2"]').val());
		}
		$('#formGroupBy input[name="q"]').val(q.join('~|~'));
		$('#formGroupBy input[name="q2"]').val(q2.join('~|~'));
	}

	function showParent() {
		let p = $(this).attr('data-parent');
		$('.depSelf').removeClass('depSelf');
		$('.depParent').removeClass('depParent');
		$(this).addClass('depSelf');
		$('#t'+p).addClass('depParent');
	}

	function popupExport(e, btn) {
		e.preventDefault();
		if (typeof btn == 'undefined') {
			btn = $(this);
		}
		let href = btn.attr('href');

		if (state.popup && !state.popup.closed) {
			state.popup.location = href;
			state.popup.focus();
			return;
		}

		state.popup = window.open(href, 'corp_export', 'left=100,top=100,width=400,height=600,popup');
	}

	function popupExportAll(e) {
		e.preventDefault();

		let btn = $(this);
		let href = btn.attr('data-href');
		let ids = [];
		btn.closest('table').find('a[data-id]').each(function(i, elem) {
			ids.push(elem.getAttribute('data-id'));
		});
		btn.attr('href', href + ids.join(','));

		return popupExport(e, btn);
	}

	function popupInfo(e) {
		e.preventDefault();
		let href = $(this).attr('href');

		if (state.popup_info && !state.popup_info.closed) {
			state.popup_info.location = href;
			state.popup_info.focus();
			return;
		}

		state.popup_info = window.open(href, 'corp_info', 'left=100,top=100,width=900,height=700,popup');
	}

	function repaginate() {
		let url = new URL(window.location);
		url.searchParams.set('pagesize', state.pagesize);

		let html = '<ul class="pagination">';
		let pgs = Math.ceil(state.max_n / state.pagesize);
		url.searchParams.set('offset', Math.max(state.offset - state.pagesize, 1));
		html += '<li class="page-item qpage-prev"><a href="'+escHTML(url)+'" class="page-link qpage" data-which="'+Math.max(state.offset - state.pagesize, 1)+'">&laquo;</a></li>';

		let i = 0;
		for ( ; i<pgs && i<10 ; ++i) {
			url.searchParams.set('offset', i*state.pagesize+1);
			html += '<li class="page-item"><a href="'+escHTML(url)+'" class="page-link qpage" data-which="'+(i*state.pagesize+1)+'">'+(i+1)+'</a></li>';
		}
		if (pgs > 10) {
			if (pgs > 500) {
				html += '<li class="page-item"><span class="page-link"><input type="number" class="form-control form-control-sm qpageinput"></span></li>';
			}
			else if (pgs > 11) {
				html += '<li class="page-item"><span class="page-link"><select class="form-select form-select-sm qpagesel">';
				for ( ; i<pgs-1 ; ++i) {
					html += '<option value="'+(i*state.pagesize+1)+'">'+(i+1)+'</option>';
				}
				html += '</select></span></li>';
			}
			url.searchParams.set('offset', (pgs-1)*state.pagesize+1);
			html += '<li class="page-item"><a href="'+escHTML(url)+'" class="page-link qpage" data-which="'+((pgs-1)*state.pagesize+1)+'">'+(pgs)+'</a></li>';
		}

		url.searchParams.set('offset', state.offset + state.pagesize);
		html += '<li class="page-item qpage-next"><a href="'+escHTML(url)+'" class="page-link qpage" data-which="'+(state.offset + state.pagesize)+'">&raquo;</a></li>';
		html += '</ul>';
		$('.qpages').html(html);

		pageToggleButtons();

		$('.qpage').click(function(e) {
			e.preventDefault();

			let p = $(this).attr('data-which');
			if (!p) {
				p = $(this).text();
			}

			loadOffset(parseInt(p), true);

			return false;
		});
		$('.qpagesel').change(function() {
			let p = $(this).val();
			loadOffset(parseInt(p), true);
		});
		$('.qpageinput').change(function() {
			let p = $(this).val();
			loadOffset((parseInt(p)-1)*state.pagesize+1, true);
		});
	}

	function pageToggleButtons() {
		let url = new URL(window.location);
		url.searchParams.set('pagesize', state.pagesize);

		$('.qpagesel').val(state.offset);
		$('.qpageinput').val(Math.ceil(state.offset/state.pagesize));
		url.searchParams.set('offset', Math.max(state.offset - state.pagesize, 1));
		$('.qpage-prev').find('.qpage').attr('href', url.toString()).attr('data-which', Math.max(state.offset - state.pagesize, 1));
		url.searchParams.set('offset', state.offset + state.pagesize);
		$('.qpage-next').find('.qpage').attr('href', url.toString()).attr('data-which', state.offset + state.pagesize);

		$('.qpages').find('.page-item').removeClass('disabled');
		$('.qpages').find('.qpage[data-which="'+state.offset+'"]').parent().addClass('disabled');

		if (state.offset == 1) {
			$('.qpage-prev').addClass('disabled');
		}
		if (state.offset >= state.max_n - state.pagesize) {
			$('.qpage-next').addClass('disabled');
		}
	}

	function loadOffset(p, h) {
		state.offset = p;

		let url = new URL(window.location);
		if (h) {
			url.searchParams.set('offset', state.offset);
			if (state.offset === Defs.offset) {
				url.searchParams.delete('offset');
			}
			url.searchParams.set('pagesize', state.pagesize);
			if (state.pagesize === Defs.pagesize) {
				url.searchParams.delete('pagesize');
			}
			window.history.pushState({}, '', url);
		}

		pageToggleButtons();

		if ($('.qresults').length) {
			let rq = {
				a: 'conc',
				h: state.hash,
				c: state.context,
				s: state.offset,
				n: state.pagesize,
				rs: [],
				ts: [],
				};

			$('.qresults').each(function() {
				let id = $(this).attr('id');
				rq.rs.push(id);
				rq.ts.push(id);
			});

			$('.qbody > table').addClass('opacity-25');
			$.getJSON('./callback.php', rq).done(handleConc);
		}

		if ($('.qfreqs').length) {
			let rq = {
				a: 'freq',
				t: url.searchParams.get('s'),
				h: state.hash,
				hf: state.hash_freq,
				hc: state.hash_combo,
				s: state.offset,
				n: state.pagesize,
				cs: [],
				};

			$('.qfreqs').each(function() {
				let id = $(this).attr('id');
				rq.cs.push(id);
			});

			$('.qbody > table').addClass('opacity-25');
			$.getJSON('./callback.php', rq).done(handleFreq);
		}
	}

	function appendIfNot0(tabs) {
		if (state.focus_n != 0) {
			return '<br><span class="fw-light text-muted">'+escHTML(tabs[state.focus_n] ? tabs[state.focus_n] : '-').replace(/ /g, "<br>")+'</span>';
		}
		return '';
	}

	function handleConc(rv) {
		state.last_rv = rv;
		let retry = false;
		let rq = {
			a: 'conc',
			h: state.hash,
			c: rv.c,
			s: rv.s,
			n: rv.n,
			rs: [],
			ts: [],
			cs: {},
			};

		if (rv.hasOwnProperty('ts')) {
			for (let corp in rv.ts) {
				if (!rv.ts[corp].d) {
					rq.ts.push(corp);
					retry = true;
				}

				let c = $('#'+corp);
				state.ts[corp] = rv.ts[corp];
				c.find('.qtotal').text(rv.ts[corp].n);
				state.max_n = Math.max(rv.ts[corp].n, state.max_n);

				if (!rv.ts[corp].d) {
					c.find('.qtotal').text(rv.ts[corp].n + '…');
				}
				else {
					if (rv.ts[corp].n === 0) {
						let c = $('#'+corp);
						c.find('.qrange').text('0');
						c.find('.qbody').html('<span class="fw-bold">No hits found.</span>');
					}
				}
			}
		}

		if (rv.hasOwnProperty('fs')) {
			for (let corp in rv.fs) {
				state.fs[corp] = {
					fields: [],
					num_fields: rv.fs[corp].length,
					};
				for (let i=0 ; i<rv.fs[corp].length ; ++i) {
					state.fs[corp].fields[rv.fs[corp][i]] = i;
				}
				//console.log(state.fs);
			}
		}

		if (state.prev_max_n < state.max_n) {
			repaginate();
			state.prev_max_n = state.max_n;
		}

		if (rv.hasOwnProperty('rs')) {
			for (let corp in rv.rs) {
				if (!rv.rs[corp].length && (rv.s < state.ts[corp].n || !state.ts[corp].n)) {
					if (!state.ts[corp].n && state.ts[corp].d) {
						continue;
					}
					rq.rs.push(corp);
					retry = true;
					continue;
				}
				state.rs[corp] = rv.rs[corp];
				rq.cs[corp] = {};

				let c = $('#'+corp);
				if (!rv.rs[corp].length) {
					c.find('.qrange').text('…');
					c.find('.qbody').html('<span class="fw-bold">No more hits.</span>');
					continue;
				}

				c.find('.qrange').text(rv.rs[corp][0].i+' to '+(rv.rs[corp][rv.rs[corp].length-1].i));
				let html = '';
				html += '';
				html += '<table class="table table-striped table-hover my-3">';
				html += '<thead><tr class="text-begin"><th colspan="4"><div><a class="btn btn-outline-primary btnExportAll" target="corp_export" href="#" data-href="./export.php?c['+corp+']=1&amp;ids=">Export all <i class="bi bi-box-arrow-up-right"></i></a></div></th></tr></thead>';
				html += '<tbody class="font-monospace text-nowrap text-break">';
				for (let i=0 ; i<rv.rs[corp].length ; ++i) {
					html += '<tr id="'+corp+'-'+rv.rs[corp][i].i+'" data-p="'+rv.rs[corp][i].p+'"><td class="qpending opacity-25">'+rv.rs[corp][i].i+'</td><td class="opacity-25">…loading…</td><td class="text-center fw-bold opacity-25">'+escHTML(rv.rs[corp][i].t)+'</td><td class="opacity-25">…loading…</td></tr>';
					rq.cs[corp][rv.rs[corp][i].i] = 1;
				}
				html += '</tbody></table>';
				c.find('.qbody').html(html);
				$('.btnExportAll').off().click(popupExportAll);
			}
		}

		if (rv.hasOwnProperty('cs')) {
			for (let corp in rv.cs) {
				let fields = state.fs[corp].fields;
				let num_fields = state.fs[corp].num_fields;

				for (let i=0 ; i<rv.cs[corp].length ; ++i) {
					let cntx = rv.cs[corp][i];
					if (rq.cs.hasOwnProperty(corp)) {
						delete(rq.cs[corp][cntx.i]);
					}
					let ts = cntx.t.split(/\n/);

					let row = $('#'+corp+'-'+cntx.i);
					let p = parseInt(row.attr('data-p'));
					let txt = row.find('.text-center').text();
					let s_tag = '';
					let s_article = '';
					let s_id = '';

					for (let j=0,n = cntx.b ; j<ts.length ; ++j) {
						if (/^<s[ >]/.test(ts[j])) {
							if (n <= p) {
								s_tag = ts[j];
								let m = ts[j].match(/ (?:tweet)="([^"]+)"/);
								if (m) {
									s_article = m[1];
								}
								m = ts[j].match(/ id="([^"]+)"/);
								if (m) {
									s_id = m[1];
								}
								//console.log([n, p, cntx.i]);
							}
						}
						else if (/^<\/s>/.test(ts[j])) {
							// Nothing
						}
						else {
							++n;
						}
					}

					let parts = {
						p: [],
						pz: [],
						ptz: 0,
						m: [],
						s: [],
						sz: [],
						stz: 0,
						};
					let n = cntx.b;
					let good = (s_id === '');
					let named = Array.from(state.named);
					let last_one = 0;
					for (let j=0 ; j<ts.length ; ++j) {
						if (/^<s[ >]/.test(ts[j])) {
							if (ts[j].indexOf(' tweet="'+s_article+'"') !== -1) {
								//console.log([cntx.i, j, n, s_tag]);
								good = true;
							}
							else if (ts[j].indexOf(' id="'+s_id+'"') !== -1) {
								//console.log([cntx.i, j, n, s_tag]);
								good = true;
							}
							else {
								good = (s_id === '');
							}
						}
						else if (/^<\/s>/.test(ts[j])) {
							// Nothing
						}
						else {
							if (good) {
								let tabs = ts[j].split(/\t/);
								while (tabs.length < num_fields) {
									tabs.push('');
								}
								if (!tabs[fields['lex']].length) {
									tabs[fields['lex']] = tabs[fields['word']];
								}
								if (!tabs[fields['lex_lc']].length) {
									tabs[fields['lex_lc']] = tabs[fields['lex']];
								}
								if (!tabs[fields['word_lc']].length) {
									tabs[fields['word_lc']] = tabs[fields['word']];
								}
								if (!tabs[fields['word_nd']].length) {
									tabs[fields['word_nd']] = tabs[fields['word_lc']];
								}
								if (!tabs[fields['lex_nd']].length) {
									tabs[fields['lex_nd']] = tabs[fields['word_nd']];
								}

								if (!last_one) {
									last_one = state.depc - parseInt(tabs[fields['dself']]) + 1;
								}
								if (parseInt(tabs[fields['dself']]) == 1) {
									last_one = state.depc;
								}
								++state.depc;

								let ins = tabs[0].replace(/[ \s\t]/g, '_');
								let title = '<div class="text-center">';
								for (let f in fields) {
									if (/_(lc|nd)$/.test(f) || f === 'dself' || f === 'dparent') {
										continue;
									}
									if (!tabs[fields[f]]) {
										continue;
									}
									if (f === 'word') {
										title += '<span class="text-danger">';
									}
									else if (f === 'pos') {
										title += '<span class="text-primary">';
									}
									else if (f === 'func') {
										title += '<span class="text-success fw-bold">';
									}
									else if (f === 'role') {
										title += '<span class="fw-bold">';
									}
									else {
										title += '<span>';
									}
									title += escHTML(tabs[fields[f]]);
									title += '</span><br>';
								}
								if (fields.hasOwnProperty('dself') && fields.hasOwnProperty('dparent') && tabs[fields['dself']] && tabs[fields['dparent']]) {
									title += '<span>'+tabs[fields['dself']]+' → '+tabs[fields['dparent']]+'</span>';
								}
								title += '</div>';
								title = escHTML(title);

								if (n < p) {
									parts.p.push('<span data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-html="true" data-bs-placement="bottom" title="'+title+'" data-parent="'+(last_one + parseInt(tabs[fields['dparent']]))+'" class="d-inline-block align-top text-center showParent" id="t'+state.depc+'">'+escHTML(ins)+appendIfNot0(tabs)+'</span>');
									parts.pz.push(u_length(tabs[0]));
									parts.ptz += u_length(tabs[0]) + 1;
								}
								else if (txt.length && txt.indexOf(tabs[0]) == 0) {
									parts.m.push('<span data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-html="true" data-bs-placement="bottom" title="'+title+'" data-parent="'+(last_one + parseInt(tabs[fields['dparent']]))+'" class="d-inline-block align-top text-center showParent" id="t'+state.depc+'">'+escHTML(ins)+appendIfNot0(tabs)+'</span>');
									txt = txt.substr(tabs[0].length).trim();
								}
								else if (named.length && txt.length && txt.indexOf('<'+named[0]+': '+tabs[0]+' >') == 0) {
									parts.m.push('<span data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-html="true" data-bs-placement="bottom" title="'+title+'" data-parent="'+(last_one + parseInt(tabs[fields['dparent']]))+'" class="d-inline-block align-top text-center showParent" id="t'+state.depc+'"><span class="fw-light">'+named[0]+':</span>'+escHTML(ins)+appendIfNot0(tabs)+'</span>');
									txt = txt.substr(('<'+named[0]+': '+tabs[0]+' >').length).trim();
									named.shift();
								}
								else {
									parts.s.push('<span data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-html="true" data-bs-placement="bottom" title="'+title+'" data-parent="'+(last_one + parseInt(tabs[fields['dparent']]))+'" class="d-inline-block align-top text-center showParent" id="t'+state.depc+'">'+escHTML(ins)+appendIfNot0(tabs)+'</span>');
									parts.sz.push(u_length(tabs[0]));
									parts.stz += u_length(tabs[0]) + 1;
								}
							}
							++n;
						}
					}

					let info = '<a class="popup-info" target="corp_info" href="./info.php?c['+escHTML(corp)+']=1&amp;id='+s_id+'"><i class="bi bi-info-square"></i></a>';
					if (s_article) {
						info += ' <a href="https://edu.visl.dk/social/?t='+s_article+'" target="_tweet"><i class="bi bi-link-45deg"></i></a>';
					}
					let html = '<td><a class="popup-export" target="corp_export" href="./export.php?c['+escHTML(corp)+']=1&amp;ids='+s_id+'" data-id="'+s_id+'"><i class="bi bi-box-arrow-up-right"></i></a> '+info+'</td><td class="text-end">';
					while (parts.p.length > 1 && parts.ptz > Defs.context_chars) {
						parts.ptz -= parts.pz[0] + 1;
						parts.p.shift();
						parts.pz.shift();
					}
					html += parts.p.join(' ');
					html += '</td><td class="text-start"><span class="fw-bold me-1">';
					html += parts.m.join(' ');
					html += '</span> ';
					while (parts.s.length > 1 && parts.stz > Defs.context_chars) {
						parts.stz -= parts.sz[parts.s.length-1] + 1;
						parts.s.pop();
						parts.sz.pop();
					}
					html += parts.s.join(' ');
					html += '</td>';
					row.html(html);

					let popoverTriggerList = row.get(0).querySelectorAll('[data-bs-toggle="popover"]');
					let popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));
					$(row).find('.popup-export').off().click(popupExport);
					$(row).find('.popup-info').off().click(popupInfo);
				}

				rq.cs[corp] = {};
				$('#'+corp).find('.qpending').each(function() {
					rq.cs[corp][parseInt($(this).text())] = 1;
				});
				$('#'+corp).find('.showParent').off().click(showParent).focus(showParent).mouseover(showParent);
			}
		}

		for (let corp in rq.cs) {
			if (Object.keys(rq.cs[corp]).length) {
				rq.cs[corp] = {
					rs: Object.keys(rq.cs[corp]).join(','),
					};
				retry = true;
			}
		}

		if (retry) {
			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleConc);}, 1000);
		}
	}

	function handleFreq(rv) {
		let url = new URL(window.location);
		let params = url.searchParams;
		let retry = false;
		let rq = {
			a: 'freq',
			t: params.get('s'),
			h: state.hash,
			hf: state.hash_freq,
			hc: state.hash_combo,
			s: rv.s,
			n: rv.n,
			cs: [],
			};

		let field = params.get('f');

		let query1 = params.get('q');
		let query2 = params.has('q2') ? params.get('q2') : '';
		let query = query2 ? query2 : query1;

		let by = params.get('b');
		let offset = parseInt(params.get('o'));
		// Turn context conditions into edge conditions
		if (by === 'lc') {
			offset -= 1;
			by = 'le';
		}
		else if (by === 'rc') {
			offset += 1;
			by = 're';
		}

		let hfield = field;
		if (/^(word|lex)$/.test(hfield)) {
			if (params.has('nd') && params.get('nd')) {
				hfield += '_nd';
			}
			else if (params.has('lc') && params.get('lc')) {
				hfield += '_lc';
			}
		}
		let bits = query.split(/(?<=\][*+?]?)\s*(?=\[)/);
		// Turn right edge conditions into left edge conditions
		if (by === 're') {
			offset += bits.length - 1;
		}
		// Create query template to be filled with the found token
		let search = '';
		if (offset < 0) {
			search = '['+hfield+'="{TOKEN}"]';
			for (let i=offset ; i<-1 ; ++i) {
				search += ' []';
			}
			search += ' '+query;
		}
		else if (offset >= bits.length) {
			search = query;
			for (let i=bits.length ; i<offset ; ++i) {
				search += ' []';
			}
			search += ' ['+hfield+'="{TOKEN}"]';
		}
		else {
			bits[offset] = bits[offset].replace(new RegExp('\\b'+field+'=".+?"'), hfield+'="{TOKEN}"');
			if (!(new RegExp('\\b'+field+'=')).test(bits[offset])) {
				let firstsq = bits[offset].indexOf('[') + 1;
				bits[offset] = bits[offset].substr(0, firstsq) + hfield + '="{TOKEN}" & ' + bits[offset].substr(firstsq);
			}
			search += bits.join(' ');
		}

		let search1 = query2 ? query1 : search;
		let search2 = query2 ? search : query2;

		let has_group = false;
		if ($('#btnGroupBy').length) {
			has_group = true;
			$('#btnGroupBy').text('Compare frequencies');
		}

		if (rv.hasOwnProperty('cs')) {
			for (let corp in rv.cs) {
				if (!rv.cs[corp].d) {
					rq.cs.push(corp);
					retry = true;
				}

				let url = new URL(window.location.origin + window.location.pathname);
				url.searchParams.set('l', params.get('l'));
				url.searchParams.set('c['+corp+']', '1');
				url.searchParams.set('s', 's');
				if (params.has('ub') && params.get('ub')) {
					url.searchParams.set('ub', '1');
				}

				let c = $('#'+corp);
				state.ts[corp] = rv.cs[corp];
				c.find('.qtotal').text(rv.cs[corp].n+' (Σ '+rv.cs[corp].t+')');
				state.max_n = Math.max(rv.cs[corp].n, state.max_n);
				repaginate();

				if (!rv.cs[corp].d) {
					c.find('.qtotal').text(rv.cs[corp].n + '…');
				}
				else {
					if (rv.cs[corp].n === 0) {
						let c = $('#'+corp);
						c.find('.qrange').text('0');
						c.find('.qbody').html('<span class="fw-bold">No hits found.</span>');
					}
				}

				if (!rv.cs[corp].f.length) {
					c.find('.qrange').text('…');
					if (!rv.cs[corp].d) {
						c.find('.qbody').text('…still searching…');
					}
					else {
						c.find('.qbody').html('<span class="fw-bold">No more hits.</span>');
					}
					continue;
				}

				let button = '<a href="./callback.php?a=freq&amp;cs[]='+Object.keys(state.corps).join('&amp;cs[]=')+'&amp;h='+rq.h+'&amp;hf='+rq.hf+'&amp;hc='+rq.hc+'&amp;t='+rq.t+'&amp;tsv='+corp+'" class="btn btn-outline-success my-3">Download TSV <i class="bi bi-download"></i></a>';
				c.find('.qtsv').html(button);

				let combo = false;
				if (corp.indexOf('_0combo_') !== -1) {
					combo = true;
					let lang = corp.substr(0, 3);
					for (let c2 in rv.cs) {
						if (c2.indexOf(lang) === 0) {
							url.searchParams.set('c['+c2+']', '1');
						}
					}
					url.searchParams.delete('c['+corp+']');
				}

				c.find('.qrange').text(rv.s+' to '+Math.min(rv.s + rv.n, rv.cs[corp].n));
				let html = '<table class="table table-striped table-hover my-3">';
				// '∕' is U+2215 Division Slash
				// '·' is U+00B7 Middle Dot
				// '²' is U+00B2 Superscript 2
				// '⁸' is U+2078 Superscript 8
				html += '<thead><tr><th>Token</th>';
				if (/^(?:h_)?(word|lex)(_nd|_lc|$)/.test(field)) {
					html += '<th class="text-vertical qcol-grf" title="Global relative frequency"><span class="color-red">G: freq²∕norm</span></th>';
					if (!combo) {
						html += '<th class="text-vertical qcol-crf" title="Corpus relative frequency"><span class="color-red">C: freq²∕norm</span></th>';
					}
					if (corp.indexOf('-') !== -1) {
						html += '<th class="text-vertical qcol-scrf" title="Sub-corpus relative frequency"><span class="color-red">S: freq²∕norm</span></th>';
					}
				}
				if (!combo) {
					html += '<th class="text-vertical qcol-nf" title="Corpus normalized frequency"><span class="color-orange">C: freq∕corp · 10⁸</span></th>';
				}
				else {
					html += '<th class="text-vertical qcol-nf" title="Global normalized frequency"><span class="color-orange">G: freq∕corp · 10⁸</span></th>';
				}
				if (corp.indexOf('-') !== -1) {
					html += '<th class="text-vertical qcol-scnf" title="Sub-corpus normalized frequency"><span class="color-orange">S: freq∕corp · 10⁸</span></th>';
				}
				html += '<th class="text-vertical qcol-pcnt" title="Percentage of total hits"><span class="color-green">freq∕conc</span></th><th class="text-vertical qcol-num" title="Number of hits">num</th>';
				if (has_group) {
					html += '<th class="text-vertical" title="Add to compare">Compare?</th>';
				}
				html += '</tr></thead>';
				html += '<tbody class="font-monospace text-nowrap text-break">';
				for (let i=0 ; i<rv.cs[corp].f.length ; ++i) {
					url.searchParams.set('q', search1.replace('{TOKEN}', escapeRegExp(rv.cs[corp].f[i][0])));
					url.searchParams.set('q2', search2.replace('{TOKEN}', escapeRegExp(rv.cs[corp].f[i][0])));
					html += '<tr><td><a href="'+escHTML(url.toString())+'" target="'+corp+'">'+escHTML(rv.cs[corp].f[i][0])+'</a></td>';
					if (/^(?:h_)?(word|lex)(_nd|_lc|$)/.test(field)) {
						html += '<td class="text-end qcol-grf">'+escHTML(rv.cs[corp].f[i][2].toFixed(2))+'</td>';
						if (!combo) {
							html += '<td class="text-end qcol-crf">'+escHTML(rv.cs[corp].f[i][3].toFixed(2))+'</td>';
						}
						if (corp.indexOf('-') !== -1) {
							html += '<td class="text-end qcol-scrf">'+escHTML(rv.cs[corp].f[i][4].toFixed(2))+'</td>';
						}
					}
					html += '<td class="text-end qcol-nf">'+(rv.cs[corp].f[i][1] / rv.cs[corp].w * 100000000).toFixed(2)+'</td>';
					if (corp.indexOf('-') !== -1) {
						html += '<td class="text-end qcol-scnf">'+(rv.cs[corp].f[i][1] / rv.cs[corp].ws * 100000000).toFixed(2)+'</td>';
					}
					html += '<td class="text-end qcol-pcnt">'+(rv.cs[corp].f[i][1] / rv.cs[corp].t * 100).toFixed(1)+'%</td><td class="text-end qcol-num">'+rv.cs[corp].f[i][1]+'</td>';
					if (has_group) {
						html += '<td><input type="checkbox" class="form-check-input chkCompare" data-q="'+escHTML(url.searchParams.get('q'))+'" data-q2="'+escHTML(url.searchParams.get('q2'))+'"></td>';
					}
					html += '</tr>';
				}
				html += '</tbody></table>';
				c.find('.qbody').html(html);
				c.find('.chkCompare').off().click(chkCompare);
				c.find('.chkCompare').slice(0,10).click();
			}

			applyOptions();
		}

		if (retry) {
			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleFreq);}, 1000);
		}
	}

	function handleHist(rv) {
		let url = new URL(window.location);
		let params = url.searchParams;
		let retry = false;
		let rq = {
			a: 'hist',
			g: params.get('g'),
			h: state.hash,
			s: rv.s,
			n: rv.n,
			cs: [],
			};

		let by_art = (params.has('ha') && params.get('ha'));

		let link = {
			rx: /^(\d{4})(\d{2})(\d{2})/,
			rpl: '$1-$2-$3.*',
			};
		if (rq.g == 'Y') {
			link.rx = /^(\d{4})/;
			link.rpl = '$1.*';
		}
		else if (rq.g == 'Y-m') {
			link.rx = /^(\d{4})(\d{2})/;
			link.rpl = '$1-$2.*';
		}
		else if (rq.g == 'Y-m-d H') {
			link.rx = /^(\d{4})(\d{2})(\d{2})(\d{2})/;
			link.rpl = '$1-$2-$3\\ $4.*';
		}
		else if (rq.g == 'Y H') {
			link.rx = /^(\d{4})(\d{2})/;
			link.rpl = '$1.*\\ $2.*';
		}

		$('.qpages').hide();
		let to_render = {};

		if (rv.hasOwnProperty('cs')) {
			for (let corp in rv.cs) {
				if (!rv.cs[corp].d) {
					rq.cs.push(corp);
					retry = true;
				}

				let c = $('#'+corp);
				state.ts[corp] = rv.cs[corp];
				c.find('.qtotal').text(rv.cs[corp].h.length);

				if (!rv.cs[corp].d) {
					c.find('.qtotal').text(rv.cs[corp].h.length + '…');
				}
				else {
					if (rv.cs[corp].h.length === 0) {
						let c = $('#'+corp);
						c.find('.qrange').text('0');
						c.find('.qbody').html('<span class="fw-bold">No hits found.</span>');
					}
				}

				if (!rv.cs[corp].h.length) {
					c.find('.qrange').text('…');
					if (!rv.cs[corp].d) {
						c.find('.qbody').text('…still searching…');
					}
					else {
						c.find('.qbody').html('<span class="fw-bold">No more hits.</span>');
					}
					continue;
				}

				let url = new URL(window.location.origin + window.location.pathname);
				url.searchParams.set('l', params.get('l'));
				url.searchParams.set('c['+corp+']', '1');
				url.searchParams.set('s', 's');
				if (params.has('ub') && params.get('ub')) {
					url.searchParams.set('ub', '1');
				}

				state.histgs[corp] = rv.cs[corp].h;
				to_render[corp] = [];
				if (corp.indexOf('-') !== -1) {
					to_render[corp.substr(0, corp.indexOf('-'))+'-subc'] = [];
				}

				let tsv = 'Group\tArticles\tSentences\tHits\tCArticles\tCSentences\tCTokens\tCWords\n';

				c.find('.qrange').text('');
				let html = '<button class="btn btn-outline-success my-3 btnGetTSV">Download TSV <i class="bi bi-download"></i></button><table class="d-inline-block table table-striped table-hover my-3">';
				html += '<thead><tr><th>Group</th><th class="text-vertical">Article hits</th><th class="text-vertical">Sentence hits</th><th class="text-vertical">All hits</th><th class="text-vertical">% Articles</th><th class="text-vertical">% Sentences</th><th class="text-vertical">% Hits/CSentences</th><th class="text-vertical text-muted">C Articles</th><th class="text-vertical text-muted">C Sentences</th></tr></thead>';
				html += '<tbody class="font-monospace text-nowrap text-break">';
				for (let i=0 ; i<rv.cs[corp].h.length ; ++i) {
					if (rv.cs[corp].h[i][1] <= 0) {
						continue;
					}
					let lstamp = rv.cs[corp].h[i][0].toString().replace(link.rx, link.rpl);
					url.searchParams.set('q', '('+params.get('q')+') within <s lstamp="'+lstamp+'"/>');
					if (params.has('q2')) {
						url.searchParams.set('q2', params.get('q2'));
					}
					html += '<tr id="h'+escHTML(rv.cs[corp].h[i][0])+'"><td><a href="'+escHTML(url.toString())+'">'+escHTML(rv.cs[corp].h[i][0])+'</a></td><td class="text-end">'+rv.cs[corp].h[i][1]+'</td><td class="text-end">'+rv.cs[corp].h[i][2]+'</td><td class="text-end">'+rv.cs[corp].h[i][3]+'</td><td class="text-end">'+(rv.cs[corp].h[i][1]*100.0 / rv.cs[corp].h[i][4]).toFixed(2)+'%</td><td class="text-end">'+(rv.cs[corp].h[i][2]*100.0 / rv.cs[corp].h[i][5]).toFixed(2)+'%</td><td class="text-end">'+(rv.cs[corp].h[i][3]*100.0 / rv.cs[corp].h[i][5]).toFixed(2)+'%</td><td class="text-end text-muted">'+rv.cs[corp].h[i][4]+'</td><td class="text-end text-muted">'+rv.cs[corp].h[i][5]+'</td></tr>';
					tsv += rv.cs[corp].h[i].join('\t')+'\n';
				}
				state.tsv = tsv;
				html += '</tbody></table><button class="btn btn-outline-success my-3 btnGetTSV">Download TSV <i class="bi bi-download"></i></button>';
				c.find('.qbody').html(html);

				c.find('.btnGetTSV').click(function() {
					saveAs(new Blob([state.tsv], {type: 'text/tab-separated-values'}), 'histogram.tsv');
				});
			}
		}

		let ks = Object.keys(state.histgs).sort();
		for (let corp in to_render) {
			if (corp.indexOf('-subc') !== -1) {
				let p = corp.substr(0, corp.indexOf('-subc'))+'-';
				for (let k=0 ; k<ks.length ; ++k) {
					if (ks[k].indexOf(p) === 0) {
						to_render[corp].push(state.histgs[ks[k]]);
					}
				}
				if (to_render[corp].length == 1) {
					delete to_render[corp];
				}
			}
			else {
				to_render[corp].push(state.histgs[corp]);
			}
		}

		let f_hits = 3;
		let f_aggr = 5;
		let f_label = '% Hits/CSentences';
		if (by_art) {
			f_hits = 1;
			f_aggr = 4;
			f_label = '% Articles/CArticles';
		}

		for (let corp in to_render) {
			let c = $('#graph-'+corp);

			let gs = [];
			let g = [];
			let Y = 0;

			// Determine the max analyzed bodies for a group and consider groups with <10% of that as sparse
			let sparse = 0;
			for (let k=0 ; k<to_render[corp].length ; ++k) {
				for (let i=0 ; i<to_render[corp][k].length ; ++i) {
					sparse = Math.max(sparse, to_render[corp][k][i][f_aggr]);
					to_render[corp][k][i][6] = to_render[corp][k][i][f_hits]*100.0 / to_render[corp][k][i][f_aggr];
				}
			}
			sparse = sparse/10.0;

			// Find max value so all graphs can get the same Y-axis
			let y_max = 0;
			for (let k=0 ; k<to_render[corp].length ; ++k) {
				for (let i=0 ; i<to_render[corp][k].length ; ++i) {
					if (to_render[corp][k][i][f_aggr] >= sparse) {
						y_max = Math.max(y_max, to_render[corp][k][i][6]);
					}
				}
			}

			for (let k=0 ; k<to_render[corp].length ; ++k) {
				for (let i=0 ; i<to_render[corp][k].length ; ++i) {
					let y = to_render[corp][k][i][0].toString().substr(0, 4);
					if (g.length >= 365 && Y != y) {
						if (typeof g[g.length-1] === 'string') {
							g.pop();
						}
						gs.push([].concat(g));
						g = [];
					}
					Y = y;

					if (g.length >= 1000) {
						if (typeof g[g.length-1] === 'string') {
							g.pop();
						}
						gs.push([].concat(g));
						g = [];
					}

					let to_p = to_render[corp][k][i];
					if (!params.has('xe') && to_render[corp][k][i][1] === 0) {
						to_p = '- skip -';
					}
					if (!params.has('xs') && to_render[corp][k][i][f_aggr] < sparse) {
						to_p = '- sparse -';
					}

					if (typeof to_p === 'string') {
						if (g.length && typeof g[g.length-1] !== 'string') {
							g.push(to_p);
						}
					}
					else {
						g.push(to_p);
					}
				}
				g.push('- sub-break -');
			}
			g.pop();
			gs.push(g);
			//console.log(gs);

			let html = '';
			for (let k=0 ; k<gs.length ; ++k) {
				let ys = {};
				for (let i=0 ; i<gs[k].length ; ++i) {
					if (typeof gs[k][i] === 'string') {
						continue;
					}
					ys[gs[k][i][0].toString().substr(0, 4)] = true;
				}
				// Collapse year ranges
				let years = Object.keys(ys).map(function(v, x) {return parseInt(v); });
				for (let i=1 ; i<years.length-1 ; ) {
					let j = i;
					while (j<years.length-1 && years[j-1] + 1 == years[j] && years[j] + 1 == years[j+1]) {
						++j;
					}
					if (j != i) {
						years.splice(i, j-i);
					}
					else {
						++i;
					}
				}
				html += '<div class="my-3" style="max-width: 75vw; overflow-x: scroll;"><div class="ghead fw-bold fs-4 text-begin">'+corp+' ('+years.join('-')+')</div><canvas id="chart-'+corp+'-'+k+'" style="width: '+(gs[k].length*5)+'px;"></canvas></div>';
			}
			c.find('.qbody').html(html);

			for (let k=0 ; k<gs.length ; ++k) {
				let labels = [];
				let bars = [];
				let c_bars = [];
				let c_borders = [];

				for (let i=0 ; i<gs[k].length ; ++i) {
					if (typeof gs[k][i] === 'string') {
						labels.push(gs[k][i]);
						bars.push(0);
						c_bars.push('rgba(32, 32, 32, 0.2)');
						c_borders.push('rgb(32, 32, 32)');
						continue;
					}

					labels.push(gs[k][i][0]);
					if (gs[k][i][f_aggr] < 1) {
						bars.push(0);
						c_bars.push('rgba(64, 64, 64, 0.2)');
						c_borders.push('rgb(64, 64, 64)');
					}
					else {
						bars.push(gs[k][i][6]);
						if (gs[k][i][f_aggr] < sparse) {
							c_bars.push('rgba(255, 159, 64, 0.2)');
							c_borders.push('rgb(255, 159, 64)');
						}
						else {
							c_bars.push('rgba(54, 162, 235, 0.2)');
							c_borders.push('rgb(54, 162, 235)');
						}
					}
				}

				let chart = new Chart(document.getElementById('chart-'+corp+'-'+k),
					{
						data: {
							labels: labels,
							datasets: [
								{
									type: 'bar',
									label: f_label,
									data: bars,
									backgroundColor: c_bars,
									borderColor: c_borders,
									minBarLength: 5,
									barPercentage: 1,
									categoryPercentage: 1,
								},
							],
						},
						options: {
							responsive: false,
							maintainAspectRatio: false,
							interaction: {
								mode: 'index',
								intersect: false,
							},
							plugins: {
								legend: {
									display: false,
								},
							},
							scales: {
								y: {
									beginAtZero: true,
									max: y_max,
								},
							},
							onClick: function(e) {
								let pos = Chart.helpers.getRelativePosition(e, chart);
								let x = chart.scales.x.getValueForPixel(pos.x);
								let l = chart.scales.x.getLabelForValue(x);
								if (!/^\d/.test(l)) {
									return;
								}
								window.location.hash = '#h'+l;
							},
						},
					});
				chart.resize(gs[k].length*10, 300);
			}
		}

		if (retry) {
			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleHist);}, 1000);
		}
	}

	function handleGroup(rv) {
		let url = new URL(window.location);
		let params = url.searchParams;
		let retry = false;
		let rq = {
			a: 'group',
			h: state.hash,
			gc: params.get('gc'),
			s: rv.s,
			n: rv.n,
			cs: [],
			};
		let gs = [];
		for (let i=0 ; i<10 ; ++i) {
			let g = 'g'+i;
			if (params.has(g)) {
				rq[g] = params.get(g);
				gs.push(rq[g]);
			}
		}

		let by_art = (params.has('ha') && params.get('ha'));

		$('.qpages').hide();
		let to_render = {};

		let arr_hash = state.hash.split(';');
		let arr_q = params.has('q') ? params.get('q').split('~|~') : [];
		let arr_q2 = params.has('q2') ? params.get('q2').split('~|~') : [];
		let arr_qs = [];
		for (let i=0 ; i<arr_q.length ; ++i) {
			arr_qs.push(arr_q[i] + ' / ' + arr_q2[i]);
		}

		let q_prefix = common_prefix(arr_qs);
		let q_suffix = common_suffix(arr_qs);
		let q_show = [];
		for (let i=0 ; i<arr_qs.length ; ++i) {
			let q = arr_qs[i];
			q = q.substr(0, q.length - q_suffix.length);
			q = q.substr(q_prefix.length);
			if (!q) {
				q = arr_qs[i];
			}
			q_show.push(q);
		}

		let display_legend = arr_q.length > 1;

		if (rv.hasOwnProperty('cs')) {
			for (let corp in rv.cs) {
				let c = $('#'+corp);
				state.ts[corp] = rv.cs[corp];
				state.groupgs[corp] = {};

				let qtotal = 0;
				let done = true;
				for (let hk = 0 ; hk<arr_hash.length ; ++hk) {
					qtotal += rv.cs[corp][hk].h.length;
					if (!rv.cs[corp][hk].d) {
						rq.cs.push(corp);
						retry = true;
						done = false;
					}
				}
				c.find('.qtotal').text(qtotal);

				if (!done) {
					c.find('.qtotal').text(qtotal + '…');
				}
				else {
					if (qtotal === 0) {
						let c = $('#'+corp);
						c.find('.qrange').text('0');
						c.find('.qbody').html('<span class="fw-bold">No hits found.</span>');
					}
				}

				if (!qtotal) {
					c.find('.qrange').text('…');
					if (!done) {
						c.find('.qbody').text('…still searching…');
					}
					else {
						c.find('.qbody').html('<span class="fw-bold">No more hits.</span>');
					}
					continue;
				}

				let url = new URL(window.location.origin + window.location.pathname);
				url.searchParams.set('l', params.get('l'));
				url.searchParams.set('c['+corp+']', '1');
				url.searchParams.set('s', 's');
				if (params.has('ub') && params.get('ub')) {
					url.searchParams.set('ub', '1');
				}

				to_render[corp] = [];
				if (corp.indexOf('-') !== -1) {
					to_render[corp.substr(0, corp.indexOf('-'))+'-subc'] = [];
				}

				for (let hk = 0 ; hk<arr_hash.length ; ++hk) {
					state.groupgs[corp][hk] = rv.cs[corp][hk].h;
					to_render[corp][hk] = rv.cs[corp][hk].h;
					if (corp.indexOf('-') !== -1) {
						to_render[corp.substr(0, corp.indexOf('-'))+'-subc'][hk] = rv.cs[corp][hk].h;
					}
				}

				let tsv = 'Group\tArticles\tSentences\tHits\tCArticles\tCSentences\tCTokens\tCWords\n';

				c.find('.qrange').text('');
				let html = '<button class="btn btn-outline-success my-3 btnGetTSV">Download TSV <i class="bi bi-download"></i></button><table class="d-inline-block table table-striped table-hover my-3">';
				html += '<thead><tr><th>Group</th><th class="text-vertical">Article hits</th><th class="text-vertical">Sentence hits</th><th class="text-vertical">All hits</th><th class="text-vertical">% Articles</th><th class="text-vertical">% Sentences</th><th class="text-vertical">% Hits/CSentences</th><th class="text-vertical text-muted">C Articles</th><th class="text-vertical text-muted">C Sentences</th></tr></thead>';
				html += '<tbody class="font-monospace text-nowrap text-break">';
				for (let hk = 0 ; hk<arr_hash.length ; ++hk) {
					if (display_legend) {
						html += '<tr><th colspan="9">'+escHTML(q_show[hk])+'</th></tr>';
					}
					tsv += '# '+arr_q[hk]+'\n';
					for (let i=0 ; i<rv.cs[corp][hk].h.length ; ++i) {
						if (rv.cs[corp][hk].h[i][1] <= 0) {
							continue;
						}
						let attrs = rv.cs[corp][hk].h[i][0].split(' |~| ');
						for (let a=0 ; a<attrs.length ; ++a) {
							attrs[a] = gs[a]+'="'+escHTML(attrs[a])+'"';
						}
						url.searchParams.set('q', '('+arr_q[hk]+') within <s '+attrs.join(' & ')+'/>');
						if (arr_q2[hk]) {
							url.searchParams.set('q2', arr_q2[hk]);
						}
						rv.cs[corp][hk].h[i][0] = rv.cs[corp][hk].h[i][0].replace(' |~| ', '; ');
						html += '<tr id="h'+escHTML(rv.cs[corp][hk].h[i][0])+'"><td><a href="'+escHTML(url.toString())+'">'+escHTML(rv.cs[corp][hk].h[i][0])+'</a></td><td class="text-end">'+rv.cs[corp][hk].h[i][1]+'</td><td class="text-end">'+rv.cs[corp][hk].h[i][2]+'</td><td class="text-end">'+rv.cs[corp][hk].h[i][3]+'</td><td class="text-end">'+(rv.cs[corp][hk].h[i][1]*100.0 / rv.cs[corp][hk].h[i][4]).toFixed(2)+'%</td><td class="text-end">'+(rv.cs[corp][hk].h[i][2]*100.0 / rv.cs[corp][hk].h[i][5]).toFixed(2)+'%</td><td class="text-end">'+(rv.cs[corp][hk].h[i][3]*100.0 / rv.cs[corp][hk].h[i][5]).toFixed(2)+'%</td><td class="text-end text-muted">'+rv.cs[corp][hk].h[i][4]+'</td><td class="text-end text-muted">'+rv.cs[corp][hk].h[i][5]+'</td></tr>';
						tsv += rv.cs[corp][hk].h[i].join('\t')+'\n';
					}
				}
				state.tsv = tsv;
				html += '</tbody></table><button class="btn btn-outline-success my-3 btnGetTSV">Download TSV <i class="bi bi-download"></i></button>';
				c.find('.qbody').html(html);

				c.find('.btnGetTSV').click(function() {
					saveAs(new Blob([state.tsv], {type: 'text/tab-separated-values'}), 'histogram.tsv');
				});
			}
		}

		let f_hits = 3;
		let f_aggr = 5;
		let f_label = '% Hits/CSentences';
		if (by_art) {
			f_hits = 1;
			f_aggr = 4;
			f_label = '% Articles/CArticles';
		}

		for (let corp in to_render) {
			let g_keys = {};

			// Determine the average analyzed bodies for a group and consider groups with <10% of that as sparse
			let sparse = 0;
			for (let hk = 0 ; hk<arr_hash.length ; ++hk) {
				let trc = to_render[corp][hk];
				let psp = 0;
				for (let k=0 ; k<trc.length ; ++k) {
					g_keys[trc[k][0]] = true;
					psp += trc[k][f_aggr];
					trc[k][6] = trc[k][f_hits]*100.0 / trc[k][f_aggr];
				}
				sparse += psp/trc.length;
			}
			sparse = sparse/10.0;

			g_keys = Object.keys(g_keys).sort();

			// Find max value so all graphs can get the same Y-axis
			// Also turns arrays into objects indexed by the first column
			let y_max = 0;
			for (let hk = 0 ; hk<arr_hash.length ; ++hk) {
				let trc = to_render[corp][hk];

				let trc_k = {};
				for (let k=0 ; k<g_keys.length ; ++k) {
					trc_k[g_keys[k]] = 0.0;
				}

				let h_y_max = 0;
				for (let k=0 ; k<trc.length ; ++k) {
					trc_k[trc[k][0]] = trc[k][6];
					if (true || trc[k][f_aggr] >= sparse) {
						h_y_max = Math.max(h_y_max, trc[k][6]);
					}
				}
				y_max += h_y_max;

				to_render[corp][hk] = trc_k;
			}

			let colors = [
				['rgba(54, 162, 235, 0.8)', 'rgb(54, 162, 235)'],
				['rgba(235, 54, 162, 0.8)', 'rgb(235, 54, 162)'],
				['rgba(162, 54, 235, 0.8)', 'rgb(162, 54, 235)'],
				['rgba(162, 235, 54, 0.8)', 'rgb(162, 235, 54)'],
				['rgba(54, 162, 162, 0.8)', 'rgb(54, 162, 162)'],
				['rgba(54, 54, 235, 0.8)', 'rgb(54, 54, 235)'],
				['rgba(54, 162, 54, 0.8)', 'rgb(54, 162, 54)'],
				['rgba(235, 162, 235, 0.8)', 'rgb(235, 162, 235)'],
				['rgba(162, 162, 235, 0.8)', 'rgb(162, 162, 235)'],
				['rgba(162, 235, 162, 0.8)', 'rgb(162, 235, 162)'],
				];

			let datasets = [];
			for (let hk = 0 ; hk<arr_hash.length ; ++hk) {
				let data = to_render[corp][hk];
				let dataset = {
					type: 'bar',
					label: q_show[hk],
					data: data,
					backgroundColor: colors[hk][0],
					borderColor: colors[hk][1],
					minBarLength: 0,
					barPercentage: 1,
					categoryPercentage: 1,
					};
				datasets.push(dataset);
			}

			$('#graph-'+corp).html('<div class="my-3" style="max-width: 90vw; overflow-x: scroll;"><div class="ghead fw-bold fs-4 text-begin">'+corp+'</div><canvas id="chart-'+corp+'" style="width: '+(g_keys.length*5)+'px;"></canvas></div>');

			let ce = document.getElementById('chart-'+corp);
			let chart = new Chart(ce,
				{
					data: {
						labels: g_keys,
						datasets: datasets,
					},
					options: {
						responsive: false,
						maintainAspectRatio: false,
						interaction: {
							mode: 'index',
							intersect: false,
						},
						plugins: {
							legend: {
								display: display_legend,
							},
							title: {
								display: true,
								text: f_label,
							},
						},
						scales: {
							x: {
								stacked: true,
							},
							y: {
								beginAtZero: true,
								//max: y_max,
								stacked: true,
							},
						},
						onClick: function(e) {
							let pos = Chart.helpers.getRelativePosition(e, chart);
							let x = chart.scales.x.getValueForPixel(pos.x);
							let l = chart.scales.x.getLabelForValue(x);
							let y = chart.scales.y.getValueForPixel(pos.y);
							let h = chart.scales.y.getLabelForValue(y);
							console.log(e, pos, x, l, y, h);
							if (!/^\d/.test(l)) {
								return;
							}
							window.location.hash = '#h'+l;
						},
					},
				});
			chart.resize(Math.max(800, $(ce).closest('.row').width(), g_keys.length*10), 600);
		}

		if (retry) {
			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleGroup);}, 1000);
		}
	}

	function contentLoaded() {
		state.corps = g_corps;
		state.hash = g_hash;
		state.hash_freq = g_hash_freq;
		state.hash_combo = g_hash_combo;

		let params = (new URL(window.location)).searchParams;
		state.focus = params.has('focus') ? params.get('focus') : Defs.focus;
		state.focus_n = fields[state.focus];
		state.offset = params.has('offset') ? parseInt(params.get('offset')) : Defs.offset;
		state.pagesize = params.has('pagesize') ? parseInt(params.get('pagesize')) : Defs.pagesize;

		if (params.has('g')) {
			$('#qhistgroup').val(params.get('g'));
		}

		let rx = /\b(\d+):\[/g;
		let q = params.get('q');
		let n = null;
		while ((n = rx.exec(q)) !== null) {
			state.named.push(n[1]);
		}

		$('#btnRelS').prop('disabled', true).addClass('disabled');

		$('.btnShowSearch').click(function() {
			$('#search').get(0).scrollIntoView(true);
			$('#query').focus();
		});
		$('.btnShowCorpora').click(function() {
			$('.btnShowCorpora').remove();
			$('#corpora').show();
		});
		$('.btnCustomize').hide();

		$('#refine').hide();
		state.refine = $('#refine').html();
		$('.btnRefine').click(function() {
			$('#refine').html(state.refine);
			window.refine.init();
			$('#refine').show().get(0).scrollIntoView(true);
		});

		$('.arrOptVisible').change(changeOption);

		// Concordances
		if ($('.qresults').length) {
			let rq = {
				a: 'conc',
				h: state.hash,
				c: state.context,
				s: state.offset,
				n: state.pagesize,
				rs: [],
				ts: [],
				};

			$('.qresults').each(function() {
				let id = $(this).attr('id');
				rq.rs.push(id);
				rq.ts.push(id);
				if (id.indexOf('-') !== -1) {
					$('#btnRelS').prop('disabled', false).removeClass('disabled');
				}
			});

			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleConc);}, 500);
		}

		// Frequencies
		if ($('.qfreqs').length) {
			let rq = {
				a: 'freq',
				t: params.get('s'),
				h: state.hash,
				hf: state.hash_freq,
				hc: state.hash_combo,
				s: state.offset,
				n: state.pagesize,
				cs: [],
				};

			$('.qfreqs').each(function() {
				let id = $(this).attr('id');
				rq.cs.push(id);
				if (id.indexOf('-') !== -1) {
					$('#btnRelS').prop('disabled', false).removeClass('disabled');
				}
			});

			$('button[name="s"][value="'+rq.t+'"]').addClass('btn-warning');

			$('.btnCustomize').click(function() {
				$('#customize-freq').toggle();
			});
			$('.btnCustomize').show();

			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleFreq);}, 500);
		}

		// Histogram
		if ($('.qhist').length) {
			let rq = {
				a: 'hist',
				h: state.hash,
				g: params.get('g'),
				s: state.offset,
				n: state.pagesize,
				cs: [],
				};

			$('.qhist').each(function() {
				let id = $(this).attr('id');
				rq.cs.push(id);
				if (id.indexOf('-') !== -1) {
					$('#btnRelS').prop('disabled', false).removeClass('disabled');
				}
			});

			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleHist);}, 500);
		}

		// Group by
		if ($('.qgroup').length) {
			let rq = {
				a: 'group',
				h: state.hash,
				gc: params.get('gc'),
				s: state.offset,
				n: state.pagesize,
				cs: [],
				};
			for (let i=0 ; i<10 ; ++i) {
				let g = 'g'+i;
				if (params.has(g)) {
					rq[g] = params.get(g);
				}
			}

			$('.qgroup').each(function() {
				let id = $(this).attr('id');
				rq.cs.push(id);
				if (id.indexOf('-') !== -1) {
					$('#btnRelS').prop('disabled', false).removeClass('disabled');
				}
			});

			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleGroup);}, 500);
		}

		$('#qpagesize').change(function() {
			let n = parseInt($(this).val());
			state.pagesize = n;
			state.offset = Math.floor(state.offset/n)*n + 1;
			repaginate();
			loadOffset(state.offset, true);
		});

		$('#freq_field').change(function() {
			let f = $(this).val();
			$('.btnRel').prop('disabled', true);
			if (/^(h_)?(word|lex)/.test(f)) {
				$('.btnRel').prop('disabled', false);
			}
		}).change();

		$('#qfocus').val(state.focus).change(function() {
			state.focus = $(this).val();
			state.focus_n = fields[state.focus];
			let url = new URL(window.location);
			url.searchParams.set('focus', state.focus);
			if (state.focus = Defs.focus) {
				url.searchParams.delete('focus');
			}
			window.history.pushState({}, '', url);
			handleConc(state.last_rv);
		});

		window.addEventListener('popstate', function(e) {
			let params = (new URL(window.location)).searchParams;
			let focus = params.has('focus') ? params.get('focus') : Defs.focus;
			let offset = params.has('offset') ? parseInt(params.get('offset')) : Defs.offset;
			let pagesize = params.has('pagesize') ? parseInt(params.get('pagesize')) : Defs.pagesize;
			if (focus != state.focus) {
				console.log(focus);
				$('#qfocus').val(focus);
				state.focus = focus;
				state.focus_n = fields[state.focus];
				handleConc(state.last_rv);
			}
			if (offset != state.offset || pagesize != state.pagesize) {
				state.offset = offset;
				state.pagesize = pagesize;
				loadOffset(state.offset, false);
			}
		});

		let popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
		let popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));

		let toastElList = document.querySelectorAll('.toast');
		let toastList = [...toastElList].map(toastEl => {let t = new bootstrap.Toast(toastEl); t.show();});

		loadOptions();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', contentLoaded);
	}
	else {
		contentLoaded();
	}

	// Export useful functions
	return {
		state: state,
		repaginate: repaginate,
		};
}));
