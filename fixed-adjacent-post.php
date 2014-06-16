<?php
/*
Plugin Name: Fixed Adjacent Post
Plugin URI: http://kiteretz.com/
Version: 1.0
Author: KITE
Description: Fix the not-excluding terms bug of get_adjacent_post().
*/

// Navigation links

/**
 * Retrieve previous post that is adjacent to current post.
 *
 * @since 1.5.0
 *
 * @param bool         $in_same_term   Optional. Whether post should be in a same taxonomy term.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return mixed       Post object if successful. Null if global $post is not set. Empty string if no corresponding post exists.
 */
function get_fixed_previous_post( $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' ) {
	return get_fixed_adjacent_post( $in_same_term, $excluded_terms, true, $taxonomy );
}

/**
 * Retrieve next post that is adjacent to current post.
 *
 * @since 1.5.0
 *
 * @param bool         $in_same_term   Optional. Whether post should be in a same taxonomy term.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return mixed       Post object if successful. Null if global $post is not set. Empty string if no corresponding post exists.
 */
function get_fixed_next_post( $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' ) {
	return get_fixed_adjacent_post( $in_same_term, $excluded_terms, false, $taxonomy );
}

/**
 * Retrieve adjacent post.
 *
 * Can either be next or previous post.
 *
 * @since 2.5.0
 *
 * @param bool         $in_same_term   Optional. Whether post should be in a same taxonomy term.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs.
 * @param bool         $previous       Optional. Whether to retrieve previous post.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return mixed       Post object if successful. Null if global $post is not set. Empty string if no corresponding post exists.
 */
function get_fixed_adjacent_post( $in_same_term = false, $excluded_terms = '', $previous = true, $taxonomy = 'category' ) {
	global $wpdb;

	if ( ( ! $post = get_post() ) || ! taxonomy_exists( $taxonomy ) )
		return null;

	$current_post_date = $post->post_date;

	$join = '';
	$posts_in_ex_terms_sql = '';
	if ( $in_same_term || ! empty( $excluded_terms ) ) {
		$join = " INNER JOIN $wpdb->term_relationships AS tr ON p.ID = tr.object_id INNER JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";

		if ( $in_same_term ) {
			if ( ! is_object_in_taxonomy( $post->post_type, $taxonomy ) )
				return '';
			$term_array = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! $term_array || is_wp_error( $term_array ) )
				return '';
			$join .= $wpdb->prepare( " AND tt.taxonomy = %s AND tt.term_id IN (" . implode( ',', array_map( 'intval', $term_array ) ) . ")", $taxonomy );
		}

		$posts_in_ex_terms_sql = $wpdb->prepare( "AND tt.taxonomy = %s", $taxonomy );
		if ( ! empty( $excluded_terms ) ) {
			if ( ! is_array( $excluded_terms ) ) {
				// back-compat, $excluded_terms used to be $excluded_terms with IDs separated by " and "
				if ( false !== strpos( $excluded_terms, ' and ' ) ) {
					_deprecated_argument( __FUNCTION__, '3.3', sprintf( __( 'Use commas instead of %s to separate excluded terms.' ), "'and'" ) );
					$excluded_terms = explode( ' and ', $excluded_terms );
				} else {
					$excluded_terms = explode( ',', $excluded_terms );
				}
			}

			$excluded_terms = array_map( 'intval', $excluded_terms );

			if ( ! empty( $term_array ) ) {
				// $excluded_terms = array_diff( $excluded_terms, $term_array );
				$posts_in_ex_terms_sql = '';
			}

			if ( ! empty( $excluded_terms ) ) {
				$posts_in_ex_terms_sql = $wpdb->prepare( " AND tt.taxonomy = %s AND tt.term_id NOT IN (" . implode( $excluded_terms, ',' ) . ')', $taxonomy );
			}
		}
	}

	$adjacent = $previous ? 'previous' : 'next';
	$op = $previous ? '<' : '>';
	$order = $previous ? 'DESC' : 'ASC';

	/**
	 * Filter the JOIN clause in the SQL for an adjacent post query.
	 *
	 * The dynamic portion of the hook name, $adjacent, refers to the type
	 * of adjacency, 'next' or 'previous'.
	 *
	 * @since 2.5.0
	 *
	 * @param string $join           The JOIN clause in the SQL.
	 * @param bool   $in_same_term   Whether post should be in a same taxonomy term.
	 * @param array  $excluded_terms Array of excluded term IDs.
	 */
	$join  = apply_filters( "get_{$adjacent}_post_join", $join, $in_same_term, $excluded_terms );

	/**
	 * Filter the WHERE clause in the SQL for an adjacent post query.
	 *
	 * The dynamic portion of the hook name, $adjacent, refers to the type
	 * of adjacency, 'next' or 'previous'.
	 *
	 * @since 2.5.0
	 *
	 * @param string $where          The WHERE clause in the SQL.
	 * @param bool   $in_same_term   Whether post should be in a same taxonomy term.
	 * @param array  $excluded_terms Array of excluded term IDs.
	 */
	$where = apply_filters( "get_{$adjacent}_post_where", $wpdb->prepare( "WHERE p.post_date $op %s AND p.post_type = %s AND p.post_status = 'publish' $posts_in_ex_terms_sql", $current_post_date, $post->post_type), $in_same_term, $excluded_terms );

	/**
	 * Filter the ORDER BY clause in the SQL for an adjacent post query.
	 *
	 * The dynamic portion of the hook name, $adjacent, refers to the type
	 * of adjacency, 'next' or 'previous'.
	 *
	 * @since 2.5.0
	 *
	 * @param string $order_by The ORDER BY clause in the SQL.
	 */
	$sort  = apply_filters( "get_{$adjacent}_post_sort", "ORDER BY p.post_date $order LIMIT 1" );

	$query = "SELECT p.ID FROM $wpdb->posts AS p $join $where $posts_in_ex_terms_sql $sort";
	$query_key = 'adjacent_post_' . md5( $query );
	$result = wp_cache_get( $query_key, 'counts' );
	if ( false !== $result ) {
		if ( $result )
			$result = get_post( $result );
		return $result;
	}

	$result = $wpdb->get_var( $query );
	if ( null === $result )
		$result = '';

	wp_cache_set( $query_key, $result, 'counts' );

	if ( $result )
		$result = get_post( $result );

	return $result;
}

