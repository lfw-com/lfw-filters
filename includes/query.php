<?php
/**
 * Turning the validated state into WP_Query arguments, counts, and the hook
 * into the core Query Loop.
 *
 * @package lfw-filters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Query helpers. All counts are cached for the request.
 */
final class LFW_Filters_Query {

	/** Option counts are skipped when a facet's candidate set is larger. */
	const MAX_COUNT_IDS = 2000;

	/** @var array Cached totals keyed by argument hash. */
	private static $totals = array();

	/** @var array|null [taxonomy, slug] while the results block renders a group. */
	public static $group = null;

	/** Forget cached counts. */
	public static function reset() {
		self::$totals = array();
		self::$group  = null;
	}

	/**
	 * A tax_query for the state.
	 *
	 * @param array       $state  From LFW_Filters_Request::state().
	 * @param string|null $except Leave this taxonomy out (option counts).
	 * @param array|null  $extra  [taxonomy, slug] to add (groups).
	 * @return array
	 */
	public static function tax_query( $state, $except = null, $extra = null ) {
		$tq = array();
		foreach ( $state['tax'] as $tax => $slugs ) {
			if ( $tax === $except ) {
				continue;
			}
			$tq[] = array(
				'taxonomy'         => $tax,
				'field'            => 'slug',
				'terms'            => $slugs,
				'operator'         => 'IN',
				'include_children' => true,
			);
		}
		if ( $extra ) {
			$tq[] = array(
				'taxonomy'         => $extra[0],
				'field'            => 'slug',
				'terms'            => array( $extra[1] ),
				'operator'         => 'IN',
				'include_children' => true,
			);
		}
		if ( count( $tq ) > 1 ) {
			$tq['relation'] = 'AND';
		}
		return $tq;
	}

	/**
	 * WP_Query arguments for a post type under the current filters.
	 *
	 * @param string      $post_type Post type.
	 * @param bool        $filtered  False for the unfiltered total.
	 * @param string|null $except    Leave this taxonomy out.
	 * @param array|null  $extra     [taxonomy, slug] to add.
	 * @return array
	 */
	public static function args( $post_type, $filtered = true, $except = null, $extra = null ) {
		$state = $filtered ? LFW_Filters_Request::state( $post_type ) : array(
			'tax' => array(),
			'q'   => '',
		);
		$args  = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'has_password'        => false,
			'ignore_sticky_posts' => true,
		);
		$tq    = self::tax_query( $state, $except, $extra );
		if ( $tq ) {
			$args['tax_query'] = $tq; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		if ( '' !== $state['q'] ) {
			$args['s'] = $state['q'];
		}
		return $args;
	}

