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
		focus: Defs.focus,
		focus_n: Defs.focus_n,
		context: Defs.context,
		pagesize: Defs.pagesize,
		max_n: 0,
		prev_max_n: 0,
		offset: Defs.offset,
		named: [],
		last_rv: null,
		};

	let fields = {};
	let num_fields = 0;
	'word	lex	extra	pos	morph	func	role	dself	dparent	word_lc	word_nd	lex_lc	lex_nd'.split(/\t/).forEach(function(e, i) {
		fields[e] = i;
		num_fields = i + 1;
	});

	function u_length(str) {
		return [...str].length;
	}

	function escHTML(t) {
		if (typeof(t) !== 'string') {
			t = t.toString();
		}
		return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
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
			if (pgs > 11) {
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
	}

	function pageToggleButtons() {
		let url = new URL(window.location);
		url.searchParams.set('pagesize', state.pagesize);

		$('.qpagesel').val(state.offset);
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
			return '<br><span class="fw-light text-muted">'+escHTML(tabs[state.focus_n] ? tabs[state.focus_n] : '-')+'</span>';
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
				let html = '<table class="table table-striped table-hover my-3">';
				//html += '<thead><tr class="text-center"><th>#</th><th>LHS</th><th class="text-center">Hit</th><th>RHS</th></tr></thead>';
				html += '<tbody class="font-monospace text-nowrap text-break">';
				for (let i=0 ; i<rv.rs[corp].length ; ++i) {
					html += '<tr id="'+corp+'-'+rv.rs[corp][i].i+'" data-p="'+rv.rs[corp][i].p+'"><td class="qpending opacity-25">'+rv.rs[corp][i].i+'</td><td class="opacity-25">…loading…</td><td class="text-center fw-bold opacity-25">'+escHTML(rv.rs[corp][i].t)+'</td><td class="opacity-25">…loading…</td></tr>';
					rq.cs[corp][rv.rs[corp][i].i] = 1;
				}
				html += '</tbody></table>';
				c.find('.qbody').html(html);
			}
		}

		if (rv.hasOwnProperty('cs')) {
			for (let corp in rv.cs) {
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

					for (let j=0,n = cntx.b ; j<ts.length ; ++j) {
						if (/^<s /.test(ts[j])) {
							if (n <= p) {
								s_tag = ts[j];
								s_article = ts[j].match(/ tweet="([^"]+)"/)[1];
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
					let good = false;
					let named = Array.from(state.named);
					for (let j=0 ; j<ts.length ; ++j) {
						if (/^<s /.test(ts[j])) {
							if (ts[j].indexOf(' tweet="'+s_article+'"') !== -1) {
								//console.log([cntx.i, j, n, s_tag]);
								good = true;
							}
							else {
								good = false;
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

								let title = escHTML(tabs.join("\n"));
								if (n < p) {
									parts.p.push('<span title="'+title+'" class="d-inline-block text-center">'+escHTML(tabs[0])+appendIfNot0(tabs)+'</span>');
									parts.pz.push(u_length(tabs[0]));
									parts.ptz += u_length(tabs[0]) + 1;
								}
								else if (txt.length && txt.indexOf(tabs[0]) == 0) {
									parts.m.push('<span title="'+title+'" class="d-inline-block text-center">'+escHTML(tabs[0])+appendIfNot0(tabs)+'</span>');
									txt = txt.substr(tabs[0].length).trim();
								}
								else if (named.length && txt.length && txt.indexOf('<'+named[0]+': '+tabs[0]+' >') == 0) {
									parts.m.push('<span title="'+title+'" class="d-inline-block text-center"><span class="fw-light">'+named[0]+':</span>'+escHTML(tabs[0])+appendIfNot0(tabs)+'</span>');
									txt = txt.substr(('<'+named[0]+': '+tabs[0]+' >').length).trim();
									named.shift();
								}
								else {
									parts.s.push('<span title="'+title+'" class="d-inline-block text-center">'+escHTML(tabs[0])+appendIfNot0(tabs)+'</span>');
									parts.sz.push(u_length(tabs[0]));
									parts.stz += u_length(tabs[0]) + 1;
								}
							}
							++n;
						}
					}

					let html = '<td><a href="#"><i class="bi bi-box-arrow-up-right"></i></a> <a href="https://alpha.visl.sdu.dk/social/?t='+s_article+'" target="_tweet"><i class="bi bi-link-45deg"></i></a></td><td class="text-end">';
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
				}

				rq.cs[corp] = {};
				$('#'+corp).find('.qpending').each(function() {
					rq.cs[corp][parseInt($(this).text())] = 1;
				});
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
			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleConc);}, 500);
		}
	}

	function handleFreq(rv) {
		let params = (new URL(window.location)).searchParams;
		let retry = false;
		let rq = {
			a: 'freq',
			t: params.get('s'),
			h: state.hash,
			hf: state.hash_freq,
			s: rv.s,
			n: rv.n,
			cs: [],
			};

		if (rv.hasOwnProperty('cs')) {
			for (let corp in rv.cs) {
				if (!rv.cs[corp].d) {
					rq.cs.push(corp);
					retry = true;
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

				c.find('.qrange').text(rv.s+' to '+Math.min(rv.s + rv.n, rv.cs[corp].n));
				let html = '<table class="table table-striped table-hover my-3">';
				//html += '<thead><tr class="text-center"><th>#</th><th>LHS</th><th class="text-center">Hit</th><th>RHS</th></tr></thead>';
				html += '<tbody class="font-monospace text-nowrap text-break">';
				for (let i=0 ; i<rv.cs[corp].f.length ; ++i) {
					html += '<tr><td>'+escHTML(rv.cs[corp].f[i][0])+'</td><td class="text-end">'+rv.cs[corp].f[i][1]+'</td><td class="text-end">'+(rv.cs[corp].f[i][1] / rv.cs[corp].t * 100).toFixed(1)+'%</td></tr>';
				}
				html += '</tbody></table>';
				c.find('.qbody').html(html);
			}
		}

		if (retry) {
			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleFreq);}, 500);
		}
	}

	function contentLoaded() {
		state.hash = g_hash;
		state.hash_freq = g_hash_freq;

		let params = (new URL(window.location)).searchParams;
		state.focus = params.has('focus') ? params.get('focus') : Defs.focus;
		state.focus_n = fields[state.focus];
		state.offset = params.has('offset') ? parseInt(params.get('offset')) : Defs.offset;
		state.pagesize = params.has('pagesize') ? parseInt(params.get('pagesize')) : Defs.pagesize;

		let rx = /\b(\d+):\[/g;
		let q = params.get('q');
		let n = null;
		while ((n = rx.exec(q)) !== null) {
			state.named.push(n[1]);
		}

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

		// Frequencies
		if ($('.qfreqs').length) {
			let rq = {
				a: 'freq',
				t: params.get('s'),
				h: state.hash,
				hf: state.hash_freq,
				s: state.offset,
				n: state.pagesize,
				cs: [],
				};

			$('.qfreqs').each(function() {
				let id = $(this).attr('id');
				rq.cs.push(id);
			});

			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleFreq);}, 500);
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
		});

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
