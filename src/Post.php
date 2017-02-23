<?php
/**
 * The Post class is used to import posts into WordPres.
 */

namespace Geniem\Importer;

use Geniem\Importer\Exception\PostException as PostException;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Class Post
 *
 * @package Geniem\Importer
 */
class Post {

    /**
     * A unique id for external identification.
     *
     * @var string
     */
    protected $gi_id;

    /**
     * If this is an existing posts, the WP id is stored here.
     *
     * @var int|boolean
     */
    protected $post_id;

    /**
     * An object resembling the WP_Post class instance.
     *
     * @var object The post data object.
     */
    protected $post;

    /**
     * Metadata in an associative array.
     *
     * @var array
     */
    protected $meta = [];

    /**
     * Taxonomies in a multidimensional associative array.
     *
     * @see $this->set_taxonomies() For description.
     *
     * @var array
     */
    protected $taxonomies = [];

    /**
     * An array for Polylang locale data.
     *
     * @var array
     */
    protected $pll = [];

    /**
     * An array of Advanced Custom Fields data.
     *
     * @var array
     */
    protected $acf = [];

    /**
     * Error messages under correspondings scopes as the key.
     *
     * Example:
     *      [
     *          'post' => [
     *              'post_title' => 'The post title is not valid.'
     *          ]
     *      ]
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Post constructor.
     */
    public function __construct( $gi_id = null ) {
        if ( null === $gi_id ) {
           $this->set_error( 'id', 'gi_id', __( 'A unique id must be set for the Post constructor.', 'geniem-importer' ) );
        } else {
            // Fetch the WP post id, if it exists.
            $this->post_id = self::get_post_id( $gi_id );
            if ( $this->post_id ) {
                // Fetch the existing WP post object.
                $this->post = get_post( $this->post_id );
            }
        }
    }

    /**
     * Returns the instance errors.
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Sets the basic data of a post.
     *
     * @param WP_Post|object $post_obj Post object.
     */
    public function set_post( $post_obj ) {

        // If the post already exists, update values.
        if ( ! empty( $this->post ) ) {
            foreach ( get_object_vars( $post_obj ) as $attr => $value ) {
                $this->post->{$attr} = $value;
            }
        } else {
            // Set the post object.
            $this->post     = new \WP_Post( $post_obj );
            $this->post_id  = null;
        }

        // Filter values before validating.
        foreach ( get_object_vars( $this->post ) as $attr => $value ) {
            $this->post->{$attr} = apply_filters( "geniem_importer_post_values_{$attr}", $value );
        }

        // Validate it.
        $this->validate_post( $this->post );
    }

    /**
     * Validates the post object data.
     *
     * @param WP_Post $post_obj An WP_Post instance.
     */
    public function validate_post( $post_obj ) {
        $errors = [];

        // TODO!

        if ( ! empty( $errors ) ) {
            $this->set_error( 'post', $errors );
        }
    }

    /**
     * Sets the post meta data.
     *
     * @param array $meta_data The meta data in an associative array.
     */
    public function set_meta( $meta_data ) {
        $this->meta = $meta_data;
        $this->validate_meta( $this->meta );
    }

    /**
     * Validate postmeta.
     */
    public function validate_meta( $meta ) {
        $errors = [];

        // TODO!

        if ( ! empty( $errors ) ) {
            $this->set_error( 'meta', $errors );
        }
    }

    /**
     * Set the taxonomies of the post.
     * The taxonomies must be passed as an associative array
     * where the key is the taxonomy slug and values are associative array
     * with the name and the slug of the taxonomy term.
     * Example:
     *      $tax_array = [
     *          'category' => [
     *              [
     *                  'name' => 'My category',
     *                  'slug' => 'my-category',
     *              ]
     *      ];
     *
     * @param array $tax_array The taxonomy data.
     */
    public function set_taxonomies( $tax_array = [] ) {
        $this->taxonomies = $tax_array;
        $this->validate_taxonomies( $this->taxonomies );
    }

    /**
     * @param $taxonomies
     */
    public function validate_taxonomies( $taxonomies ) {
        $errors = [];

        foreach ( $taxonomies as $taxonomy ) {
            // TODO!!!
        }

        if ( ! empty( $errors ) ) {
            $this->set_error( 'taxonomies', $errors );
        }
    }

