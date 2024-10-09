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
		modal_v: null,
		url: null,
		params: null,
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

	function ss_get(key, def) {
		let v = null;
		try {
			v = window.sessionStorage.getItem(key);
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

	function ss_set(key, val) {
		try {
			window.sessionStorage.setItem(key, JSON.stringify(val));
		}
		catch (e) {
		}
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

	function toggleButtons() {
		$('#pos,#btnAbc,.btnRel').prop('disabled', true).addClass('disabled');
		$('#btnAbc').prop('disabled', false).removeClass('disabled');

		if (/^(h_)?(word|lex)/.test($('#freq_field').val())) {
			$('#pos,#btnAbc,.btnRel').prop('disabled', false).removeClass('disabled');
		}
		else {
			$('#pos').prop('checked', false);
		}

		$('#btnRelS').prop('disabled', true).addClass('disabled');
		$('.qcorpus').each(function() {
			let id = $(this).attr('id');
			if (id.indexOf('-') !== -1) {
				$('#btnRelS').prop('disabled', false).removeClass('disabled');
				return;
			}
		});
		if ($('#br').prop('checked')) {
			$('#btnAbc,.btnRel').prop('disabled', true).addClass('disabled');
		}
	}

	function chkCompare() {
		let words = [];
		let q = [];
		let q2 = [];
		$('.chkCompare:checked').slice(0, 10).each(function() {
			let t = $(this);
			words.push(t.attr('data-word'));
			q.push(t.attr('data-q'));
			q2.push(t.attr('data-q2'));
		});
		ss_set('compare-checked-' + state.hash + '-' + state.hash_freq, words);

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

	function vectorDel() {
		$(this).closest('.row').remove();
	}

	function popupVector() {
		let words = [];
		let html = '';
		$('.chkVector:checked').each(function() {
			if (words.length >= 300) {
				return;
			}
			words.push($(this).attr('data-word'));
			html += '<div class="row my-1"><div class="col"><input type="text" class="form-control word" value="'+escHTML($(this).attr('data-word'))+'"></div><div class="col"><input type="text" class="form-control num" value="'+escHTML($(this).attr('data-num'))+'"></div><div class="col-1"><button class="btn btn-sm btn-danger btnVectorDel">X</button></div></div>';
		});
		ss_set('w2v-checked-' + state.hash + '-' + state.hash_freq, words);

		let ws_a = ss_get('w2v-a_words-' + state.hash + '-' + state.hash_freq, []);
		let ns_a = ss_get('w2v-a_freqs-' + state.hash + '-' + state.hash_freq, []);
		for (let i=0 ; i<ws_a.length ; ++i) {
			html += '<div class="row my-1"><div class="col"><input type="text" class="form-control added word" value="'+escHTML(ws_a[i])+'"></div><div class="col"><input type="text" class="form-control added num" value="'+escHTML(ns_a[i])+'"></div><div class="col-1"><button class="btn btn-sm btn-danger btnVectorDel">X</button></div></div>';
		}

		$('#vectorWords').html(html);
		$('.btnVectorDel').off().click(vectorDel);
		$('#modalVector').attr('data-corp', $(this).closest('.qfreqs').attr('id'));
		let axes = ls_get('w2v-axes-'+state.params.get('l'), {});
		for (let ax in axes) {
			if (typeof axes[ax] === 'boolean') {
				$('#'+ax).prop('checked', axes[ax]);
			}
			else {
				$('#'+ax).val(axes[ax]);
			}
		}
		state.modal_v.show();
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

		if ($('.qngrams').length) {
			let rq = {
				a: 'ngrams',
				h: state.hash,
				f: get(url.searchParams, 'f', 'word'),
				s: state.offset,
				n: state.pagesize,
				rs: [],
				ts: [],
				};

			$('.qngrams').each(function() {
				let id = $(this).attr('id');
				rq.rs.push(id);
				rq.ts.push(id);
			});

			$('.qbody > table').addClass('opacity-25');
			$.getJSON('./callback.php', rq).done(handleNgrams);
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

	function parse_query(src) {
		let rv = {
			tokens: [],
			quants: [],
			meta: [],
		};

		src = $.trim(src.replace(/_PLUS_/g, '+').replace(/_HASH_/g, '#').replace(/_AND_/g, '&').replace(/_PCNT_/g, '%'));

		let re_fld = /^([a-z_]+)(!?)=/;
		let re_val = /"([^"]+)"/;

		let meta = null;
		let re_meta = /^\((.+)\) within <s (.+?)\/>$/;
		while ((meta = re_meta.exec(src)) !== null) {
			src = meta[1];
			meta = meta[2];

			let fld = null;
			while ((fld = re_fld.exec(meta)) !== null) {
				let not = fld[2] ? true : false;
				fld = fld[1];
				//console.log(fld);
				meta = meta.substr(fld.length + not*1 + 1);
				//console.log(meta);

				let val = re_val.exec(meta);
				//console.log(val);
				if (!val) {
					break;
				}

				meta = $.trim(meta.substr(val[0].length));
				//console.log(meta);
				rv.meta.push({k: fld, i: not, v: val[0]});

				if (meta.indexOf('& ') === 0) {
					meta = $.trim(meta.substr(2));
				}
				//console.log(val);
				//console.log(meta);
			}
		}

		if (src.charAt(0) !== '[') {
			src = '['+src+']';
		}

		while (src.charAt(0) === '[') {
			let token = [];
			src = src.substr(1);

			let fld = null;
			while ((fld = re_fld.exec(src)) !== null) {
				let not = fld[2] ? true : false;
				fld = fld[1];
				//console.log(fld);
				src = src.substr(fld.length + not*1 + 1);
				//console.log(src);

				let val = re_val.exec(src);
				//console.log(val);
				if (!val) {
					break;
				}

				src = $.trim(src.substr(val[0].length));
				token.push({k: fld, i: not, v: val[0]});

				if (src.indexOf('& ') === 0) {
					src = $.trim(src.substr(2));
				}
				//console.log(val);
				//console.log(src);
			}

			if (src.indexOf(']') === 0) {
				src = $.trim(src.substr(1));
			}
			if (src.length && src.charAt(0) != '[') {
				rv.quants.push(src.charAt(0));
				src = $.trim(src.substr(1));
			}
			else {
				rv.quants.push('');
			}

			rv.tokens.push(token);
		}

		//console.log(rv);
		return rv;
	}

	function render_query(q) {
		let rv = '';
		for (let i=0 ; i<q.tokens.length ; ++i) {
			let fields = q.tokens[i];
			rv += '[';
			for (let j=0 ; j<fields.length ; ++j) {
				let field = fields[j];
				rv += field.k;
				if (field.i) {
					rv += '!';
				}
				rv += '=';
				rv += field.v;
				rv += ' & ';
			}
			rv = rv.replace(/ & $/, '');
			rv += '] ';
		}
		rv = rv.replace(/ $/, '');

		if (q.meta.length) {
			rv = '(' + rv + ') within <s ';
			for (let j=0 ; j<q.meta.length ; ++j) {
				let field = q.meta[j];
				rv += field.k;
				if (field.i) {
					rv += '!';
				}
				rv += '=';
				rv += field.v;
				rv += ' & ';
			}
			rv = rv.replace(/ & $/, '');
			rv += '/>';
		}

		return rv;
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

	function handleNgrams(rv) {
		state.last_rv = rv;
		let retry = false;
		let rq = {
			a: 'ngrams',
			h: state.hash,
			f: rv.f,
			s: rv.s,
			n: rv.n,
			rs: [],
			ts: [],
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

				let c = $('#'+corp);
				if (!rv.rs[corp].length) {
					c.find('.qrange').text('…');
					c.find('.qbody').html('<span class="fw-bold">No more hits.</span>');
					continue;
				}

				let tsv = 'Text\tCount\n';

				c.find('.qrange').text(rv.rs[corp][0].i+' to '+(rv.rs[corp][rv.rs[corp].length-1].i));
				let html = '';
				html += '';
				html += '<button class="btn btn-outline-success my-3 btnGetTSV">Download TSV <i class="bi bi-download"></i></button><table class="table table-striped table-hover my-3">';
				html += '<thead><tr class="text-begin"><th>Text</th><th class="text-end">Count</th></tr></thead>';
				html += '<tbody class="font-monospace text-nowrap text-break">';
				for (let i=0 ; i<rv.rs[corp].length ; ++i) {
					html += '<tr id="'+corp+'-'+rv.rs[corp][i].i+'"><td>'+escHTML(rv.rs[corp][i].t)+'</td><td class="text-end">'+rv.rs[corp][i].c+'</td></tr>';
					tsv += rv.rs[corp][i].t + '\t' + rv.rs[corp][i].c + '\n';
				}
				state.tsv = tsv;
				html += '</tbody></table><button class="btn btn-outline-success my-3 btnGetTSV">Download TSV <i class="bi bi-download"></i></button>';
				c.find('.qbody').html(html);

				c.find('.btnGetTSV').click(function() {
					saveAs(new Blob([state.tsv], {type: 'text/tab-separated-values'}), 'ngrams.tsv');
				});
			}
		}

		if (retry) {
			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleNgrams);}, 1000);
		}
	}

	function handleFreq(rv) {
		let url = new URL(window.location);
		let params = url.searchParams;
		let retry = false;
		let rq = {
			a: 'freq',
			t: params.get('s'),
			br: get(params, 'br', 0),
			h: state.hash,
			hf: state.hash_freq,
			hc: state.hash_combo,
			s: rv.s,
			n: rv.n,
			cs: [],
			};

		let field = params.get('f');
		let wv = (get(params, 'wv', '') === 'on');
		if (wv && field != 'lex' && field != 'h_lex') {
			field = 'lex';
			$('#freq_field').val(field);
		}

		let query1 = get(params, 'q', '');
		let query2 = get(params, 'q2', '');
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
			if (get(params, 'nd', false)) {
				hfield += '_nd';
			}
			else if (get(params, 'lc', false)) {
				hfield += '_lc';
			}
		}
		if (rq.br) {
			hfield = get(params, 'br_f', 'word');
		}

		let bits = parse_query(query);
		// Turn right edge conditions into left edge conditions
		if (by === 're') {
			offset += bits.tokens.length - 1;
		}
		// Create query template to be filled with the found token
		if (offset < 0) {
			bits.tokens.unshift([{k: hfield, i: false, v: '"{TOKEN}"'}]);
			for (let i=offset ; i<-1 ; ++i) {
				bits.tokens.unshift([]);
			}
		}
		else if (offset >= bits.tokens.length) {
			for (let i=bits.tokens.length ; i<offset ; ++i) {
				bits.tokens.push([]);
			}
			bits.tokens.push([{k: hfield, i: false, v: '"{TOKEN}"'}]);
		}
		else {
			let found = false;
			for (let i=0 ; i<bits.tokens[offset].length ; ++i) {
				if (bits.tokens[offset][i].k === field && !bits.tokens[offset][i].i) {
					bits.tokens[offset][i].k = hfield;
					bits.tokens[offset][i].v = '"{TOKEN}"';
					found = true;
					break;
				}
			}
			if (!found) {
				bits.tokens[offset].push({k: hfield, i: false, v: '"{TOKEN}"'});
			}
		}

		let search = render_query(bits);

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
				if (get(params, 'ub', false)) {
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

				if (wv) {
					c.find('.qtsv').append('<button class="btn btn-outline-success btnPopupVector">Vector plot <i class="bi bi-pin-map"></i></button>');
					$('.btnPopupVector').click(popupVector);
				}

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
				// '→' is U+2192 Rightwards Arrow
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
				if (wv) {
					html += '<th class="text-vertical" title="Add to vector plot">Vector?</th>';
				}
				else if (has_group) {
					html += '<th class="text-vertical" title="Add to compare">Compare?</th>';
				}
				html += '</tr></thead>';
				html += '<tbody class="font-monospace text-nowrap text-break">';
				for (let i=0 ; i<rv.cs[corp].f.length ; ++i) {
					let rpl = escapeRegExp(rv.cs[corp].f[i][0]);
					if (wv && rv.cs[corp].f[i][0].indexOf('\t') !== -1) {
						let ts = rv.cs[corp].f[i][0].split('\t');
						rpl = escapeRegExp(ts[0]) + '" & pos="' + escapeRegExp(ts[1]);
					}
					if (rq.br) {
						let m = rv.cs[corp].f[i][0].match(/^(.*) →/);
						rpl = '.*(?:^| )'+m[1]+'(?: |$).*';
					}
					url.searchParams.set('q', search1.replace('{TOKEN}', rpl));
					url.searchParams.set('q2', search2.replace('{TOKEN}', rpl));
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
					if (wv) {
						html += '<td><input type="checkbox" class="form-check-input chkVector" data-word="'+escHTML(rv.cs[corp].f[i][0].replace(/\t/g, '_'))+'" data-num="'+rv.cs[corp].f[i][1]+'"></td>';
					}
					else if (has_group) {
						html += '<td><input type="checkbox" class="form-check-input chkCompare" data-word="'+escHTML(rv.cs[corp].f[i][0])+'" data-q="'+escHTML(url.searchParams.get('q'))+'" data-q2="'+escHTML(url.searchParams.get('q2'))+'"></td>';
					}
					html += '</tr>';
				}
				html += '</tbody></table>';
				c.find('.qbody').html(html);

				c.find('.chkCompare').off().click(chkCompare);
				let comps = ss_get('compare-checked-' + state.hash + '-' + state.hash_freq, []);
				if (comps.length) {
					for (let c=0 ; c<comps.length ; ++c) {
						$('.chkCompare[data-word="'+comps[c]+'"]').click();
					}
				}
				else {
					c.find('.chkCompare').slice(0,10).click();
				}

				let vecs = ss_get('w2v-checked-' + state.hash + '-' + state.hash_freq, []);
				if (vecs.length) {
					for (let v=0 ; v<vecs.length ; ++v) {
						$('.chkVector[data-word="'+vecs[v]+'"]').prop('checked', true);
					}
				}
				else {
					$('.chkVector').prop('checked', true);
				}
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
			ga: get(params, 'ga', 's'),
			};
		let gs = [];
		for (let i=0 ; i<10 ; ++i) {
			let g = 'g'+i;
			if (params.has(g)) {
				rq[g] = params.get(g);
				gs.push(rq[g]);
			}
		}

		let comp = get(params, 'ga', 's');

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
			let suf = q.length - q_suffix.length;
			while (/[-_A-Za-z0-9\w\d]/.test(q[suf])) {
				++suf;
			}
			q = q.substr(0, suf);
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

				let tsv = 'Group\tArticles\tSentences\tHits\tWords\tCArticles\tCSentences\tCTokens\tCWords\n';

				c.find('.qrange').text('');
				let html = '<button class="btn btn-outline-success my-3 btnGetTSV">Download TSV <i class="bi bi-download"></i></button><table class="d-inline-block table table-striped table-hover my-3">';
				html += '<thead><tr><th>Group</th><th class="text-vertical">Article hits</th><th class="text-vertical">Sentence hits</th><th class="text-vertical">All hits</th><th class="text-vertical">Unique lex_POS</th><th class="text-vertical">% Articles</th><th class="text-vertical">% Sentences</th><th class="text-vertical">% Hits/CSentences</th><th class="text-vertical">Hits/CWords/10k</th><th class="text-vertical text-muted">C Articles</th><th class="text-vertical text-muted">C Sentences</th><th class="text-vertical text-muted">C Words</th></tr></thead>';
				html += '<tbody class="font-monospace text-nowrap text-break">';
				for (let hk = 0 ; hk<arr_hash.length ; ++hk) {
					if (display_legend) {
						html += '<tr><th colspan="12">'+escHTML(q_show[hk])+'</th></tr>';
					}
					tsv += '# '+arr_q[hk]+'\n';
					/*
						0 => text
						1 => article hits
						2 => sentence hits
						3 => hits (tokens)
						4 => words (alnum)
						5 => unique lex_POS
						6 => corpus articles
						7 => corpus sentences
						8 => corpus hits (tokens)
						9 => corpus words (alnum)
						10 => corpus uniq lex_POS
					*/
					for (let i=0 ; i<rv.cs[corp][hk].h.length ; ++i) {
						if (rv.cs[corp][hk].h[i][1] <= 0) {
							continue;
						}
						rv.cs[corp][hk].h[i][0] = rv.cs[corp][hk].h[i][0].toString();

						let attrs = rv.cs[corp][hk].h[i][0].split(' |~| ');
						for (let a=0 ; a<attrs.length ; ++a) {
							attrs[a] = gs[a]+'="'+escHTML(attrs[a])+'"';
						}
						url.searchParams.set('q', '('+arr_q[hk]+') within <s '+attrs.join(' & ')+'/>');
						if (arr_q2[hk]) {
							url.searchParams.set('q2', arr_q2[hk]);
						}
						rv.cs[corp][hk].h[i][0] = rv.cs[corp][hk].h[i][0].replace(' |~| ', '; ');
						html += '<tr id="h'+escHTML(rv.cs[corp][hk].h[i][0])+'"><td><a href="'+escHTML(url.toString())+'">'+escHTML(rv.cs[corp][hk].h[i][0])+'</a></td><td class="text-end">'+rv.cs[corp][hk].h[i][1]+'</td><td class="text-end">'+rv.cs[corp][hk].h[i][2]+'</td><td class="text-end">'+rv.cs[corp][hk].h[i][3]+'</td><td class="text-end">'+rv.cs[corp][hk].h[i][5]+'</td><td class="text-end">'+(rv.cs[corp][hk].h[i][1]*100.0 / rv.cs[corp][hk].h[i][6]).toFixed(2)+'%</td><td class="text-end">'+(rv.cs[corp][hk].h[i][2]*100.0 / rv.cs[corp][hk].h[i][7]).toFixed(2)+'%</td><td class="text-end">'+(rv.cs[corp][hk].h[i][3]*100.0 / rv.cs[corp][hk].h[i][7]).toFixed(2)+'%</td><td class="text-end">'+(rv.cs[corp][hk].h[i][3] / (rv.cs[corp][hk].h[i][9]/10000)).toFixed(2)+'</td><td class="text-end text-muted">'+rv.cs[corp][hk].h[i][6]+'</td><td class="text-end text-muted">'+rv.cs[corp][hk].h[i][7]+'</td><td class="text-end text-muted">'+rv.cs[corp][hk].h[i][9]+'</td></tr>';
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
		let f_aggr = 7;
		let f_factor = 1;
		let f_label = '% Hits/CSentences';
		if (comp === 'a') {
			f_hits = 1;
			f_aggr = 6;
			f_label = '% Articles/CArticles';
		}
		if (comp === 'w') {
			f_aggr = 9;
			f_factor = 100;
			f_label = 'Hits/CWords/10k';
		}
		if (comp === 'tt') {
			f_hits = 5;
			f_aggr = 3;
			f_label = 'Type/Token';
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
					if (comp === 'tt') {
						trc[k][6] = (Math.log(trc[k][f_aggr]) - Math.log(trc[k][f_hits])) / (Math.log(trc[k][f_aggr])*Math.log(trc[k][f_aggr]));
					}
					else {
						trc[k][6] = (trc[k][f_hits]*100.0 / trc[k][f_aggr]) * f_factor;
					}
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

	function get(params, k, df) {
		if (!params.has(k)) {
			return df;
		}
		return params.get(k);
	}

	function handleWordvec() {
		$('.qpages').text('');

		for (let corp in g_wv) {
			let vs = g_wv[corp].data;
			let axes = g_wv[corp].axes;
			let e = $('#wv-'+corp);

			let w = Math.max(window.innerWidth - 300, 800);
			let h = Math.max(window.innerHeight - 100, 600);

			e.find('.qbody').html('<div><canvas id="chart-'+corp+'" style="width: '+w+'px; height: '+h+'px;"></canvas></div><div><a class="btn btn-outline-primary btnZoomReset">Reset zoom</a></div><div id="data-'+corp+'"></div>');

			let params = (new URL(window.location)).searchParams;
			let x1 = get(params, 'x1', '').replace(/ /g, '=').split(';');
			let x2 = get(params, 'x2', '').replace(/ /g, '=').split(';');
			let y1 = get(params, 'y1', '').replace(/ /g, '=').split(';');
			let y2 = get(params, 'y2', '').replace(/ /g, '=').split(';');
			let ws = get(params, 'ws', '').replace(/ /g, '=').split('~');
			let ns = get(params, 'ns', null).split('~');

			let url_x = new URL(window.location.origin + window.location.pathname);
			url_x.searchParams.set('l', params.get('l'));
			url_x.searchParams.set('c['+corp+']', '1');
			url_x.searchParams.set('s', 's');
			let url_y = new URL(url_x);

			let ls = {};
			let ps = {};
			[x1, x2].forEach(function(arr) {
				for (let i=0 ; i<arr.length ; ++i) {
					let m = arr[i].match(/^(.+?)_([^_]+)$/);
					if (m) {
						ls[m[1]] = true;
						ps[m[2]] = true;
					}
					else {
						ls[arr[i]] = true;
						ps['.*'] = true;
					}
				}
			});
			url_x.searchParams.set('q', '[lex="'+Object.keys(ls).join('|')+'" & pos="'+Object.keys(ps).join('|')+'"]');

			ls = {};
			ps = {};
			[y1, y2].forEach(function(arr) {
				for (let i=0 ; i<arr.length ; ++i) {
					let m = arr[i].match(/^(.+?)_([^_]+)$/);
					if (m) {
						ls[m[1]] = true;
						ps[m[2]] = true;
					}
					else {
						ls[arr[i]] = true;
						ps['.*'] = true;
					}
				}
			});
			url_y.searchParams.set('q', '[lex="'+Object.keys(ls).join('|')+'" & pos="'+Object.keys(ps).join('|')+'"]');

			let ths = '';
			if (ns) {
				ths += '<th>Num</th>';
			}
			let html = '<table class="table table-striped"><thead><tr><th>Word</th>'+ths+'<th>X</th><th>Y</th></tr></thead><tbody>';

			let labels = [];
			let annots = {};
			let colors = {
				vals: [],
				min: 99,
				max: -99,
				bg: [],
				border: [],
				};
			let data = [];
			let mean = {
				x: 0,
				y: 0,
				};
			let wmean = {
				x: 0,
				y: 0,
				n: 0,
				};
			let xs = [];
			let ys = [];
			let wxs = [];
			let wys = [];
			let wns = [];
			for (let i=0 ; i<ws.length ; ++i) {
				let w = ws[i];

				let row = '<tr id="'+corp+'-'+escHTML(w)+'"><td>'+escHTML(w)+'</td>';
				if (ns) {
					row += '<td>'+ns[i]+'</td>';
				}
				let good = true;
				for (let k in vs) {
					if (!vs[k].hasOwnProperty(w) || vs[k][w] === 0) {
						good = false;
					}
				}
				if (!good) {
					row += '<td>~</td><td>~</td></tr>';
					html += row;
					continue;
				}

				let x_a = 0;
				for (let j=0 ; j<axes.x1.length ; ++j) {
					x_a += vs[axes.x1[j]][w];
				}
				x_a /= axes.x1.length;

				let x_b = 0;
				for (let j=0 ; j<axes.x2.length ; ++j) {
					x_b += vs[axes.x2[j]][w];
				}
				x_b /= axes.x2.length;

				let y_a = 0;
				for (let j=0 ; j<axes.y1.length ; ++j) {
					y_a += vs[axes.y1[j]][w];
				}
				y_a /= axes.y1.length;

				let y_b = 0;
				for (let j=0 ; j<axes.y2.length ; ++j) {
					y_b += vs[axes.y2[j]][w];
				}
				y_b /= axes.y2.length;

				let x = x_b - x_a;
				let y = y_b - y_a;

				let m = w.match(/^(.+?)_([^_]+)$/);
				url_x.searchParams.set('q2', '[lex="'+m[1]+'" & pos="'+m[2]+'"]');
				url_y.searchParams.set('q2', '[lex="'+m[1]+'" & pos="'+m[2]+'"]');

				row += '<td><a href="'+escHTML(url_x.toString())+'">'+(Math.round(x*10000)/10000)+'</a></td><td><a href="'+escHTML(url_y.toString())+'">'+(Math.round(y*10000)/10000)+'</a></td></tr>';
				html += row;

				colors.vals.push(Math.max(x_a, x_b, y_a, y_b));
				colors.min = Math.min(colors.min, x_a, x_b, y_a, y_b);
				colors.max = Math.max(colors.max, x_a, x_b, y_a, y_b);
				labels.push(w);
				data.push({x: x, y: y});
				mean.x += x;
				mean.y += y;
				xs.push(x);
				ys.push(y);
				if (ns) {
					let n = parseInt(ns[i]);
					wmean.n += n;
					wmean.x += x * n;
					wmean.y += y * n;
					wxs.push([n, x]);
					wys.push([n, y]);
					wns.push(n);
				}

				annots['l'+i] = {
					type: 'label',
					xValue: x,
					yValue: y,
					position: {x: 'start', y: 'center'},
					content: [w.replace(/_[A-Z]+$/, '').replace(/=/g, '_')],
					};
			}

			colors.max -= colors.min;
			for (let j=0 ; j<colors.vals.length ; ++j) {
				let v = (colors.vals[j] - colors.min) / colors.max;
				let r = Math.floor(56 + (199 * v)); // 56-255
				let g = Math.floor(162 + (-63 * v)); // 162-99
				let b = Math.floor(233 + (-102 * v)); // 233-131
				colors.bg.push('rgb('+r+', '+g+', '+b+')');
				colors.border.push('rgb('+r+', '+g+', '+b+')');
			}

			let radia = [];
			let means = [];
			let medians = [];
			if (ns) {
				for (let j=0 ; j<wns.length ; ++j) {
					let w = wns[j] / wmean.n;
					radia.push(3+50*w);
				}
				wxs.sort(function(a,b) {return a[1] - b[1];});
				wys.sort(function(a,b) {return a[1] - b[1];});

				wmean.x /= wmean.n;
				wmean.y /= wmean.n;
				annots['wmean'] = {
					type: 'label',
					xValue: wmean.x,
					yValue: wmean.y,
					position: {x: 'start', y: 'center'},
					content: ['', '', '(w-mean)'],
					z: -1,
					};
				annots['wmeanx'] = {
					type: 'line',
					xMin: wmean.x,
					xMax: wmean.x,
					borderColor: 'rgb(255, 99, 131, 0.75)',
					borderWidth: 1,
					z: -10,
					};
				annots['wmeany'] = {
					type: 'line',
					yMin: wmean.y,
					yMax: wmean.y,
					borderColor: 'rgb(255, 99, 131, 0.75)',
					borderWidth: 1,
					z: -10,
					};
				data.push(wmean);
				radia.push(3);
				colors.bg.push('rgb(127, 127, 127)');
				colors.border.push('rgb(255, 255, 255)');

				html += '<tr><td>(w mean)</td><td>~</td><td>'+(Math.round(wmean.x*10000)/10000)+'</td><td>'+(Math.round(wmean.y*10000)/10000)+'</td></tr>';

				let wmedian = {
					x: 0,
					y: 0,
					};
				if (wxs.length & 1) {
					let sum = 0;
					for (let j=0 ; j<wxs.length ; ++j) {
						sum += wxs[j][0];
						if (sum >= wmean.n/2) {
							wmedian.x = wxs[j][1];
							break;
						}
					}

					sum = 0;
					for (let j=0 ; j<wys.length ; ++j) {
						sum += wys[j][0];
						if (sum >= wmean.n/2) {
							wmedian.y = wys[j][1];
							break;
						}
					}
				}
				else {
					let sum = 0;
					for (let j=0 ; j<wxs.length ; ++j) {
						sum += wxs[j][0];
						if (sum >= wmean.n/2) {
							wmedian.x = wxs[j][1];
							break;
						}
					}

					sum = 0;
					for (let j=wxs.length ; j>0 ; --j) {
						sum += wxs[j-1][0];
						if (sum >= wmean.n/2) {
							wmedian.x += wxs[j-1][1];
							break;
						}
					}

					sum = 0;
					for (let j=0 ; j<wys.length ; ++j) {
						sum += wys[j][0];
						if (sum >= wmean.n/2) {
							wmedian.y = wys[j][1];
							break;
						}
					}

					sum = 0;
					for (let j=wys.length ; j>0 ; --j) {
						sum += wys[j-1][0];
						if (sum >= wmean.n/2) {
							wmedian.y += wys[j-1][1];
							break;
						}
					}

					wmedian.x /= 2;
					wmedian.y /= 2;
				}
				annots['wmedian'] = {
					type: 'label',
					xValue: wmedian.x,
					yValue: wmedian.y,
					position: {x: 'start', y: 'center'},
					content: ['', '', '(w-median)'],
					z: -1,
					};
				annots['wmedianx'] = {
					type: 'line',
					xMin: wmedian.x,
					xMax: wmedian.x,
					borderColor: 'rgb(254, 158, 66, 0.75)',
					borderWidth: 1,
					z: -10,
					};
				annots['wmediany'] = {
					type: 'line',
					yMin: wmedian.y,
					yMax: wmedian.y,
					borderColor: 'rgb(254, 158, 66, 0.75)',
					borderWidth: 1,
					z: -10,
					};
				data.push(wmedian);
				radia.push(3);
				colors.bg.push('rgb(127, 127, 127)');
				colors.border.push('rgb(255, 255, 255)');

				html += '<tr><td>(w median)</td><td>~</td><td>'+(Math.round(wmedian.x*10000)/10000)+'</td><td>'+(Math.round(wmedian.y*10000)/10000)+'</td></tr>';
			}

			html += '</tbody></table>';
			['x1', 'x2', 'y1', 'y2'].forEach(function(e) {
				html += '<table class="table table-striped"><thead><tr><th>'+e.toUpperCase()+'</th></tr></thead><tbody>';
				for (let ei = 0 ; ei<axes[e].length ; ++ei) {
					html += '<tr><td>'+escHTML(axes[e][ei].replace(/=/g, ' '))+'</td></tr>';
				}
				html += '</tbody></table>';
			});
			$('#data-'+corp).html(html);

			let ce = document.getElementById('chart-'+corp);
			let chart = new Chart(ce,
				{
					type: 'scatter',
					data: {
						labels: labels,
						datasets: [{
							label: '',
							data: data,
							radius: radia,
							backgroundColor: colors.bg,
							borderColor: colors.border,
						}],
					},
					options: {
						responsive: true,
						aspectRatio: 1,
						maintainAspectRatio: false,
						interaction: {
							//mode: 'index',
							intersect: false,
						},
						plugins: {
							legend: {
								display: false,
							},
							annotation: {
								annotations: annots,
							},
							zoom: {
								pan: {
									enabled: true,
								},
								zoom: {
									wheel: {
										enabled: true,
									},
									pinch: {
										enabled: true,
									},
									mode: 'xy',
								},
							},
						},
						scales: {
							x: {
								type: 'linear',
								title: {
									display: true,
									text: x1.join(';')+' - '+x2.join(';'),
								}
							},
							y: {
								type: 'linear',
								title: {
									display: true,
									text: y1.join(';')+' - '+y2.join(';'),
								}
							},
						},
						onClick: function(e) {
							let pos = Chart.helpers.getRelativePosition(e, chart);
							let x = chart.scales.x.getValueForPixel(pos.x);
							let l = chart.scales.x.getLabelForValue(x);
							let y = chart.scales.y.getValueForPixel(pos.y);
							let h = chart.scales.y.getLabelForValue(y);
							console.log(e, pos, x, l, y, h);
						},
					},
				});
			e.find('.btnZoomReset').click(function() {
				chart.resetZoom();
			});
		}
	}

	function contentLoaded() {
		state.corps = g_corps;
		state.hash = g_hash;
		state.hash_freq = g_hash_freq;
		state.hash_combo = g_hash_combo;

		state.url = new URL(window.location);
		state.params = state.url.searchParams;
		let params = state.params;
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

		$('#br').change(function() {
			$('.bracket').removeClass('show');
			if ($(this).prop('checked')) {
				$('.bracket').addClass('show');
			}
			toggleButtons();
		}).change();

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
			});

			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleConc);}, 500);
		}

		// N-grams
		if ($('.qngrams').length) {
			let rq = {
				a: 'ngrams',
				h: state.hash,
				f: get(params, 'f', 'word'),
				s: state.offset,
				n: state.pagesize,
				rs: [],
				ts: [],
				};

			$('.qngrams').each(function() {
				let id = $(this).attr('id');
				rq.rs.push(id);
				rq.ts.push(id);
			});

			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleNgrams);}, 500);
		}

		// Frequencies
		if ($('.qfreqs').length) {
			let rq = {
				a: 'freq',
				t: params.get('s'),
				br: get(params, 'br', 0),
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
				ga: get(params, 'ga', 's'),
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
			});

			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleGroup);}, 500);
		}

		// Group by
		if ($('.qwordvec').length) {
			setTimeout(handleWordvec, 500);
		}

		$('#qpagesize').change(function() {
			let n = parseInt($(this).val());
			state.pagesize = n;
			state.offset = Math.floor(state.offset/n)*n + 1;
			repaginate();
			loadOffset(state.offset, true);
		});

		$('#freq_field').change(toggleButtons);

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

		state.modal_v = new bootstrap.Modal('#modalVector');
		$('.btnVectorAdd').click(function() {
			$('#vectorNew').removeClass('is-invalid');
			let w = $.trim($('#vectorNew').val());
			if (!w) {
				return false;
			}
			if (!/^(.+?)_([A-Z]+)$/.test(w)) {
				$('#vectorNew').addClass('is-invalid');
				return false;
			}
			$('#vectorWords').append('<div class="row my-1"><div class="col"><input type="text" class="form-control added word" value="'+escHTML(w)+'"></div><div class="col"><input type="text" class="form-control added num" value="1"></div><div class="col-1"><button class="btn btn-sm btn-danger btnVectorDel">X</button></div></div>');
			$('.btnVectorDel').off().click(vectorDel);
			$('#vectorNew').val('').focus();
		});
		$('#vectorForm').submit(function(e) {
			e.preventDefault();
			$('.btnVectorAdd').click();
			return false;
		});
		$('.btnVectorPlot').click(function() {
			let corp = $('#modalVector').attr('data-corp');
			let ws = [];
			let ns = [];
			let ws_a = [];
			let ns_a = [];
			$('#vectorWords').find('.word').each(function() {
				if (ws.length >= 300) {
					return false;
				}
				let w = $.trim($(this).val());
				ws.push(w);
				if ($(this).hasClass('added')) {
					ws_a.push(w);
				}
			});
			ws = ws.join('~');
			$('#vectorWords').find('.num').each(function() {
				if (ns.length >= 300) {
					return false;
				}
				let n = parseInt($(this).val());
				n = (n > 0) ? n : 1;
				ns.push(n);
				if ($(this).hasClass('added')) {
					ns_a.push(n);
				}
			});
			ns = ns.join('~');

			ss_set('w2v-a_words-' + state.hash + '-' + state.hash_freq, ws_a);
			ss_set('w2v-a_freqs-' + state.hash + '-' + state.hash_freq, ns_a);

			let lang = state.params.get('l');
			let axes = {
				x1: $.trim($('#x1').val()),
				x2: $.trim($('#x2').val()),
				y1: $.trim($('#y1').val()),
				y2: $.trim($('#y2').val()),
			};
			ls_set('w2v-axes-'+lang, axes);

			let url = new URL(window.location.origin + window.location.pathname);
			url.searchParams.set('l', lang);
			url.searchParams.set('q', state.params.get('q'));
			url.searchParams.set('q2', state.params.get('q2'));
			url.searchParams.set('s', 'wv');
			url.searchParams.set('c['+corp+']', '1');
			url.searchParams.set('x1', axes.x1);
			url.searchParams.set('x2', axes.x2);
			url.searchParams.set('y1', axes.y1);
			url.searchParams.set('y2', axes.y2);
			url.searchParams.set('ws', ws);
			url.searchParams.set('ns', ns);
			window.location = url;
		});

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
		parse_query: parse_query,
		render_query: render_query,
		repaginate: repaginate,
		state: state,
		};
}));
