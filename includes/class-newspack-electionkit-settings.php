<?php
/**
 * Newspack Election Kit Settings Page.
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Manages Settings page.
 */
class Newspack_Electionkit_Settings {
	/**
	 * Set up hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_plugin_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'page_init' ] );
	}

	/**
	 * Add options page
	 */
	public static function add_plugin_page() {
		add_options_page(
			__( 'Settings Admin', 'newspack-electionkit' ),
			__( 'Election Kit', 'newspack-electionkit' ),
			'manage_options',
			'newspack-electionkit-settings-admin',
			[ __CLASS__, 'create_admin_page' ]
		);
	}

	/**
	 * Options page callback
	 */
	public static function create_admin_page() {

		?>
		<div class="wrap">
			<h1><?php _e( 'Election Kit Settings', 'newspack-electionkit' ); ?></h1>
			<form method="post" action="options.php">
			<?php
				settings_fields( 'newspack_electionkit_options_group' );
				do_settings_sections( 'newspack-electionkit-settings-admin' );
				submit_button();
			?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register and add settings
	 */
	public static function page_init() {
		register_setting(
			'newspack_electionkit_options_group',
			'newspack_electionkit_google_api_key'
		);
		add_settings_section(
			'newspack_electionkit_settings',
			__( 'API Keys', 'newspack-electionkit' ),
			null,
			'newspack-electionkit-settings-admin'
		);
		add_settings_field(
			'newspack_electionkit_google_api_key',
			__( 'Google Maps', 'newspack-electionkit' ),
			[ __CLASS__, 'newspack_electionkit_google_api_key_callback' ],
			'newspack-electionkit-settings-admin',
			'newspack_electionkit_settings'
		);
	}

	/**
	 * Render Debug checkbox.
	 */
	public static function newspack_electionkit_google_api_key_callback() {
		$newspack_electionkit_google_api_key = get_option( 'newspack_electionkit_google_api_key', false );
		printf(
			'<input type="text" id="newspack_electionkit_google_api_key" aria-describedby="newspack_electionkit_google_api_key-description" name="newspack_electionkit_google_api_key" value="%s" class="regular-text" /><p class="description" id="newspack_electionkit_google_api_key-description">%s</p>',
			esc_attr( $newspack_electionkit_google_api_key ),
			wp_kses_post( 'This plugin requires a valid Google Maps Geocoding API key. You can obtain one for free following the instructions from <a href="https://developers.google.com/maps/documentation/geocoding/start" target="_blank">Google here</a>.' )
		);
	}
}

if ( is_admin() ) {
	Newspack_Electionkit_Settings::init();
}