    /**
     * Stores the post instance and all its data into the database.
     *
     * @throws PostException If the post data is not valid.
     */
    public function save() {
        if ( ! $this->is_valid() ) {
            // Store the invalid data for later access.
            $key          = Settings::get_setting( 'GI_TRANSIENT_KEY' ) . 'invalid_post_' . $this->gi_id;
            $expiration   = Settings::get_setting( 'GI_TRANSIENT_EXPIRATION' );
            set_transient( $key, get_object_vars( $this ), $expiration );

            throw new PostException( __( 'The post data is not valid.', 'geniem-importer' ), 0, $this->get_errors() );
        }

        $post_arr = (array) $this->post;

        // Add the final post data filtering for imports.
        add_filter( 'wp_insert_post_data', [ __CLASS__, 'pre_post_save' ], 1 );

        // Run the WP save function.
        $post_id = wp_insert_post( $post_arr );

        // Identify the post, if not yet done.
        if ( empty( $this->post_id ) ) {
            $this->post_id = $post_id;
            $this->identify();
        }

        // Save metadata.
        if ( ! empty( $this->meta ) ) {
            $this->save_meta();
        }

        // Save taxonomies.
        if ( ! empty( $this->taxonomies ) ) {
            $this->save_taxonomies();
        }

        // Remove the filter to prevent filtering data from other than importer inserts.
        remove_filter( 'wp_insert_post_data', [ __CLASS__, 'pre_post_save' ] );
    }

    /**
     * Saves the metadata of the post.
     */
    protected function save_meta() {
        if ( is_array( $this->meta ) ) {
            foreach ( $this->meta as $meta_obj ) {
                update_post_meta( $this->post_id, $meta_obj->meta_key, $meta_obj->meta_value );
            }
        }
    }

    /**
     * Sets the terms of a post and create taxonomy terms
     * if they do not exist yet.
     */
    protected function save_taxonomies() {
        if ( is_array( $this->taxonomies ) ) {
            foreach ( $this->taxonomies as $taxonomy => $terms ) {
                if ( is_array( $terms ) ) {
                    foreach ( $terms as &$term ) {
                        $name       = $term['name'];
                        $slug       = $term['slug'];
                        $term_obj   = get_term_by( 'slug', $slug, $taxonomy );

                        // If the term does not exist, create it.
                        if ( ! $term_obj ) {

                            // There might be a parent set.
                            $parent = isset( $term['parent'] ) ?: get_term_by( 'slug', $term['parent'], $taxonomy );

                            // Insert the new term.
                            $result = wp_insert_term(
                                $name,
                                $taxonomy,
                                [
                                    'slug'          => $slug,
                                    'description'   => isset( $term['description'] ) ?: $term['description'],
                                    'parent'        => $parent ? $parent->term_id : 0,
                                ]
                            );

                            // Something went wrong.
                            if ( is_wp_error( $result ) ) {
                                self::set_error( 'taxonomy', $name, __( 'An error occurred creating the taxonomy term.', 'geniem_importer' ) );
                            }

                            // We only need the id.
                            $term_obj           = (object) [];
                            $term_obj->term_id  = $result['term_id'];
                        }

                        // Set the term and store the result.
                        $term['success'] = $wp_set_object_terms( $this->post_id, $term_obj->term_id, $taxonomy );
                    }
                }
            }
        }
    }

    /**
     * Adds postmeta rows for matching a WP post with an external source.
     */
    protected function identify() {
        $id_prefix = Settings::get_setting( 'GI_ID_PREFIX' );
        // Remove the trailing '_'.
        $identificator = rtrim( $id_prefix, '_' );
        // Set the queryable identificator.
        // Example: meta_key = 'gi_id', meta_value = 12345
        update_post_meta( $this->post_id, $identificator, $this->gi_id );
        // Set the indexed indentificator.
        // Example: meta_key = 'gi_id_12345', meta_value = 12345
        update_post_meta( $this->post_id, $id_prefix . $this->gi_id, $this->gi_id );
    }

    /**
     * Checks whether the current post is valid.
     *
     * @return bool
     */
    protected function is_valid() {
        if ( empty( $this->errors ) ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Fetches a post id for a given external id.
     *
     * @param string $gi_id The external id.
     *
     * @return int|bool The post id, if found, false if not.
     */
    public static function get_post_id( $gi_id ) {
        global $wpdb;

        $id_prefix  = Settings::get_setting( 'GI_ID_PREFIX' );
        $query      = 'SELECT DISTINCT post_id FROM wp_postmeta WHERE meta_key = %s';
        $results    = $wpdb->get_col( $wpdb->prepare( $query, $id_prefix . $gi_id ) );
        if ( count( $results ) ) {
            return $results[0];
        } else {
            return false;
        }
    }

    /**
     * This function creates a filter for the 'wp_insert_posts_data' hook
     * which is enabled only while importing post data with Geniem Importer.
     *
     * @param $post_data
     *
     * @return mixed|void
     */
    public static function pre_post_save( $post_data ) {
        return apply_filters( 'geniem_importer_post_pre_save', $post_data );
    }

    /**
     * Sets a single error message or a full error array depending on the $key value.
     *
     * @param string        $scope The error scope.
     * @param string|array  $key   The key or the error array.
     * @param string        $error The error message.
     */
    protected function set_error( $scope = '', $key = '', $error = '' ) {
        $this->errors[ $scope ] = isset( $this->errors[ $scope ] ) ? $this->errors[ $scope ] : [];
        if ( is_array( $key ) ) {
            // Set the full error array.
            $this->errors[ $scope ] = $key;
        } else {
            $this->errors[ $scope ][ $key ] = $error;
        }
    }
}