<?php
/**
 * Filter results: the inner Query Loop, filtered (see includes/query.php),
 * optionally once per term of a grouping taxonomy, or an empty state.
 *
 * @package lfw-filters
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$lfwf_inner = isset( $block->parsed_block['innerBlocks'] ) ? lfw_filters_flag_queries( $block->parsed_block['innerBlocks'] ) : array();
$lfwf_pt    = lfw_filters_find_post_type( $lfwf_inner );
$lfwf_pt    = '' !== $lfwf_pt ? $lfwf_pt : 'post';
$lfwf_total = LFW_Filters_Query::total( $lfwf_pt, true );
$lfwf_out   = '';

if ( 0 === $lfwf_total ) {
	$lfwf_out = '<div class="lfwf-empty"><p class="lfwf-empty-heading">' . esc_html( $attributes['emptyHeading'] ) . '</p>'
		. ( '' !== trim( (string) $attributes['emptyText'] ) ? '<p>' . esc_html( $attributes['emptyText'] ) . '</p>' : '' ) . '</div>';
} elseif ( ! empty( $attributes['groupBy'] ) && in_array( $attributes['groupBy'], LFW_Filters_Request::taxonomies( $lfwf_pt ), true ) ) {
	$lfwf_tax   = $attributes['groupBy'];
	$lfwf_level = max( 2, min( 6, (int) $attributes['groupHeadingLevel'] ) );
	foreach ( lfw_filters_offer_terms( $lfwf_tax ) as $lfwf_term ) {
		$lfwf_n = LFW_Filters_Query::total( $lfwf_pt, true, array( $lfwf_tax, $lfwf_term->slug ) );
		if ( ! $lfwf_n ) {
			continue;
		}
		LFW_Filters_Query::$group = array( $lfwf_tax, $lfwf_term->slug );
		$lfwf_html                = '';
		foreach ( $lfwf_inner as $lfwf_ib ) {
			$lfwf_html .= render_block( $lfwf_ib );
		}
		LFW_Filters_Query::$group = null;
		$lfwf_hid                 = 'lfwf-g-' . sanitize_html_class( $lfwf_tax . '-' . $lfwf_term->slug );
		$lfwf_out                .= sprintf(
			'<section class="lfwf-group" aria-labelledby="%1$s" data-lfwf-group="%6$s"><h%2$d class="lfwf-group-heading" id="%1$s">%3$s <span class="lfwf-group-n">%4$s</span></h%2$d>%5$s</section>',
			esc_attr( $lfwf_hid ),
			$lfwf_level,
			esc_html( $lfwf_term->name ),
			'<span aria-hidden="true">' . esc_html( number_format_i18n( $lfwf_n ) ) . '</span>' . lfw_filters_sr( esc_html( sprintf( /* translators: %s: number */ _n( '(%s result)', '(%s results)', $lfwf_n, 'lfw-filters' ), number_format_i18n( $lfwf_n ) ) ) ),
			$lfwf_html,
			esc_attr( $lfwf_tax . ':' . $lfwf_term->slug )
		);
	}
} else {
	$lfwf_out = $content;
}

// Each rendered item carries its term slugs, so a static copy of the page
// (no server) can filter the list in the browser; see assets/filters.js.
$lfwf_out = lfw_filters_annotate_items( $lfwf_out, $lfwf_pt );

printf(
	'<div %1$s id="lfwf-results" data-lfwf-region data-lfwf-post-type="%3$s" data-lfwf-empty-heading="%4$s" data-lfwf-empty-text="%5$s" data-lfwf-sr-one="%6$s" data-lfwf-sr-many="%7$s">%2$s</div>',
	get_block_wrapper_attributes( array( 'class' => 'lfwf lfwf-results' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$lfwf_out, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block output.
	esc_attr( $lfwf_pt ),
	esc_attr( $attributes['emptyHeading'] ),
	esc_attr( $attributes['emptyText'] ),
	/* translators: %s: number */
	esc_attr__( '(%s result)', 'lfw-filters' ),
	/* translators: %s: number */
	esc_attr__( '(%s results)', 'lfw-filters' )
);