/**
 * Get adjacent post relational link.
 *
 * Can either be next or previous post relational link.
 *
 * @since 2.8.0
 *
 * @param string       $title          Optional. Link title format.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs.
 * @param bool         $previous       Optional. Whether to display link to previous or next post. Default true.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return string
 */
function get_fixed_adjacent_post_rel_link( $title = '%title', $in_same_term = false, $excluded_terms = '', $previous = true, $taxonomy = 'category' ) {
	if ( $previous && is_attachment() && $post = get_post() )
		$post = get_post( $post->post_parent );
	else
		$post = get_fixed_adjacent_post( $in_same_term, $excluded_terms, $previous, $taxonomy );

	if ( empty( $post ) )
		return;

	$post_title = the_title_attribute( array( 'echo' => false, 'post' => $post ) );

	if ( empty( $post_title ) )
		$post_title = $previous ? __( 'Previous Post' ) : __( 'Next Post' );

	$date = mysql2date( get_option( 'date_format' ), $post->post_date );

	$title = str_replace( '%title', $post_title, $title );
	$title = str_replace( '%date', $date, $title );

	$link = $previous ? "<link rel='prev' title='" : "<link rel='next' title='";
	$link .= esc_attr( $title );
	$link .= "' href='" . get_permalink( $post ) . "' />\n";

	$adjacent = $previous ? 'previous' : 'next';

	/**
	 * Filter the adjacent post relational link.
	 *
	 * The dynamic portion of the hook name, $adjacent, refers to the type
	 * of adjacency, 'next' or 'previous'.
	 *
	 * @since 2.8.0
	 *
	 * @param string $link The relational link.
	 */
	return apply_filters( "{$adjacent}_post_rel_link", $link );
}

/**
 * Display relational links for the posts adjacent to the current post.
 *
 * @since 2.8.0
 *
 * @param string       $title          Optional. Link title format.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 */
function fixed_adjacent_posts_rel_link( $title = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' ) {
	echo get_fixed_adjacent_post_rel_link( $title, $in_same_term, $excluded_terms, true, $taxonomy );
	echo get_fixed_adjacent_post_rel_link( $title, $in_same_term, $excluded_terms, false, $taxonomy );
}

/**
 * Display relational links for the posts adjacent to the current post for single post pages.
 *
 * This is meant to be attached to actions like 'wp_head'. Do not call this directly in plugins or theme templates.
 * @since 3.0.0
 *
 */
function fixed_adjacent_posts_rel_link_wp_head() {
	if ( !is_singular() || is_attachment() )
		return;
	fixed_adjacent_posts_rel_link();
}

/**
 * Display relational link for the next post adjacent to the current post.
 *
 * @since 2.8.0
 *
 * @param string       $title          Optional. Link title format.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 */
function fixed_next_post_rel_link( $title = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' ) {
	echo get_fixed_adjacent_post_rel_link( $title, $in_same_term, $excluded_terms, false, $taxonomy );
}

/**
 * Display relational link for the previous post adjacent to the current post.
 *
 * @since 2.8.0
 *
 * @param string       $title          Optional. Link title format.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs. Default true.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 */
function fixed_prev_post_rel_link( $title = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' ) {
	echo get_fixed_adjacent_post_rel_link( $title, $in_same_term, $excluded_terms, true, $taxonomy );
}

