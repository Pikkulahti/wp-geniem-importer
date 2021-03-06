<?php
/**
 * Plugin localization controller.
 */

namespace Geniem\Importer\Localization;

// Classes
use Geniem\Importer\Post as Post;
use Geniem\Importer\Api as Api;
use Geniem\Importer\Exception\PostException as PostException;
use Geniem\Importer\Errors as Errors;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Localization
 *
 * @package Geniem\Importer
 */
class Controller {

    /**
     * Saves plugin with your installed WordPress translation plugin.
     * The actual locale saving is happening in Plugin specific classes
     * Geniem\Importer\Localization\Polylang and Geniem\Importer\Localization\WPML
     *
     * @param Post $post Instance of the Post class.
     *
     * @return boolean
     */
    public static function save_locale( &$post ) {

        // Check which translation plugin should be used
        $activated_i18n_plugin = self::get_activated_i18n_plugin( $post );

        // If no translation plugin was detected.
        if ( $activated_i18n_plugin === false ) {
            return false;
        }

        // If Polylang is activated use Polylang.
        if ( $activated_i18n_plugin === 'polylang' ) {
            Polylang::save_pll_locale( $post );
        }

        // If WPML is activated use WPML.
        if ( $activated_i18n_plugin === 'wpml' ) {
            // @todo : handle WPML translations in geniem-importer/Localization/WPML.php
            // WPML::save_wpml_locale( $post_id, $i18n );
        }
    }

    /**
     * Checks which translation plugin to use.
     * On success returns slug of supported WordPress translation plugins. 'wpml', 'polylang'
     * if translation plugin is not found returns false.
     *
     * @param Post $post The current importer object.
     *
     * @return string|boolean
     */
    public static function get_activated_i18n_plugin( &$post ) {

        // Checks if Polylang is installed and activated
        $polylang_activated = function_exists( 'PLL' );

        // If Polylang is activated use Polylang
        if ( $polylang_activated === true ) {
            return 'polylang';
        }

        /**
         * Checks if WPML is active
         * Polylang includes WPML api and WPML functions so we need to be more specific with WMPL.
         */
        $wpml_activated = defined( 'ICL_SITEPRESS_VERSION' );

        // If WPML is activated use WPML
        if ( $wpml_activated === true ) {
            return 'wpml';
        }

        // If Polylang or wpml is not active leave an error message for debugging
        if ( $polylang_activated === false && $wpml_activated === false ) {
            // Show an error if translation engines aren't activated and user is willing to translate
            $err = __( 'Error, translation plugin does not seem to be activated. Please install and activate your desired translation plugin to start translations.', 'geniem-importer' );
            $post->set_error( 'i18n', '', $err );
            return false;
        }
    }

}
