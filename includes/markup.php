<?php
/**
 * Shared markup helpers. Nothing a visitor typed is printed: links are built
 * from validated term slugs only, and the keyword never appears.
 *
 * @package lfw-filters
 */

defined( 'ABSPATH' ) || exit;

/**
 * The page the filters submit to, without any query string.
 *
 * @return string
 */
function lfw_filters_base_url() {
	if ( is_singular() ) {
		return (string) get_permalink( get_queried_object_id() );
	}
	if ( is_post_type_archive() ) {
		$pt = get_query_var( 'post_type' );
		if ( is_array( $pt ) ) {
			$pt = reset( $pt );
		}
		$link = get_post_type_archive_link( (string) $pt );
		if ( $link ) {
			return $link;
		}
	}
	if ( is_tax() || is_category() || is_tag() ) {
		$link = get_term_link( get_queried_object() );
		if ( ! is_wp_error( $link ) ) {
			return $link;
		}
	}
	if ( is_home() && get_option( 'page_for_posts' ) ) {
		return (string) get_permalink( (int) get_option( 'page_for_posts' ) );
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return home_url( '/' );
	}
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- path only, escaped on output.
	return home_url( $path ? $path : '/' );
}

/**
 * A link to the current filters with changes. Built only from validated
 * slugs; the keyword is never put into markup.
 *
 * @param array       $state  Validated state.
 * @param string|null $tax    Taxonomy to remove a value from.
 * @param string|null $slug   Slug to remove (null removes the whole taxonomy).
 * @return string
 */
function lfw_filters_link( $state, $tax = null, $slug = null ) {
	$parts = array();
	foreach ( $state['tax'] as $name => $slugs ) {
		foreach ( $slugs as $s ) {
			if ( $name === $tax && ( null === $slug || $s === $slug ) ) {
				continue;
			}
			$parts[] = rawurlencode( $name ) . '=' . rawurlencode( $s );
		}
	}
	$base = lfw_filters_base_url();
	return $parts ? $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . implode( '&', $parts ) : $base;
}

/**
 * Facets for a Filters block: only allowlisted taxonomies, each once, with
 * a known control type.
 *
 * @param string $post_type Post type.
 * @param mixed  $facets    The facets attribute.
 * @return array[] Each: taxonomy, control, label.
 */
function lfw_filters_facets( $post_type, $facets ) {
	$allowed = LFW_Filters_Request::taxonomies( $post_type );
	$out     = array();
	$list    = is_array( $facets ) && $facets ? $facets : array_map(
		function ( $t ) {
			return array( 'taxonomy' => $t );
		},
		$allowed
	);
	foreach ( $list as $f ) {
		if ( ! is_array( $f ) || empty( $f['taxonomy'] ) || ! in_array( $f['taxonomy'], $allowed, true ) || isset( $out[ $f['taxonomy'] ] ) ) {
			continue;
		}
		$tax     = get_taxonomy( $f['taxonomy'] );
		$control = isset( $f['control'] ) && in_array( $f['control'], array( 'checkboxes', 'select', 'toggles' ), true ) ? $f['control'] : 'checkboxes';
		$label   = isset( $f['label'] ) && '' !== trim( (string) $f['label'] ) ? (string) $f['label'] : $tax->labels->singular_name;

		$out[ $f['taxonomy'] ] = array(
			'taxonomy' => $f['taxonomy'],
			'control'  => $control,
			'label'    => $label,
		);
	}
	return array_values( $out );
}

/**
 * Terms to offer for a facet: non-empty terms, parents before children.
 *
 * @param string $taxonomy Taxonomy.
 * @return WP_Term[]
 */
function lfw_filters_offer_terms( $taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'number'     => 200,
			'orderby'    => 'name',
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	/**
	 * Filters the terms offered for a facet (reorder or remove; never add
	 * terms of another taxonomy).
	 *
	 * @param WP_Term[] $terms    Terms, by name.
	 * @param string    $taxonomy Taxonomy.
	 */
	return (array) apply_filters( 'lfw_filters_offer_terms', $terms, $taxonomy );
}

