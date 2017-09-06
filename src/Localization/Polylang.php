<?php
/**
 * Plugin settings controller.
 */

namespace Geniem\Importer\Localization;

// Classes
use Geniem\Importer\Api as Api;
use Geniem\Importer\Settings as Settings;


defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Class Polylang
 *
 * @package Geniem\Importer
 */
class Polylang {
    /**
     * Holds polylang.
     *
     * @var object|null
     */
    protected static $polylang = null;

    /**
     * Holds polylang.
     *
     * @var object|null
     */
    protected static $languages = [];

    /**
     * Holds current attachment id.
     *
     * @var string
     */
    protected static $current_attachment_ids = [];

    /**
     * Initialize.
     */
    public static function init() {
        $polylang  = function_exists( 'PLL' ) ? PLL() : null;
        $polylang  = $polylang instanceof \PLL_Frontend
            ? $polylang
            : null;

        if ( $polylang ) {
            /**
             * Get current languages.
             * Returns list of language codes.
             */
            self::$languages = pll_languages_list();

            // media index might not be set by default
            if ( isset( $polylang->options['media'] ) ) {

                // Check if media duplication is on.
                if ( $polylang->model->options['media_support'] && $polylang->options['media']['duplicate'] ?? 0 ) {

                    // Needed for PLL_Admin_Advanced_Media
                    $polylang->filters_media     = new \PLL_Admin_Filters_Media( $polylang );

                    // Acticates media duplication
                    $polylang->gi_advanced_media = new \PLL_Admin_Advanced_Media( $polylang );
                    // Hook into media duplication so we can add attachment_id meta.
                    # add_action( 'pll_translate_media', array( __CLASS__, 'get_attachment_post_ids' ), 11, 3 );
                }
            }
            else {
                // Media is not set.
            }

            self::$polylang = $polylang;
        } // End if().
    }

    /**
     * Returns the polylang object.
     * @return object|null Polylang object.
     */
    public static function pll() {
        return self::$polylang;
    }

    /**
     * Returns the polylang language list of language codes.
     * @return array Polylang language list.
     */
    public static function language_list() {
        return self::$languages;
    }

    /**
     * [get description]
     * @param  [type] $key [description]
     * @return [type]      [description]
     */
    public static function set_attachment_language( $attachment_post_id, $attachment_id, $language ) {
        if ( $language ) {
            pll_set_post_language( $attachment_post_id, $language );
        }
    }

    /**
     * [get description]
     * @param  [type] $key [description]
     * @return [type]      [description]
     */
    public static function get_attachment_by_language( $attachment_post_id, $language ) {
        if ( isset( self::$polylang->filters_media ) ) {
            $attachment_translations = pll_get_post_translations( $attachment_post_id );
            $attachment_post_id      = $attachment_translations[ $language ] ?? $attachment_post_id;
        }
        return $attachment_post_id;
    }

    /**
     * Save Polylang locale.
     *
     * @param Geniem\Importer\Post $post The current importer post object.
     * @return void
     */
    public static function save_pll_locale( $post ) {

        // Get needed variables
        $post_id    = $post->get_post_id();
        $gi_id      = $post->get_gi_id();
        $i18n       = $post->get_i18n();

        // If pll information is in wrong format
        if ( is_array( $i18n ) ) {

            // Set post locale. 
            \pll_set_post_language( $post_id, $i18n['locale'] );

            // Check if we need to link the post to its master.
            $master_key = $i18n['master']['query_key'] ?? null;

            // If master key exists
            if ( ! empty( $master_key ) ) {

                // @todo Api check - T: What it this?
                // Get master post id for translation linking
                $gi_id_prefix   = Settings::get( 'GI_ID_PREFIX' );
                $master_id      = substr( $master_key, strlen( $gi_id_prefix ) );
                $master_post_id = Api::get_post_id_by_api_id( $master_id );

                // Set link for translations.
                if ( $master_post_id ) {

                        // Get current translation.
                        $current_translations = \pll_get_post_translations( $master_post_id );

                        // Set up new translations.
                        $new_translations = [
                            'post_id'         => $master_post_id,
                            $i18n['locale']   => $post_id,
                        ];
                        $parsed_args = \wp_parse_args( $new_translations, $current_translations );

                        // Add and link translation.
                        \pll_save_post_translations( $parsed_args );
                } // End if().
            } // End if().
        } else {
            // @todo show an error: Post doesn't have pll information in right format.

        } // End if().
    }
}
