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
		add_action( 'admin_menu', array( __CLASS__, 'add_plugin_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'page_init' ) );
		if ( ! get_option( 'newspack_electionkit_google_api_key', null ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'activation_nag' ) );
		}
		if ( ! get_option( 'newspack_electionkit_ballotpedia_api_key', null ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'activation_nag_ballotpedia' ) );
		}
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
			array( __CLASS__, 'create_admin_page' )
		);
	}

	/**
	 * Options page callback
	 */
	public static function create_admin_page() {

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Election Kit Settings', 'newspack-electionkit' ); ?></h1>
			<form method="post" action="options.php">
			<?php
				settings_fields( 'newspack_electionkit_options_group' );
				do_settings_sections( 'newspack-electionkit-settings-admin' );
				submit_button();
				printf(
					wp_kses(
						/* translators: %2$s: Set Up Wizard, %4$s: Components Demo */
						'<p><a href="%1$s">%2$s</a> | <a href="%3$s">%4$s</a> | <a href="%5$s">%6$s</a></p>',
						array(
							'p' => array(),
							'a' => array(
								'href' => array(),
							),
						)
					),
					esc_url( admin_url( 'edit.php?post_type=ek_person' ) ),
					esc_attr( __( 'Profiles', 'newspack' ) ),
					esc_url( admin_url( 'edit-tags.php?taxonomy=ek_district&post_type=ek_person' ) ),
					esc_attr( __( 'Districts', 'newspack' ) ),
					esc_url( admin_url( 'edit-tags.php?taxonomy=ek_race&post_type=ek_person' ) ),
					esc_attr( __( 'Races', 'newspack' ) )
				);
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
			array( __CLASS__, 'newspack_electionkit_google_api_key_callback' ),
			'newspack-electionkit-settings-admin',
			'newspack_electionkit_settings'
		);

		register_setting(
			'newspack_electionkit_options_group',
			'newspack_electionkit_ballotpedia_api_key'
		);

		add_settings_field(
			'newspack_electionkit_ballotpedia_api_key',
			__( 'Ballotpedia', 'newspack-electionkit' ),
			array( __CLASS__, 'newspack_electionkit_ballotpedia_api_key_callback' ),
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

	/**
	 * Render Ballotpedia Debug Checkbox.
	 */
	public static function newspack_electionkit_ballotpedia_api_key_callback() {
		$newspack_electionkit_ballotpedia_api_key = get_option( 'newspack_electionkit_ballotpedia_api_key', false );
		printf(
			'<input type="text" id="newspack_electionkit_ballotpedia_api_key" name="newspack_electionkit_ballotpedia_api_key" aria-describedby="newspack_electionkit_ballotpedia_api_key-description" value="%s" class="regular-text"/><p class="description" id="newspack_electionkit_ballotpedia_api_key-description">%s</p>',
			esc_attr( $newspack_electionkit_ballotpedia_api_key ),
			esc_html( 'This plugin requires a valid Ballotpedia API key. Please contact your Technical Account manager at Newspack for access.' )
		);
	}

	/**
	 * Add admin notice if API key is unset.
	 */
	public static function activation_nag() {
		$screen = get_current_screen();
		?>
		<div class="notice notice-warning">
			<p>
				<?php
					echo wp_kses_post(
							// translators: urge users to input their API credentials on settings page.
						__( 'Newspack Election Kit requires a Google Maps Geocoding API Key to function. You can obtain one for free following the instructions from <a href="https://developers.google.com/maps/documentation/geocoding/start" target="_blank">Google here</a>. Then please <a href="options-general.php?page=newspack-electionkit-settings-admin">go to settings</a> to input your key.', 'newspack-electionkit' )
					);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Add admin notice if the Ballotpedia API key is unset.
	 */
	public static function activation_nag_ballotpedia() {
		$screen = get_current_screen();
		?>
		<div class="notice notice-warning">
			<p>
				<?php
				echo wp_kses_post(
				// translators: urge users to input their API credentials on settings page.
					__( 'Newspack Election Kit requires a Ballotpedia API Key to function. Please contact your Technical Account Manager to obtain one. Then please <a href="options-general.php?page=newspack-electionkit-settings-admin">go to settings</a> to input your key.', 'newspack-electionkit' )
				);
				?>
			</p>
		</div>
		<?php
	}
}

if ( is_admin() ) {
	Newspack_Electionkit_Settings::init();
}