/**
 * Visually hidden text.
 *
 * @param string $text Already escaped text.
 * @return string
 */
function lfw_filters_sr( $text ) {
	return '<span class="lfwf-sr">' . $text . '</span>';
}

/**
 * Lowercased post type nouns for counts.
 *
 * @param string $post_type Post type.
 * @param string $singular  Override.
 * @param string $plural    Override.
 * @return string[] [singular, plural]
 */
function lfw_filters_nouns( $post_type, $singular, $plural ) {
	$pto = get_post_type_object( $post_type );
	$s   = '' !== trim( (string) $singular ) ? $singular : ( $pto ? strtolower( $pto->labels->singular_name ) : __( 'result', 'lfw-filters' ) );
	$p   = '' !== trim( (string) $plural ) ? $plural : ( $pto ? strtolower( $pto->labels->name ) : __( 'results', 'lfw-filters' ) );
	return array( $s, $p );
}

/**
 * The count sentence.
 *
 * @param string $post_type Post type.
 * @param string $singular  Override.
 * @param string $plural    Override.
 * @return string Plain text.
 */
function lfw_filters_count_text( $post_type, $singular = '', $plural = '' ) {
	list( $s, $p ) = lfw_filters_nouns( $post_type, $singular, $plural );
	$state         = LFW_Filters_Request::state( $post_type );
	$all           = LFW_Filters_Query::total( $post_type, false );
	$n             = $state['active'] ? LFW_Filters_Query::total( $post_type, true ) : $all;
	if ( ! $state['active'] ) {
		/* translators: 1: number, 2: noun */
		return sprintf( __( '%1$s %2$s', 'lfw-filters' ), number_format_i18n( $all ), 1 === $all ? $s : $p );
	}
	if ( 0 === $n ) {
		/* translators: %s: plural noun */
		return sprintf( __( 'No %s match', 'lfw-filters' ), $p );
	}
	/* translators: 1: shown, 2: total, 3: noun */
	return sprintf( __( 'Showing %1$s of %2$s %3$s', 'lfw-filters' ), number_format_i18n( $n ), number_format_i18n( $all ), 1 === $all ? $s : $p );
}

/**
 * Give every Query Loop item its term slugs as data attributes, so a static
 * copy of the page (no server behind it) can filter the rendered list in the
 * browser. Only slugs of public taxonomies of the post type are written;
 * nothing a visitor sent is involved. On WordPress itself the attributes are
 * inert: the server still does the filtering.
 *
 * @param string $html      Rendered results.
 * @param string $post_type Post type.
 * @return string
 */
function lfw_filters_annotate_items( $html, $post_type ) {
	if ( '' === $html || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $html;
	}
	$taxes = LFW_Filters_Request::taxonomies( $post_type );
	$p     = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_tag( array( 'tag_name' => 'li' ) ) ) {
		if ( ! $p->has_class( 'wp-block-post' ) ) {
			continue;
		}
		$id = 0;
		foreach ( $p->class_list() as $cls ) {
			if ( preg_match( '/^post-(\d+)$/', $cls, $m ) ) {
				$id = (int) $m[1];
				break;
			}
		}
		if ( ! $id ) {
			continue;
		}
		$tokens = array();
		foreach ( $taxes as $tax ) {
			$terms = get_the_terms( $id, $tax );
			if ( ! $terms || is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$tokens[] = $tax . ':' . $term->slug;
				if ( is_taxonomy_hierarchical( $tax ) ) {
					foreach ( get_ancestors( $term->term_id, $tax, 'taxonomy' ) as $anc ) {
						$a = get_term( $anc, $tax );
						if ( $a && ! is_wp_error( $a ) ) {
							$tokens[] = $tax . ':' . $a->slug;
						}
					}
				}
			}
		}
		$p->set_attribute( 'data-lfwf-item', (string) $id );
		$p->set_attribute( 'data-lfwf-terms', implode( ' ', array_unique( $tokens ) ) );
	}
	return $p->get_updated_html();
}
