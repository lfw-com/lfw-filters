<?php
/**
 * Filters block: a plain GET form. Checked states come only from validated
 * term slugs; the keyword field is never prefilled by the server.
 *
 * @package lfw-filters
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$lfwf_pt     = isset( $attributes['postType'] ) ? (string) $attributes['postType'] : 'post';
$lfwf_facets = lfw_filters_facets( $lfwf_pt, isset( $attributes['facets'] ) ? $attributes['facets'] : array() );
$lfwf_layout = isset( $attributes['layout'] ) && 'panel' === $attributes['layout'] ? 'panel' : 'band';
$lfwf_state  = LFW_Filters_Request::state( $lfwf_pt );
$lfwf_counts = ! empty( $attributes['showCounts'] );

$lfwf_count_html = function ( $tax, $slug, $counts ) {
	if ( null === $counts ) {
		return '';
	}
	$n = isset( $counts[ $slug ] ) ? (int) $counts[ $slug ] : 0;
	return ' <span class="lfwf-n" data-lfwf-sync="n-' . esc_attr( $tax . '-' . $slug ) . '">'
		. '<span aria-hidden="true">' . esc_html( number_format_i18n( $n ) ) . '</span>'
		/* translators: %s: number of matching posts */
		. lfw_filters_sr( esc_html( sprintf( _n( '(%s result)', '(%s results)', $n, 'lfw-filters' ), number_format_i18n( $n ) ) ) )
		. '</span>';
};

$lfwf_html = '';
foreach ( $lfwf_facets as $lfwf_f ) {
	$lfwf_tax    = $lfwf_f['taxonomy'];
	$lfwf_terms  = lfw_filters_offer_terms( $lfwf_tax );
	$lfwf_sel    = isset( $lfwf_state['tax'][ $lfwf_tax ] ) ? $lfwf_state['tax'][ $lfwf_tax ] : array();
	$lfwf_oc     = $lfwf_counts ? LFW_Filters_Query::option_counts( $lfwf_pt, $lfwf_tax ) : null;
	$lfwf_id     = 'lfwf-' . sanitize_html_class( $lfwf_tax );
	$lfwf_legend = esc_html( $lfwf_f['label'] );

	if ( ! $lfwf_terms ) {
		continue;
	}

	if ( 'select' === $lfwf_f['control'] ) {
		$lfwf_opts = '<option value="">' . esc_html__( 'Any', 'lfw-filters' ) . '</option>';
		foreach ( $lfwf_terms as $lfwf_t ) {
			$lfwf_n     = ( null !== $lfwf_oc ) ? ' (' . number_format_i18n( isset( $lfwf_oc[ $lfwf_t->slug ] ) ? $lfwf_oc[ $lfwf_t->slug ] : 0 ) . ')' : '';
			$lfwf_opts .= sprintf(
				'<option value="%1$s"%2$s data-lfwf-name="%3$s">%3$s%4$s</option>',
				esc_attr( $lfwf_t->slug ),
				selected( isset( $lfwf_sel[0] ) && $lfwf_sel[0] === $lfwf_t->slug, true, false ),
				esc_html( $lfwf_t->name ),
				esc_html( $lfwf_n )
			);
		}
		$lfwf_html .= '<div class="lfwf-facet lfwf-facet-select" data-lfwf-facet="' . esc_attr( $lfwf_tax ) . '">'
			. '<label class="lfwf-label" for="' . esc_attr( $lfwf_id ) . '">' . $lfwf_legend . '</label>'
			. '<select class="lfwf-select" id="' . esc_attr( $lfwf_id ) . '" name="' . esc_attr( $lfwf_tax ) . '" data-lfwf-sync-options="' . esc_attr( $lfwf_tax ) . '">' . $lfwf_opts . '</select></div>';
		continue;
	}

	$lfwf_pill  = 'toggles' === $lfwf_f['control'];
	$lfwf_items = '';
	foreach ( $lfwf_terms as $lfwf_t ) {
		$lfwf_iid    = $lfwf_id . '-' . sanitize_html_class( $lfwf_t->slug );
		$lfwf_items .= '<li><label class="' . ( $lfwf_pill ? 'lfwf-pill' : 'lfwf-check' ) . '" for="' . esc_attr( $lfwf_iid ) . '">'
			. '<input type="checkbox" id="' . esc_attr( $lfwf_iid ) . '" name="' . esc_attr( $lfwf_tax ) . '" value="' . esc_attr( $lfwf_t->slug ) . '"' . checked( in_array( $lfwf_t->slug, $lfwf_sel, true ), true, false ) . '>'
			. '<span class="lfwf-opt">' . esc_html( $lfwf_t->name ) . '</span>'
			. $lfwf_count_html( $lfwf_tax, $lfwf_t->slug, $lfwf_oc )
			. '</label></li>';
	}
	$lfwf_list = '<ul class="lfwf-options' . ( $lfwf_pill ? ' is-pills' : '' ) . '" role="list">' . $lfwf_items . '</ul>';

	if ( 'band' === $lfwf_layout ) {
		$lfwf_nsel  = count( $lfwf_sel );
		$lfwf_badge = '<span class="lfwf-badge" data-lfwf-sync="sel-' . esc_attr( $lfwf_tax ) . '">'
			. ( $lfwf_nsel ? esc_html( number_format_i18n( $lfwf_nsel ) ) . lfw_filters_sr( ' ' . esc_html__( 'selected', 'lfw-filters' ) ) : '' )
			. '</span>';
		$lfwf_html .= '<details class="lfwf-drop lfwf-facet" data-lfwf-facet="' . esc_attr( $lfwf_tax ) . '">'
			. '<summary class="lfwf-summary"><span>' . $lfwf_legend . '</span>' . $lfwf_badge . '</summary>'
			. '<fieldset class="lfwf-fieldset"><legend class="lfwf-sr">' . $lfwf_legend . '</legend>' . $lfwf_list . '</fieldset>'
			. '</details>';
	} else {
		$lfwf_html .= '<fieldset class="lfwf-facet lfwf-fieldset" data-lfwf-facet="' . esc_attr( $lfwf_tax ) . '">'
			. '<legend class="lfwf-legend">' . $lfwf_legend . '</legend>' . $lfwf_list . '</fieldset>';
	}
}

