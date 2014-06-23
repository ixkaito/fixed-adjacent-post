=== Fixed Adjacent Post ===
Tags: adjacent, post, links, previous post, next post
Requires at least: 3.9-RC1
Tested up to: 3.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Fix the not-excluding terms bug of `get_adjacent_post()`.

Usage:

* `get_fixed_previous_post( $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' )`
	- alternatively to `get_previous_post()`

* `get_fixed_next_post( $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' )`
	- alternatively to `get_next_post()`

* `get_fixed_adjacent_post( $in_same_term = false, $excluded_terms = '', $previous = true, $taxonomy = 'category' )`
	- alternatively to `get_adjacent_post()`

* `get_fixed_adjacent_post_rel_link( $title = '%title', $in_same_term = false, $excluded_terms = '', $previous = true, $taxonomy = 'category' )`
	- alternatively to `get_adjacent_post_rel_link()`

* `fixed_adjacent_posts_rel_link( $title = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' )`
	- alternatively to `adjacent_posts_rel_link()`

* `fixed_adjacent_posts_rel_link_wp_head()`
	- alternatively to `adjacent_posts_rel_link_wp_head()`

* `fixed_next_post_rel_link( $title = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' )`
	- alternatively to `next_post_rel_link()`

* `fixed_prev_post_rel_link( $title = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' )`
	- alternatively to `prev_post_rel_link()`

* `get_fixed_previous_post_link( $format = '&laquo; %link', $link = '%title', $in_same_cat = false, $excluded_terms = '', $taxonomy = 'category' )`
	- alternatively to `get_previous_post_link()`

* `fixed_previous_post_link( $format = '&laquo; %link', $link = '%title', $in_same_cat = false, $excluded_terms = '', $taxonomy = 'category' )`
	- alternatively to `previous_post_link()`

* `get_fixed_next_post_link( $format = '%link &raquo;', $link = '%title', $in_same_cat = false, $excluded_terms = '', $taxonomy = 'category' )`
	- alternatively to `get_next_post_link()`

* `fixed_next_post_link( $format = '%link &raquo;', $link = '%title', $in_same_cat = false, $excluded_terms = '', $taxonomy = 'category' )`
	- alternatively to `next_post_link()`

* `get_fixed_adjacent_post_link( $format, $link, $in_same_cat = false, $excluded_terms = '', $previous = true, $taxonomy = 'category' )`
	- alternatively to `get_adjacent_post_link()`

* `fixed_adjacent_post_link( $format, $link, $in_same_cat = false, $excluded_terms = '', $previous = true, $taxonomy = 'category' )`
	- alternatively to `adjacent_post_link()`

== Installation ==

1. Upload the `fixed-adjacent-post` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the Plugins menu in WordPress.
3. Then you can use the above functions in your themes.