	/**
	 * Number of matching posts.
	 *
	 * @param string     $post_type Post type.
	 * @param bool       $filtered  Apply the filters.
	 * @param array|null $extra     [taxonomy, slug] to add.
	 * @return int
	 */
	public static function total( $post_type, $filtered = true, $extra = null ) {
		$args = self::args( $post_type, $filtered, null, $extra );
		$key  = md5( wp_json_encode( $args ) );
		if ( ! isset( self::$totals[ $key ] ) ) {
			$q                    = new WP_Query(
				$args + array(
					'fields'                 => 'ids',
					'posts_per_page'         => 1,
					'no_found_rows'          => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			self::$totals[ $key ] = (int) $q->found_posts;
		}
		return self::$totals[ $key ];
	}

	/**
	 * How many posts each term of a taxonomy would give, with the other
	 * filters applied (OR within a taxonomy). Null when the candidate set is
	 * too large to count cheaply.
	 *
	 * @param string $post_type Post type.
	 * @param string $taxonomy  Taxonomy.
	 * @return array<string, int>|null slug => count
	 */
	public static function option_counts( $post_type, $taxonomy ) {
		$args = self::args( $post_type, true, $taxonomy );
		$key  = 'opt:' . $taxonomy . ':' . md5( wp_json_encode( $args ) );
		if ( array_key_exists( $key, self::$totals ) ) {
			return self::$totals[ $key ];
		}
		$q   = new WP_Query(
			$args + array(
				'fields'                 => 'ids',
				'posts_per_page'         => self::MAX_COUNT_IDS + 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$ids = array_map( 'intval', $q->posts );
		if ( count( $ids ) > self::MAX_COUNT_IDS ) {
			self::$totals[ $key ] = null;
			return null;
		}
		$counts = array();
		if ( $ids ) {
			$rows = wp_get_object_terms( $ids, $taxonomy, array( 'fields' => 'all_with_object_id' ) );
			$hier = is_taxonomy_hierarchical( $taxonomy );
			$seen = array();
			if ( ! is_wp_error( $rows ) ) {
				foreach ( $rows as $t ) {
					$slugs = array( $t->slug );
					if ( $hier ) {
						foreach ( get_ancestors( $t->term_id, $taxonomy, 'taxonomy' ) as $anc ) {
							$a = get_term( $anc, $taxonomy );
							if ( $a && ! is_wp_error( $a ) ) {
								$slugs[] = $a->slug;
							}
						}
					}
					foreach ( $slugs as $s ) {
						$k = $s . '|' . $t->object_id;
						if ( isset( $seen[ $k ] ) ) {
							continue;
						}
						$seen[ $k ]   = true;
						$counts[ $s ] = isset( $counts[ $s ] ) ? $counts[ $s ] + 1 : 1;
					}
				}
			}
		}
		self::$totals[ $key ] = $counts;
		return $counts;
	}
}

/*
 * The hook into the core Query Loop. Only Query Loops flagged with
 * query.lfwFilters (set by the Filter results block) are touched.
 */
add_filter(
	'query_loop_block_query_vars',
	function ( $query, $block ) {
		$ctx = isset( $block->context['query'] ) && is_array( $block->context['query'] ) ? $block->context['query'] : array();
		if ( empty( $ctx['lfwFilters'] ) ) {
			return $query;
		}
		$pt = isset( $query['post_type'] ) ? $query['post_type'] : 'post';
		if ( is_array( $pt ) ) {
			$pt = reset( $pt );
		}
		$args = LFW_Filters_Query::args( (string) $pt, true, null, LFW_Filters_Query::$group );
		if ( ! empty( $args['tax_query'] ) ) {
			$existing = isset( $query['tax_query'] ) && is_array( $query['tax_query'] ) ? $query['tax_query'] : array();
			if ( $existing ) {
				$query['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					'relation' => 'AND',
					$existing,
					$args['tax_query'],
				);
			} else {
				$query['tax_query'] = $args['tax_query']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			}
		}
		if ( isset( $args['s'] ) ) {
			$query['s'] = $args['s'];
		}
		return $query;
	},
	10,
	2
);

/**
 * Flag every core/query inside a block list for filtering and give it its own
 * query (a Query Loop that inherits the main query would ignore the filters).
 *
 * @param array $blocks Parsed blocks.
 * @return array
 */
function lfw_filters_flag_queries( $blocks ) {
	foreach ( $blocks as $i => $b ) {
		if ( isset( $b['blockName'] ) && 'core/query' === $b['blockName'] ) {
			$q = isset( $b['attrs']['query'] ) && is_array( $b['attrs']['query'] ) ? $b['attrs']['query'] : array();
			$q['lfwFilters']                  = true;
			$q['inherit']                     = false;
			$blocks[ $i ]['attrs']['query']   = $q;
		}
		if ( ! empty( $b['innerBlocks'] ) ) {
			$blocks[ $i ]['innerBlocks'] = lfw_filters_flag_queries( $b['innerBlocks'] );
		}
	}
	return $blocks;
}

/**
 * The post type of the first core/query in a block list.
 *
 * @param array $blocks Parsed blocks.
 * @return string
 */
function lfw_filters_find_post_type( $blocks ) {
	foreach ( $blocks as $b ) {
		if ( isset( $b['blockName'] ) && 'core/query' === $b['blockName'] ) {
			return isset( $b['attrs']['query']['postType'] ) ? (string) $b['attrs']['query']['postType'] : 'post';
		}
		if ( ! empty( $b['innerBlocks'] ) ) {
			$pt = lfw_filters_find_post_type( $b['innerBlocks'] );
			if ( '' !== $pt ) {
				return $pt;
			}
		}
	}
	return '';
}

add_filter(
	'render_block_data',
	function ( $parsed ) {
		if ( isset( $parsed['blockName'] ) && 'lfw-filters/results' === $parsed['blockName'] && ! empty( $parsed['innerBlocks'] ) ) {
			$parsed['innerBlocks'] = lfw_filters_flag_queries( $parsed['innerBlocks'] );
		}
		return $parsed;
	}
);
