<?php
/**
 * Establish a relationship between a taxonomy and a custom post type such that
 * there is a one to one relation between each taxonomy term and cpt post.
 *
 * add_relationship() sets up the relationships
 * get_related_post() returns term's post for storing and returning related meta
 * get_related_term() returns related term (for ex: to base a related posts query on) if we are displaying that post
 *
 */

namespace Term_Data_Store;

// This library is intended to be used in several plugins. Make sure it hasn't already loaded.
if ( ! function_exists( 'Term_Data_Store\\add_relationship' ) ) {


class Invalid_Input_Exception extends \Exception {
}


/**
 * Sets up a term data storage relationship between a post type and a taxonomy
 *
 * This relationship will keep a post type and a term in a 1:1 synced relation,
 * ensuring that no terms or posts ever exist without a matching entity of the
 * other type.
 *
 * First the function makes sure get_post_type_object() and get_taxonomy()
 * return something and then checks get_relationship() for the post type and for
 * the taxonomy to make sure that neither is already in a relationship.
 *
 * The function adds a hook for save_post. The callback for the hook is the
 * return value from get_save_post_hook().
 *
 * Then it adds a hook for create_$taxonomy. The callback for the hook is the
 * return value from get_create_term_hook().
 *
 * Finally, this function adds the relationship to the get_relationship static
 * variable by providing the post_type as the first argument and the taxonomy as
 * the second.
 *
 * @uses get_post_type_object()
 * @uses get_taxonomy()
 * @uses get_relationship()
 * @uses add_action() Adds the return of get_save_post_hook() to save_post (2 arguments)
 * @uses add_action() Adds the return of get_create_term_hook() to "create_$taxonomy"
 *
 * @throws Invalid_Input_Exception If either post_type or taxonomy is invalid
 *
 * @param string $post_type The post type slug
 * @param string $taxonomy  The taxonomy slug
 */
function add_relationship( $post_type, $taxonomy ) {

	if ( ! get_post_type_object( $post_type ) ) {
		throw new Invalid_Input_Exception( __FUNCTION__ . '() invalid post_type input.' );
	}

	if ( ! get_taxonomy( $taxonomy ) ) {
		throw new Invalid_Input_Exception( __FUNCTION__ . '() invalid taxonomy input.' );
	}

	$post_type_relationships = get_relationship( $post_type );
	$taxonomy_relationships  = get_relationship( $taxonomy );
	if ( ! empty( $post_type_relationships ) && ! empty( $taxonomy_relationships ) ) {
		throw new Invalid_Input_Exception( __FUNCTION__ . '() post_type and taxonomy already have relationships.' );
	} elseif ( ! empty( $post_type_relationships ) && empty( $taxonomy_relationships ) ) {
		throw new Invalid_Input_Exception( __FUNCTION__ . '() post_type already has a relationship.' );
	} elseif ( empty( $post_type_relationships ) && ! empty( $taxonomy_relationships ) ) {
		throw new Invalid_Input_Exception( __FUNCTION__ . '() taxonomy already has a relationship.' );
	}
	unset( $post_type_relationships, $taxonomy_relationships );

	add_action( 'save_post', get_save_post_hook( $post_type, $taxonomy ), 10, 2 );
	add_action( 'create_' . $taxonomy, get_create_term_hook( $post_type, $taxonomy ) );
	get_relationship( $post_type, $taxonomy );

}

/**
 * Get the name of an object's corresponding type
 *
 * This function stores a static variable containing an associative array with
 * post types as keys and taxonomies as values. This function will return the
 * taxonomy name if $for exists as a post type (i.e. a key in the array) or the
 * post type name if $for exists as a taxonomy (i.e. a value found using
 * array_search()). Otherwise it returns null
 *
 * @internal If the function is called with a non-null value for $set, add the value to the array using $for as the key and $set as the value.
 *
 * @param string      $for The name of the object type (post type or taxonomy) to get
 * @param string|null $set Used internally. Ignore
 *
 * @return string|null The corresponding value
 */
function get_relationship( $for, $set = null ) {

	static $post_type_relationships = array();

	if ( isset( $set ) ) {
		return $post_type_relationships[$for] = $set;
	}

	if ( ! empty( $post_type_relationships[$for] ) ) {
		return $post_type_relationships[$for];
	}

	$search = array_search( $for, $post_type_relationships );
	if ( ! empty( $search ) ) {
		return $search;
	}

	return null;

}

/**
 * Returns a boolean to indicate whether a relationship is currently being balanced
 *
 * This allows the plugin to create posts and terms without triggering an
 * infinite loop of creating posts and terms from each other. The function uses
 * a static variable to store the current balancing status. To set it, pass a
 * variable as an argument. The function will check for any arguments and use
 * the first one cast as a boolean as the static value.
 *
 *
 * @return bool Whether a relationship is currently being balanced
 */
function balancing_relationship() {

	static $balancing_status = false;

	if ( func_num_args() ) {
		$balancing_status = (boolean) func_get_arg( 0 );
	}

	return $balancing_status;

}

/**
 * Returns a closure to be used as the callback hooked to save_post
 *
 * If balancing_relationship() returns true, this function does nothing.
 * Otherwise it will set balancing_relationship to true before starting and back
 * to false at the end of the function.
 *
 * The closure will receive the post_type and taxonomy values through its use
 * statement so that it will have the necessary data to filter out posts created
 * for other post types and will know which taxonomy to check and create terms
 * for.
 *
 * The function stores references to the closures in a static variable using the
 * md5 hash of "$post_type|$taxonomy" to generate the key. If that value exists,
 * return it instead of creating a new copy.
 *
 * The closure that this function generates receives two arguments ($post_id and
 * $post) and does the following:
 *   If $post->post_type is $post_type and $post->post_status is 'publish' and
 *   get_the_terms() is empty:
 *     Create a new term in $taxonomy with the same name and slug as the post
 *     and 'tag' the post with that term using wp_set_object_terms().
 *
 * @uses balancing_relationship()
 * @uses get_the_terms()
 * @uses wp_insert_term()
 * @uses wp_set_object_terms()
 *
 * @param string $post_type
 * @param string $taxonomy
 *
 * @return \Closure The callback
 */
function get_save_post_hook( $post_type, $taxonomy ) {

	static $existing_closures;
	if ( ! isset( $existing_closures ) ) {
		$existing_closures = array();
	}

	$md5 = md5( $post_type . '|' . $taxonomy );
	if ( isset( $existing_closures[$md5] ) ) {
		return $existing_closures[$md5];
	}

	$closure = function ( $post_id, $post ) use ( $post_type, $taxonomy ) {
		if ( apply_filters( 'tds_balancing_from_post', balancing_relationship(), $post_type, $taxonomy, $post ) ) {
			return;
		}
		if ( empty( $post ) || $post_type !== $post->post_type || ( 'publish' !== $post->post_status ) || get_the_terms( $post_id, $taxonomy ) ) {
			return;
		}
		balancing_relationship( true );
		if ( function_exists( '' ) ) {
			$term = wpcom_vip_get_term_by( 'slug', $post->post_name, $taxonomy, ARRAY_A );
		} else {
			$term = get_term_by( 'slug', $post->post_name, $taxonomy, ARRAY_A );
		}
		if( !$term )
		{
			$term = wp_insert_term( $post->post_title, $taxonomy, array( 'slug' => $post->post_name ) );
			if( is_wp_error( $term ) ) {
				throw new \Exception( 'Error creating a term: ' . implode( ', ', $term->get_error_messages() ) . ' Slug: ' . $post->post_name . ' / Title: ' . $post->post_title );
			}
		}

		if( is_object( $term ) ) {
			$term = (array) $term;
		}

		wp_set_object_terms( $post->ID, (int) $term['term_id'], $taxonomy );
		balancing_relationship( false );
	};

	$existing_closures[$md5] = $closure;
	return $closure;

}

/**
 * Returns a closure to be used as the callback hooked to save_post
 *
 * If balancing_relationship() returns true, this function does nothing.
 * Otherwise it will set balancing_relationship to true before starting and back
 * to false at the end of the function.
 *
 * The closure will receive the post_type and taxonomy values through its use
 * statement so that it will be aware of which taxonomy the term was created in
 * and which post type to create a post for.
 *
 * The function stores references to the closures in a static variable using the
 * md5 hash of "$post_type|$taxonomy" to generate the key. If that value exists,
 * return it instead of creating a new copy.
 *
 * The closure that this function generates receives one argument ($term_id) and
 * does the following:
 *   If get_objects_in_term() for the term id and the taxonomy is not a non-
 *   empty array:
 *     Get the term using get_term(). Create a new post of post type $post_type
 *     with the same title and slug as the term and 'tag' the post with the term
 *     using wp_set_object_terms().
 *
 * @uses balancing_relationship()
 * @uses get_objects_in_term()
 * @uses get_term()
 * @uses wp_insert_post()
 * @uses wp_set_object_terms()
 *
 * @param string $post_type
 * @param string $taxonomy
 *
 * @return \Closure The callback
 */
function get_create_term_hook( $post_type, $taxonomy ) {

	static $existing_closures;
	if ( ! isset( $existing_closures ) ) {
		$existing_closures = array();
	}

	$md5 = md5( $post_type . '|' . $taxonomy );
	if ( isset( $existing_closures[$md5] ) ) {
		return $existing_closures[$md5];
	}

	$closure = function ( $term_id ) use ( $post_type, $taxonomy ) {
		if ( apply_filters( 'tds_balancing_from_term', balancing_relationship(), $taxonomy, $post_type, $term_id ) ) {
			return;
		}
		if ( empty( $term_id ) ) {
			return;
		}
		balancing_relationship( true );
		$term_objects = get_objects_in_term( $term_id, $taxonomy );
		if ( empty( $term_objects ) ) {
			$term    = get_term( $term_id, $taxonomy );
			$post_id = wp_insert_post( array(
				'post_type' => $post_type,
				'post_title'  => $term->name,
				'post_name'   => $term->slug,
				'post_status' => 'publish',
			) );
			wp_set_object_terms( $post_id, $term_id, $taxonomy );
		}
		balancing_relationship( false );
	};

	$existing_closures[$md5] = $closure;
	return $closure;

}

/**
 * Takes a term object and returns a post object related to it
 *
 * If $term is an ID the term is fetched using get_term(). If that function does
 * not return a valid term object, the function returns null. Otherwise, get the
 * related post type using get_relationship() and grab the most recent published
 * post of that post type and return it.
 *
 * @uses get_term()
 * @uses is_wp_error()
 * @uses get_relationship()
 * @uses get_posts()
 *
 * @param object|int  $term     The term object or a term id WITH a taxonomy
 * @param string|null $taxonomy The taxonomy (required if $term is an ID)
 *
 * @return \WP_Post|null
 */
function get_related_post( $term, $taxonomy = null ) {

	if ( is_int( $term ) ) {
		if ( ! empty( $taxonomy ) ) {
			$term = get_term( $term, $taxonomy );
		} else {
			return null;
		}
	}

	if ( is_wp_error( $term ) || ! is_object( $term ) ) {
		return null;
	}

	$post_type = get_relationship( $term->taxonomy );

	if ( ! empty( $post_type ) ) {
		$posts = new \WP_Query( array(
			'TDS_source'          => 'get_related_post', // so I can filter just this query
			'post_type'           => $post_type,
			'posts_per_page'      => 1,
			'tax_query'           => array( array(
				'taxonomy'        => $term->taxonomy,
				'field'           => 'id',
				'terms'           => $term->term_id
			) ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true
		) );
	}

	return $posts->post_count == 0 ? null : $posts->post;
}

/**
 * Takes a post object (or ID) and returns a term object related to it
 *
 * First $post is run through get_post() to ensure it's a valid post object. If
 * it's not, null is returned. Then the terms are fetched using get_the_terms().
 * If a non-empty array is not returned, null is returned. Otherwise, the first
 * element of the array is returned.
 *
 * @uses get_post()
 * @uses get_the_terms()
 * @uses is_wp_error()
 *
 * @param \WP_Post|int $post The post or post id
 *
 * @return object|null
 */
function get_related_term( $post ) {

	$post = get_post( $post );
	if ( empty( $post ) ) {
		return;
	}

    $terms = get_the_terms( $post->ID, get_relationship( $post->post_type ) );

	if ( is_wp_error( $terms ) || ! $terms ) {
		return null;
	}

	if ( is_array( $terms ) && count( $terms ) > 0 ) {
		return reset( $terms );
	}

	return null;

}

}