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
		root.refine = factory();
	}
}(typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	let r = null;
	let rs = null;
	let rs2 = null;
	let rn = 0;
	let params = {};
	let values = [];
	let form = null;

	let verbatims = {
		'word': true, 'lex': true, 'pos': true,
		'h_word': true, 'h_lex': true, 'h_pos': true,
		's_word': true, 's_lex': true, 's_pos': true,
	};

	function to_html(s) {
		return String(s).replace(/&(?!\w+;)/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

	function to_id(s) {
		return to_html(s.replace(/[^A-Za-z0-9]+/g, '_'));
	}

	function create_table() {
		let nr = r.clone();
		nr.attr('id', 'r_' + rn);
		nr.find('[name]').each(function() {
			$(this).attr('name', $(this).attr('name') + '_' + rn);
		});
		nr.find('[for]').each(function() {
			$(this).attr('for', $(this).attr('for') + '_' + rn);
		});
		nr.find('[id]').each(function() {
			$(this).attr('id', $(this).attr('id') + '_' + rn);
		});
		++rn;
		nr.find('input').change(update_search);
		return nr;
	}

	function insert_before(e) {
		$(e).closest('.etable').before(create_table());
		update_search();
	}

	function insert_after(e) {
		$(e).closest('.etable').after(create_table());
		update_search();
	}

	function delete_table(e) {
		if ($('.etable').length <= 1) {
			return;
		}
		$(e).closest('.etable').remove();
		update_search();
	}

	function toggle_sibling(e) {
		let tbl = $(e).closest('.etable');
		let dp = tbl.find('.sibbox');
		if (dp.hasClass('hidden')) {
			dp.removeClass('hidden');
			let html = tbl.find('.midbox').first().html();
			html = html.replace(/ id="([^"]+)"/g, ' id="s_$1"');
			html = html.replace(/ name="([^"]+)"/g, ' name="s_$1"');
			html = html.replace(/ data-attr="([^"]+)"/g, ' data-attr="s_$1"');
			html = html.replace(/ for="([^"]+)"/g, ' for="s_$1"');

			dp.first().html(html);
			dp.first().find('input').change(update_search);
			tbl.find('.inbox').addClass('hidden').find('input').first().click();
		}
		else {
			dp.addClass('hidden').text('');
			tbl.find('.inbox').removeClass('hidden');
		}
		update_search();
	}

	function toggle_dependency(e) {
		let tbl = $(e).closest('.etable');
		let dp = tbl.find('.depbox');
		if (dp.hasClass('hidden')) {
			dp.removeClass('hidden');
			let html = tbl.find('.midbox').first().html();
			html = html.replace(/ id="([^"]+)"/g, ' id="h_$1"');
			html = html.replace(/ name="([^"]+)"/g, ' name="h_$1"');
			html = html.replace(/ data-attr="([^"]+)"/g, ' data-attr="h_$1"');
			html = html.replace(/ for="([^"]+)"/g, ' for="h_$1"');

			dp.find('td').first().html(html);
			dp.find('td').first().find('input').change(update_search);
			tbl.find('.btnSibling').removeClass('hidden');
		}
		else {
			dp.addClass('hidden');
			dp.html('<td></td>');
			tbl.find('.sibbox').addClass('hidden').text('');
			tbl.find('.btnSibling').addClass('hidden');
			tbl.find('.inbox').removeClass('hidden');
		}
		update_search();
	}

	function toggle_list(e) {
		//console.log(e);
		let je = $(e);
		let tbl = $('#'+je.attr('for'));
		if (tbl.is(':visible')) {
			je.text(je.text().replace(/ -$/, ' +'));
			tbl.hide();
		}
		else {
			je.text(je.text().replace(/ \+$/, ' -'));
			tbl.show();
		}
	}

	function toggle_refs(e) {
		let nr = $(e).closest('.etable').attr('id').substr(1);
		let is_h = $(e).closest('.colored').hasClass('midbox') ? '' : 'h_';
		if ($(e).closest('.colored').hasClass('sibbox')) {
			is_h = 's_';
		}
		let refs = $(e).attr('data-refs');
		if (refs) {
			refs = refs.split("\t");
			for (let i=0 ; i<refs.length ; ++i) {
				let ref = refs[i].split('.');
				ref = $('#'+is_h+ref[0]+nr).find('[name="'+is_h+ref[1]+nr+'"]').closest('tr');
				if (!ref || !ref.length) {
					ref = refs[i].split('.');
					ref = $('#'+is_h+ref[0]+nr).find('[name="'+is_h+to_id(ref[1])+nr+'"]').closest('tr');
				}
				if (!ref) {
					continue;
				}
				let n = parseInt(ref.attr('data-show'));
				n += e.checked ? 1 : -1;

				ref.attr('data-show', n);
				if (n) {
					ref.show();
				}
				else {
					ref.hide();
				}
			}
		}
	}

	function toggle_children(e) {
		let ins = $(e).closest('tr').next().find('input');
		for (let i=0 ; i<ins.length ; ++i) {
			let inp = $(ins[i]);
			inp.prop('checked', e.checked);
			inp.change();
		}
	}

	function get_extra(e) {
		let extra = '';
		if (e.attr('attr')) {
			extra += ' data-attr="'+e.attr('attr')+'"';
		}
		if (e.attr('value')) {
			extra += ' data-value="'+to_html(e.attr('value'))+'"';
		}
		return extra;
	}

	function get_refs(e) {
		let refs = '';
		let ss = e.children('select');
		if (ss.length) {
			refs += ' onchange="refine.toggle_refs(this);" data-refs="';
			for (let s=0 ; s<ss.length ; ++s) {
				refs += $(ss[s]).attr('ref') + "\t";
			}
			refs = $.trim(refs) + '"';
		}
		return refs;
	}

	function xml_fixer(match, p1, offset, string) {
		p1 = p1.replace(/&/g, '&amp;');
		p1 = p1.replace(/</g, '&lt;');
		p1 = p1.replace(/>/g, '&gt;');
		return '="' + p1 + '"';
	}

	function _update_search_helper(which, where) {
		let search = '';

		which.find('.etable').each(function() {
			let e = $(this);
			let nr = e.attr('id').substr(1);
			let tbl = '[';

			let fields = {};
			let inv = {};
			let cd = {};

			if (which === rs2) {
				e.find('.where').text('SQ');
			}

			let inps = e.find('input[type="text"]');
			for (let i=0 ; i<inps.length ; ++i) {
				let inp = $(inps[i]);
				let attr = inp.attr('data-attr');
				let val = $.trim(inp.val().replace(/"/g, "''"));

				if (!val) {
					continue;
				}
				if (!fields[attr]) {
					fields[attr] = [];
					inv[attr] = false;
					cd[attr] = '';
				}
				fields[attr].push(val);

				let neg = inp.closest('tr').find('input[type="checkbox"]').first();
				if (neg && neg.prop('checked')) {
					inv[attr] = true;
				}

				let trans = inp.closest('tr').next().find('input[type="checkbox"]');
				cd[attr] = '';
				if (trans && trans.first().prop('checked')) {
					cd[attr] = '_lc';
				}
				if (trans && trans.last().prop('checked')) {
					cd[attr] = '_nd';
				}
			}

			let cbxs = e.find('[data-attr]:checked');
			for (let i=0 ; i<cbxs.length ; ++i) {
				let cbx = $(cbxs[i]);
				let attr = cbx.attr('data-attr');
				let val = cbx.attr('data-value');

				if (cbx.attr('data-group')) {
					attr += '\uE001'+cbx.attr('data-group');
				}

				if (!val) {
					console.log(cbxs[i]);
					continue;
				}
				if (!fields[attr]) {
					fields[attr] = [];
					inv[attr] = false;
					cd[attr] = '';
				}
				fields[attr].push(val);

				let neg = cbx.closest('.exlist').prev().find('input').first();
				if (neg && neg.prop('checked')) {
					inv[attr] = true;
				}
			}

			let joins = [];
			for (let attr in fields) {
				if (!fields.hasOwnProperty(attr)) {
					continue;
				}
				for (let i=0 ; i<fields[attr].length ; ++i) {
					if (!verbatims.hasOwnProperty(attr)) {
						fields[attr][i] = '.*(?:^| )'+fields[attr][i]+'(?: |$).*';
					}
					if (fields[attr].length > 1) {
						fields[attr][i] = '(?:'+fields[attr][i]+')';
					}
				}
				let neg = inv[attr] ? '!' : '';
				joins.push(attr + cd[attr] + neg + '="'+ fields[attr].join('|') +'"');
			}

			tbl += joins.join(' & ');
			tbl += ']' + e.find('[name="n'+nr+'"]:checked').val();
			search += tbl+' ';
		});

		search = search.replace(/\uE001[^=!]+/g, '');
		//search = search.replace(/</g, 'lltt').replace(/>/g, 'ggtt').replace(/&/g, '_AND_');
		search = $.trim(search);
		$(where).val(search);

		if (where === '#query2') {
			if (search.length) {
				$('#s2').show();
			}
			else {
				$('#s2').hide();
			}
		}
	}

	function update_search(which, where) {
		_update_search_helper(rs, '#query');
		_update_search_helper(rs2, '#query2');
	}

	function parse_values(s, fld) {
		let vals = [];
		let found = true;
		while (found) {
			found = false;
			for (let i=0 ; i<values.length ; ++i) {
				if (s.indexOf('('+values[i]+')') === 0) {
					s = s.substr(values[i].length + 2);
					if (s.indexOf('|') === 0) {
						s = s.substr(1);
					}
					vals.push(values[i]);
					found = true;
					break;
				}
			}
			for (let i=0 ; i<values.length ; ++i) {
				if (s.indexOf(values[i]) === 0) {
					s = s.substr(values[i].length);
					vals.push(values[i]);
					found = true;
					break;
				}
			}
		}
		return vals;
	}

	function parse_search(where, q) {
		if (!q) {
			if (where !== '#rs2') {
				$(where).append(create_table());
			}
			return;
		}

		let did_anything = false;
		let src = $.trim(q.replace(/\?:/g, '').replace(/_PLUS_/g, '+').replace(/_HASH_/g, '#').replace(/_AND_/g, '&').replace(/_PCNT_/g, '%').replace(/\(\.\* \)\?/g, '').replace(/\( \.\*\)\?/g, ''));

		let re_fld = /^([a-z_]+)(!?)=/;
		let re_val = /"([^"]+)"/;

		if (src.charAt(0) !== '[') {
			src = '['+src+']';
		}

		while (src.charAt(0) === '[') {
			src = src.substr(1);
			let tbl = create_table();
			$(where).append(tbl);
			toggle_dependency(tbl);
			toggle_sibling(tbl);

			let ps = [];

			let had_head = false;
			let had_sibling = false;
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

				if (fld.indexOf('h_') == 0) {
					had_head = true;
				}
				else if (fld.indexOf('s_') == 0) {
					had_sibling = true;
				}

				if (/_nd$/.test(fld)) {
					fld = fld.substr(0, fld.length-3);
					$(tbl).find('[name="'+fld+'_c_'+(rn-1)+'"]').prop('checked', true);
					$(tbl).find('[name="'+fld+'_d_'+(rn-1)+'"]').prop('checked', true);
				}
				if (/_lc$/.test(fld)) {
					fld = fld.substr(0, fld.length-3);
					$(tbl).find('[name="'+fld+'_c_'+(rn-1)+'"]').prop('checked', true);
				}

				src = $.trim(src.substr(val[0].length));
				//console.log(src);
				if (fld === 'word' || fld === 'lex' || fld == 'h_word' || fld == 'h_lex' || fld == 's_word' || fld == 's_lex') {
					$(tbl).find('[data-attr="'+fld+'"]').val(val[1]);
				}
				else {
					let vals = parse_values(val[1], fld);
					for (let i=0 ; i<vals.length ; ++i) {
						let dv = $(tbl).find('[data-value="'+vals[i]+'"]').filter('[data-attr="'+fld+'"]');

						let p = dv.closest('.exlist').prev();
						p.find('input').first().prop('checked', not);
						if (p.find('label').first()) {
							ps.push(p.find('label').first()[0]);
						}

						p = dv.closest('[id]');
						p = $(tbl).find('label[for="'+p.attr('id')+'"]');
						if (p) {
							ps.push(p[0]);
						}

						dv.prop('checked', true);
						dv.change();
					}
					if (vals.length === 0) {
						$(tbl).find('[data-attr="'+fld+'"]').val(val[1]);
					}
				}

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
				$(tbl).find('[value="'+src.charAt(0)+'"]').prop('checked', true);
				src = $.trim(src.substr(1));
			}

			ps = $.unique(ps);
			for (let i=0 ; i<ps.length ; ++i) {
				toggle_list(ps[i]);
			}

			did_anything = true;
			if (!had_sibling) {
				toggle_sibling(tbl);
			}
			if (!had_head) {
				toggle_dependency(tbl);
			}
		}

		if (!did_anything) {
			$(where).append(create_table());
		}
	}

	function init() {
		let url = new URL(window.location);
		let params = url.searchParams;

		rs = $('#rs');
		rs2 = $('#rs2');

		$('#toggle_sq').click(function() {
			if ($(rs2).find('.etable').length) {
				$(rs2).find('.etable').remove();
			}
			else {
				let q2 = $.trim($('#query2').val());
				if (!q2) {
					$('#query2').val('[]');
				}
				parse_search('#rs2', '#query2');
			}
			update_search();
		});

		let config = '_static/refine/default.xml';
		let cs = $('.chkCorpus:checked');
		if (cs.length) {
			config = '_static/refine/' + cs.first().attr('name').substr(2, 3) + '.xml';
		}
		if (params.has('l') && params.get('l') !== 'mul' && /^[a-z]{3}$/.test(params.get('l'))) {
			config = '_static/refine/' + params.get('l') + '.xml';
		}
		$.get(config, null, function(xml) {
			xml = xml.replace(/="([^"]+)"/g, xml_fixer);
			config = $.parseXML(xml);
			if (!config) {
				alert('Could not parse config!');
				return;
			}

			r = $('#r');
			let mb = r.find('.midbox').first();

			let gs = [];
			let cs = $(config).find('advancedmenu').children();
			let last = null;
			for (let i=0 ; i<cs.length ; ++i) {
				if (cs[i].nodeName !== last) {
					gs.push([]);
					last = cs[i].nodeName;
				}
				gs[gs.length-1].push(cs[i]);
			}

			for (let gi=0 ; gi<gs.length ; ++gi) {
				let tfs = gs[gi];
				let tf = '<table>';
				for (let i=0 ; i<tfs.length ; ++i) {
					let f = $(tfs[i]);

					let attr = f.attr('attr');
					let extra = get_extra(f);

					let onc = '';
					if (tfs[i].nodeName === 'expandlist') {
						attr = to_id(f.attr('name'));
						onc = ' onclick="refine.toggle_list(this);"';
					}

					tf += '<tr>';
					tf += '<td><label for="'+attr+'"'+onc+'>'+f.attr('name');
					if (tfs[i].nodeName === 'textfield') {
						let sz = f.attr('negatable') ? 10 : 20;
						tf += '</label></td></td><td><input type="text" size="'+sz+'" name="'+attr+'" id="'+attr+'"'+extra+'>';
						if (f.attr('negatable')) {
							tf += ' <label title="Invert match"><input type="checkbox" name="'+attr+'_neg">¬</label>';
						}
					}
					else if (tfs[i].nodeName === 'expandlist') {
						tf += ' +</label>';
						if (f.attr('negatable')) {
							tf += '</td><td><label title="Invert match"><input type="checkbox" name="'+attr+'_neg">¬</label>';
						}
					}
					tf += '</td></tr>';

					if (tfs[i].nodeName === 'expandlist') {
						tf += '<tr id="'+attr+'" class="hidden exlist"><td colspan="2"><table>';
						let mss = f.children('multiselect');
						for (let j=0 ; j<mss.length ; ++j) {
							let ms = $(mss[j]);
							let id = to_id(ms.attr('name'));
							let extra = get_extra(ms);

							let hide = ms.attr('mustselect') ? ' class="hidden" data-show="0"' : '';
							let refs = get_refs(ms);

							let cxs = ms.children('checkbox');
							if (cxs.length) {
								refs = ' onclick="refine.toggle_children(this);"';
							}

							tf += '<tr'+hide+'><td><label><input type="checkbox" name="'+id+'"'+refs+extra+'> '+ms.attr('name')+'</label></td><td>';

							id += j;

							if (cxs.length) {
								tf += '<label class="smaller" for="'+id+'" onclick="refine.toggle_list(this);">more</label>';
							}
							tf += '</td></tr>';
							if (cxs.length) {
								tf += '<tr id="'+id+'" class="hidden"><td colspan="2">';
								for (let k=0 ; k<cxs.length ; ++k) {
									let cx = $(cxs[k]);
									let extra = get_extra(cx);
									let fid = to_id(cx.attr('name'));
									let refs = get_refs(cx);

									if (f.attr('and')) {
										extra += ' data-group="'+id+'"';
									}

									tf += '&nbsp; <label><input type="checkbox" name="'+fid+'"'+refs+extra+'> '+cx.attr('name')+'</label><br>';
								}
								tf += '</td><tr>';
							}
						}
						tf += '</table></td><tr>';
					}

					if (/^(word|lex)$/.test(attr)) {
						tf += '<tr><td colspan="2"><label title="Case insensitive"><input type="checkbox" name="'+attr+'_c"> ¬case</label>, <label title="Diacritics insensitive"><input type="checkbox" name="'+attr+'_d"> ¬diacritics</label></td></tr>';
					}
				}
				tf += '</table>';
				mb.append(tf);
			}
			r.detach();

			let vals = r.find('[data-value]');
			for (let i=0 ; i<vals.length ; ++i) {
				values.push($(vals[i]).attr('data-value'));
			}
			values.sort(function(a, b) {
				return b.length - a.length;
			});
			//console.log(values);

			let qs = [$.trim($('#query').val()), $.trim($('#query2').val())];
			parse_search('#rs', qs[0]);
			parse_search('#rs2', qs[1]);
			update_search();
			$('#rs').find('input[type="text"]').first().focus();
		}, 'html');
	}

	// Export useful functions
	return {
		init: init,
		toggle_refs: toggle_refs,
		toggle_list: toggle_list,
		toggle_children: toggle_children,
		insert_before: insert_before,
		insert_after: insert_after,
		toggle_dependency: toggle_dependency,
		toggle_sibling: toggle_sibling,
		delete_table: delete_table,
		};
}));