/*
 * Get previous post link that is adjacent to the current post.
 *
 * @since 3.7.0
 *
 * @param string       $format         Optional. Link anchor format.
 * @param string       $link           Optional. Link permalink format.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return string
 */
function get_fixed_previous_post_link( $format = '&laquo; %link', $link = '%title', $in_same_cat = false, $excluded_terms = '', $taxonomy = 'category' ) {
	return get_fixed_adjacent_post_link( $format, $link, $in_same_cat, $excluded_terms, true, $taxonomy );
}

/**
 * Display previous post link that is adjacent to the current post.
 *
 * @since 1.5.0
 * @see get_previous_post_link()
 *
 * @param string       $format         Optional. Link anchor format.
 * @param string       $link           Optional. Link permalink format.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 */
function fixed_previous_post_link( $format = '&laquo; %link', $link = '%title', $in_same_cat = false, $excluded_terms = '', $taxonomy = 'category' ) {
	echo get_fixed_previous_post_link( $format, $link, $in_same_cat, $excluded_terms, $taxonomy );
}

/**
 * Get next post link that is adjacent to the current post.
 *
 * @since 3.7.0
 *
 * @param string       $format         Optional. Link anchor format.
 * @param string       $link           Optional. Link permalink format.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return string
 */
function get_fixed_next_post_link( $format = '%link &raquo;', $link = '%title', $in_same_cat = false, $excluded_terms = '', $taxonomy = 'category' ) {
	return get_fixed_adjacent_post_link( $format, $link, $in_same_cat, $excluded_terms, false, $taxonomy );
}

/**
 * Display next post link that is adjacent to the current post.
 *
 * @since 1.5.0
 * @see get_next_post_link()
 *
 * @param string       $format         Optional. Link anchor format.
 * @param string       $link           Optional. Link permalink format.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 */
function fixed_next_post_link( $format = '%link &raquo;', $link = '%title', $in_same_cat = false, $excluded_terms = '', $taxonomy = 'category' ) {
	 echo get_fixed_next_post_link( $format, $link, $in_same_cat, $excluded_terms, $taxonomy );
}

/**
 * Get adjacent post link.
 *
 * Can be either next post link or previous.
 *
 * @since 3.7.0
 *
 * @param string       $format         Link anchor format.
 * @param string       $link           Link permalink format.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded terms IDs.
 * @param bool         $previous       Optional. Whether to display link to previous or next post. Default true.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return string
 */
function get_fixed_adjacent_post_link( $format, $link, $in_same_cat = false, $excluded_terms = '', $previous = true, $taxonomy = 'category' ) {
	if ( $previous && is_attachment() )
		$post = get_post( get_post()->post_parent );
	else
		$post = get_fixed_adjacent_post( $in_same_cat, $excluded_terms, $previous, $taxonomy );

	if ( ! $post ) {
		$output = '';
	} else {
		$title = $post->post_title;

		if ( empty( $post->post_title ) )
			$title = $previous ? __( 'Previous Post' ) : __( 'Next Post' );

		/** This filter is documented in wp-includes/post-template.php */
		$title = apply_filters( 'the_title', $title, $post->ID );

		$date = mysql2date( get_option( 'date_format' ), $post->post_date );
		$rel = $previous ? 'prev' : 'next';

		$string = '<a href="' . get_permalink( $post ) . '" rel="'.$rel.'">';
		$inlink = str_replace( '%title', $title, $link );
		$inlink = str_replace( '%date', $date, $inlink );
		$inlink = $string . $inlink . '</a>';

		$output = str_replace( '%link', $inlink, $format );
	}

	$adjacent = $previous ? 'previous' : 'next';

	/**
	 * Filter the adjacent post link.
	 *
	 * The dynamic portion of the hook name, $adjacent, refers to the type
	 * of adjacency, 'next' or 'previous'.
	 *
	 * @since 2.6.0
	 *
	 * @param string  $output The adjacent post link.
	 * @param string  $format Link anchor format.
	 * @param string  $link   Link permalink format.
	 * @param WP_Post $post   The adjacent post.
	 */
	return apply_filters( "{$adjacent}_post_link", $output, $format, $link, $post );
}

/**
 * Display adjacent post link.
 *
 * Can be either next post link or previous.
 *
 * @since 2.5.0
 * @uses get_fixed_adjacent_post_link()
 *
 * @param string       $format         Link anchor format.
 * @param string       $link           Link permalink format.
 * @param bool         $in_same_cat    Optional. Whether link should be in a same category.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded category IDs.
 * @param bool         $previous       Optional. Whether to display link to previous or next post. Default true.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return string
 */
function fixed_adjacent_post_link( $format, $link, $in_same_cat = false, $excluded_terms = '', $previous = true, $taxonomy = 'category' ) {
	echo get_fixed_adjacent_post_link( $format, $link, $in_same_cat, $excluded_terms, $previous, $taxonomy );
}
