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

	let state = {
		h: '',
		rs: {},
		ts: {},
		cs: {},
		context: 15,
		pagesize: 40,
		max_n: 0,
		offset: 1,
		};

	function u_length(str) {
		return [...str].length;
	}

	function escHTML(t) {
		return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');
	}

	function repaginate() {
		let html = '<ul class="pagination">';
		let pgs = Math.ceil(state.max_n / state.pagesize);
		html += '<li class="page-item qpage-prev"><a href="#" class="page-link qpage" data-which="'+Math.max(state.offset - state.pagesize, 1)+'">&laquo;</a></li>';

		if (pgs > 10) {
			html += '<li class="page-item"><span class="page-link"><select class="form-select form-select-sm qpagesel">';
			for (let i = 0 ; i<pgs ; ++i) {
				html += '<option value="'+(i*state.pagesize+1)+'">'+(i+1)+'</option>';
			}
			html += '</select></span></li>';
		}
		else {
			for (let i = 0 ; i<pgs ; ++i) {
				html += '<li class="page-item"><a href="#" class="page-link qpage" data-which="'+((i*state.pagesize+1))+'">'+(i+1)+'</a></li>';
			}
		}
		html += '<li class="page-item qpage-next"><a href="#" class="page-link qpage" data-which="'+(state.offset + state.pagesize)+'">&raquo;</a></li>';
		html += '</ul>';
		$('#qpages').html(html);

		pageToggleButtons();

		$('.qpage').click(function(e) {
			e.preventDefault();

			let p = $(this).attr('data-which');
			if (!p) {
				p = $(this).text();
			}

			loadOffset(parseInt(p));

			return false;
		});
		$('.qpagesel').change(function() {
			let p = $(this).val();
			loadOffset(parseInt(p));
		});
	}

	function pageToggleButtons() {
		$('.qpagesel').val(state.offset);
		$('.qpage-prev').find('.qpage').attr('data-which', Math.max(state.offset - state.pagesize, 1));
		$('.qpage-next').find('.qpage').attr('data-which', state.offset + state.pagesize);

		$('#qpages').find('.page-item').removeClass('disabled');
		$('#qpages').find('.qpage[data-which="'+state.offset+'"]').parent().addClass('disabled');

		if (state.offset == 1) {
			$('.qpage-prev').addClass('disabled');
		}
		if (state.offset >= state.max_n - state.pagesize) {
			$('.qpage-next').addClass('disabled');
		}
	}

	function loadOffset(p) {
		state.offset = p;

		pageToggleButtons();

		let rq = {
			a: 'load',
			h: state.hash,
			c: state.context,
			rs: {},
			ts: [],
			};

		$('.qresults').each(function() {
			let id = $(this).attr('id');
			rq.rs[id] = {s: state.offset, n: state.pagesize, c: state.context};
			rq.ts.push(id);
		});

		$('.qbody > table').addClass('opacity-25');
		$.getJSON('./callback.php', rq).done(handleLoad);
	}

	function handleLoad(rv) {
		let retry = false;
		let rq = {
			a: 'load',
			h: rv.h,
			c: rv.c,
			rs: {},
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

		if (parseInt($('#qpages').attr('data-max')) < state.max_n) {
			repaginate();
			$('#qpages').attr('data-max', state.max_n);
		}

		if (rv.hasOwnProperty('rs')) {
			for (let corp in rv.rs) {
				if (!rv.rs[corp].es.length && (rv.rs[corp].s < state.ts[corp].n || !state.ts[corp].n)) {
					if (!state.ts[corp].n && state.ts[corp].d) {
						continue;
					}
					rq.rs[corp] = {
						s: rv.rs[corp].s,
						n: state.pagesize,
						};
					retry = true;
					continue;
				}
				state.rs[corp] = rv.rs[corp];
				rq.cs[corp] = {};

				let c = $('#'+corp);
				if (!rv.rs[corp].es.length) {
					c.find('.qrange').text('…');
					c.find('.qbody').html('<span class="fw-bold">No more hits.</span>');
					continue;
				}

				c.find('.qrange').text(rv.rs[corp].es[0].i+' to '+(rv.rs[corp].es[rv.rs[corp].es.length-1].i));
				let html = '<table class="table table-striped table-hover my-3">';
				//html += '<thead><tr class="text-center"><th>#</th><th>LHS</th><th class="text-center">Hit</th><th>RHS</th></tr></thead>';
				html += '<tbody class="font-monospace text-nowrap text-break">';
				for (let i=0 ; i<rv.rs[corp].es.length ; ++i) {
					html += '<tr id="'+corp+'-'+rv.rs[corp].es[i].i+'" data-p="'+rv.rs[corp].es[i].p+'"><td class="qpending">'+rv.rs[corp].es[i].i+'</td><td>…</td><td class="text-center fw-bold">'+escHTML(rv.rs[corp].es[i].t)+'</td><td>…</td></tr>';
					rq.cs[corp][rv.rs[corp].es[i].i] = 1;
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
								let title = escHTML(tabs.join("\n"));
								if (n < p) {
									parts.p.push('<span title="'+title+'">'+escHTML(tabs[0])+'</span>');
									parts.pz.push(u_length(tabs[0]));
									parts.ptz += u_length(tabs[0]) + 1;
								}
								else if (txt.length && txt.indexOf(tabs[0]) == 0) {
									parts.m.push('<span title="'+title+'">'+escHTML(tabs[0])+'</span>');
									txt = txt.substr(tabs[0].length).trim();
								}
								else {
									parts.s.push('<span title="'+title+'">'+escHTML(tabs[0])+'</span>');
									parts.sz.push(u_length(tabs[0]));
									parts.stz += u_length(tabs[0]) + 1;
								}
							}
							++n;
						}
					}

					let html = '<td><a href="https://alpha.visl.sdu.dk/social/?t='+s_article+'" target="_tweet">TW</a></td><td class="text-end">';
					while (parts.p.length > 1 && parts.ptz > 60) {
						parts.ptz -= parts.pz[0] + 1;
						parts.p.shift();
						parts.pz.shift();
					}
					html += parts.p.join(' ');
					html += '</td><td class="text-start"><span class="fw-bold me-1">';
					html += parts.m.join(' ');
					html += '</span> ';
					while (parts.s.length > 1 && parts.stz > 60) {
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
			setTimeout(function () {$.getJSON('./callback.php', rq).done(handleLoad);}, 250);
		}
	}

	function contentLoaded() {
		state.hash = g_hash;
		let rq = {
			a: 'load',
			h: state.hash,
			c: state.context,
			rs: {},
			ts: [],
			};

		$('.qresults').each(function() {
			let id = $(this).attr('id');
			rq.rs[id] = {s: state.offset, n: state.pagesize, c: state.context};
			rq.ts.push(id);
		});

		setTimeout(function () {$.getJSON('./callback.php', rq).done(handleLoad);}, 500);
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
		};
}));
