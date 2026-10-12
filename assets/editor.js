/* LFW Filters, editor side. Every block renders on the server, so the editor
   shows the markup visitors get. No build step. */
(function (wp) {
  'use strict';

  const el = wp.element.createElement;
  const be = wp.blockEditor;
  const c = wp.components;
  const SSR = wp.serverSideRender;
  const __ = wp.i18n.__;
  const useSelect = wp.data.useSelect;

  function usePostTypes() {
    return useSelect(function (select) {
      const types = select('core').getPostTypes({ per_page: -1 }) || [];
      return types.filter(function (t) { return t.viewable && t.slug !== 'attachment'; });
    }, []);
  }

  function useTaxonomies(postType) {
    return useSelect(function (select) {
      const taxes = select('core').getTaxonomies({ type: postType, per_page: -1 }) || [];
      return taxes.filter(function (t) { return t.visibility ? t.visibility.publicly_queryable : true; });
    }, [postType]);
  }

  function postTypeControl(a, set) {
    const types = usePostTypes();
    const options = types.map(function (t) { return { label: t.name, value: t.slug }; });
    if (!options.some(function (o) { return o.value === a.postType; })) {
      options.unshift({ label: a.postType, value: a.postType });
    }
    return el(c.SelectControl, {
      key: 'pt',
      label: __('Post type', 'lfw-filters'),
      value: a.postType,
      options: options,
      onChange: function (v) { set({ postType: v }); }
    });
  }

  function text(key, label, a, set, help) {
    return el(c.TextControl, {
      key: key, label: label, help: help, value: a[key] || '',
      onChange: function (v) { const o = {}; o[key] = v; set(o); }
    });
  }

  function facetControls(a, set) {
    const taxes = useTaxonomies(a.postType);
    const facets = Array.isArray(a.facets) ? a.facets : [];
    function find(slug) { return facets.filter(function (f) { return f.taxonomy === slug; })[0]; }
    function update(slug, patch) {
      const next = facets.map(function (f) { return f.taxonomy === slug ? Object.assign({}, f, patch) : f; });
      set({ facets: next });
    }
    return taxes.map(function (t) {
      const f = find(t.slug);
      const rows = [el(c.ToggleControl, {
        key: 'on',
        label: t.name,
        checked: !!f,
        onChange: function (on) {
          set({ facets: on ? facets.concat([{ taxonomy: t.slug, control: 'checkboxes', label: '' }]) : facets.filter(function (x) { return x.taxonomy !== t.slug; }) });
        }
      })];
      if (f) {
        rows.push(el(c.SelectControl, {
          key: 'ctl',
          label: __('Control', 'lfw-filters'),
          value: f.control || 'checkboxes',
          options: [
            { label: __('Checkbox group', 'lfw-filters'), value: 'checkboxes' },
            { label: __('Select', 'lfw-filters'), value: 'select' },
            { label: __('Toggle buttons', 'lfw-filters'), value: 'toggles' }
          ],
          onChange: function (v) { update(t.slug, { control: v }); }
        }));
        rows.push(el(c.TextControl, {
          key: 'lbl',
          label: __('Label (optional)', 'lfw-filters'),
          value: f.label || '',
          onChange: function (v) { update(t.slug, { label: v }); }
        }));
      }
      return el('div', { key: t.slug, style: { marginBottom: '16px' } }, rows);
    });
  }

  function preview(name, a) {
    return el(c.Disabled, null, el(SSR, { block: name, attributes: a }));
  }

  wp.blocks.registerBlockType('lfw-filters/filters', {
    edit: function (props) {
      const a = props.attributes;
      const set = props.setAttributes;
      return el('div', be.useBlockProps(), [
        el(be.InspectorControls, { key: 'i' }, [
          el(c.PanelBody, { key: 'src', title: __('Source', 'lfw-filters') }, [
            postTypeControl(a, set),
            el('p', { key: 'h', className: 'components-base-control__help' }, __('No taxonomies switched on means every public taxonomy, as checkboxes.', 'lfw-filters'))
          ].concat(facetControls(a, set))),
          el(c.PanelBody, { key: 'lay', title: __('Layout and labels', 'lfw-filters') }, [
            el(c.SelectControl, {
              key: 'layout', label: __('Layout', 'lfw-filters'), value: a.layout,
              options: [{ label: __('Band (menus in a row)', 'lfw-filters'), value: 'band' }, { label: __('Panel (all filters open)', 'lfw-filters'), value: 'panel' }],
              onChange: function (v) { set({ layout: v }); }
            }),
            el(c.ToggleControl, { key: 's', label: __('Keyword field', 'lfw-filters'), checked: !!a.search, onChange: function (v) { set({ search: v }); } }),
            el(c.ToggleControl, { key: 'n', label: __('Show counts beside options', 'lfw-filters'), checked: !!a.showCounts, onChange: function (v) { set({ showCounts: v }); } }),
            text('searchLabel', __('Keyword label', 'lfw-filters'), a, set),
            text('searchPlaceholder', __('Keyword hint', 'lfw-filters'), a, set),
            text('submitLabel', __('Button label (shown without JavaScript)', 'lfw-filters'), a, set),
            text('label', __('Form name for screen readers', 'lfw-filters'), a, set)
          ])
        ]),
        el('div', { key: 'p' }, preview('lfw-filters/filters', a))
      ]);
    },
    save: function () { return null; }
  });

  wp.blocks.registerBlockType('lfw-filters/results', {
    edit: function (props) {
      const a = props.attributes;
      const set = props.setAttributes;
      const blockProps = be.useBlockProps();
      const inner = be.useInnerBlocksProps(blockProps, {
        template: [['core/query', {
          query: { perPage: 12, pages: 0, offset: 0, postType: 'post', order: 'asc', orderBy: 'title', inherit: false, lfwFilters: true }
        }, [['core/post-template', {}, [['core/post-title', { isLink: true, level: 3 }]]], ['core/query-pagination']]]]
      });
      return el(wp.element.Fragment, null, [
        el(be.InspectorControls, { key: 'i' }, el(c.PanelBody, { title: __('Filter results', 'lfw-filters') }, [
          el('p', { key: 'h', className: 'components-base-control__help' }, __('The Query Loop inside follows the Filters block on the same page. Set its post type to match.', 'lfw-filters')),
          text('groupBy', __('Group by taxonomy (optional)', 'lfw-filters'), a, set, __('A taxonomy name, such as area. Grouped results have no pagination.', 'lfw-filters')),
          el(c.RangeControl, { key: 'lvl', label: __('Group heading level', 'lfw-filters'), value: a.groupHeadingLevel, min: 2, max: 6, onChange: function (v) { set({ groupHeadingLevel: v }); } }),
          text('emptyHeading', __('No results heading', 'lfw-filters'), a, set),
          text('emptyText', __('No results text', 'lfw-filters'), a, set)
        ])),
        el('div', Object.assign({ key: 'b' }, inner))
      ]);
    },
    save: function () { return el(be.InnerBlocks.Content); }
  });

  wp.blocks.registerBlockType('lfw-filters/active', {
    edit: function (props) {
      const a = props.attributes;
      const set = props.setAttributes;
      return el('div', be.useBlockProps(), [
        el(be.InspectorControls, { key: 'i' }, el(c.PanelBody, { title: __('Active filters', 'lfw-filters') }, [
          postTypeControl(a, set),
          text('label', __('List name for screen readers', 'lfw-filters'), a, set),
          text('clearLabel', __('Clear link label', 'lfw-filters'), a, set)
        ])),
        el('p', { key: 'p', className: 'lfwf-editor-note' }, __('Active filter chips appear here when a visitor filters.', 'lfw-filters'))
      ]);
    },
    save: function () { return null; }
  });

  wp.blocks.registerBlockType('lfw-filters/count', {
    edit: function (props) {
      const a = props.attributes;
      const set = props.setAttributes;
      return el('div', be.useBlockProps(), [
        el(be.InspectorControls, { key: 'i' }, el(c.PanelBody, { title: __('Result count', 'lfw-filters') }, [
          postTypeControl(a, set),
          text('singular', __('Singular noun (optional)', 'lfw-filters'), a, set),
          text('plural', __('Plural noun (optional)', 'lfw-filters'), a, set)
        ])),
        el('div', { key: 'p' }, preview('lfw-filters/count', a))
      ]);
    },
    save: function () { return null; }
  });
})(window.wp);