$lfwf_search = '';
if ( ! empty( $attributes['search'] ) ) {
	$lfwf_ph     = isset( $attributes['searchPlaceholder'] ) ? (string) $attributes['searchPlaceholder'] : '';
	$lfwf_search = '<div class="lfwf-search">'
		. '<label class="lfwf-label' . ( 'band' === $lfwf_layout ? ' lfwf-sr' : '' ) . '" for="lfwf-q">' . esc_html( $attributes['searchLabel'] ) . '</label>'
		. '<input class="lfwf-input" id="lfwf-q" type="search" name="' . esc_attr( LFW_Filters_Request::KEYWORD_PARAM ) . '" maxlength="' . (int) LFW_Filters_Request::MAX_KEYWORD . '" autocomplete="off"'
		. ( '' !== $lfwf_ph ? ' placeholder="' . esc_attr( $lfwf_ph ) . '"' : '' ) . '>'
		. '</div>';
}

$lfwf_submit = '<div class="lfwf-actions" data-lfwf-submit><button class="lfwf-button" type="submit">' . esc_html( $attributes['submitLabel'] ) . '</button></div>';

if ( 'band' === $lfwf_layout ) {
	$lfwf_body = '<div class="lfwf-inner">' . $lfwf_search . '<div class="lfwf-facets">' . $lfwf_html . '</div>' . $lfwf_submit . '</div>';
} else {
	$lfwf_body = '<details class="lfwf-panel" open data-lfwf-panel><summary class="lfwf-panel-summary">' . esc_html__( 'Filters', 'lfw-filters' ) . '</summary>'
		. '<div class="lfwf-inner">' . $lfwf_search . '<div class="lfwf-facets">' . $lfwf_html . '</div>' . $lfwf_submit . '</div></details>';
}

// Plain permalinks (?page_id=4): a GET form drops the action's query string,
// so carry WordPress's own routing parameters as hidden fields.
$lfwf_base   = lfw_filters_base_url();
$lfwf_hidden = '';
$lfwf_bq     = (string) wp_parse_url( $lfwf_base, PHP_URL_QUERY );
if ( '' !== $lfwf_bq ) {
	parse_str( $lfwf_bq, $lfwf_bvars );
	foreach ( $lfwf_bvars as $lfwf_k => $lfwf_v ) {
		if ( is_scalar( $lfwf_v ) ) {
			$lfwf_hidden .= '<input type="hidden" name="' . esc_attr( $lfwf_k ) . '" value="' . esc_attr( $lfwf_v ) . '">';
		}
	}
}

$lfwf_wrap = get_block_wrapper_attributes(
	array(
		'class' => 'lfwf lfwf-filters is-layout-' . $lfwf_layout,
	)
);

printf(
	'<div %1$s><form class="lfwf-form" id="lfwf-form" action="%2$s" method="get" role="search" aria-label="%3$s" data-lfwf-form data-lfwf-selected-label="%5$s">%4$s</form></div>',
	$lfwf_wrap, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core escapes it.
	esc_url( $lfwf_base ),
	esc_attr( $attributes['label'] ),
	$lfwf_hidden . $lfwf_body, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
	esc_attr__( 'selected', 'lfw-filters' )
);
