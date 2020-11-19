<?php
/**
 * Plugin Name: Newspack Election Kit
 * Plugin URI: https://willenglishiv.com/newspack-election-kit
 * Description: Provides a sample ballot for Newspack Customers.  Install on the page of your choice with the shortcode [sample_ballot]
 * Author: Will English IV
 * Author URI: https://willenglishiv.com/
 * Version: 1.0.0
 * Text Domain: newspack-electionkit
 *
 * @package Newspack Election Kit
 * @version 0.0.0
 */

/**
 * Generate ballot form markup.
 *
 * @param array $atts Array of attributes.
 */
function np_sample_ballot_form( $atts ) {
	$a = shortcode_atts(
		array(
			'show_bios'      => 'false',
			'debug_location' => '',
		),
		$atts
	);
	ob_start(); ?>

	<div class="newspack-electionkit">
		<form class="address-form">
			<input type="hidden" id="ek-show-bio" name="ek-show-bio" value="<?php echo esc_attr( $a['show_bios'] ); ?>">
			<label for="ek-address"><?php esc_html_e( "Enter the address where you're registered to vote:", 'newspack-electionkit' ); ?></label>
			<span>
				<input type="text" id="ek-address" name="ek-address" value="<?php echo esc_attr( $a['debug_location'] ); ?>" required>
				<input type="submit" value="<?php esc_attr_e( 'Submit', 'newspack-electionkit' ); ?>">
			</span>
		</form>
		<div class="ek-credit">
			<?php
				$credit_allowed_html = array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
					),
				);

				echo wp_kses(
					__( 'This sample ballot tool originated as a project of <a href="https://www.thechicagoreporter.com" target="_blank">The Chicago Reporter</a> and is provided with support from <a href="https://newspack.pub/" target="_blank">Newspack</a> and the <a href="https://www.americanpressinstitute.org/" target="_blank">American Press Institute</a>. Candidate data is sourced from <a href="https://ballotpedia.org/Main_Page" target="_blank">Ballotpedia</a>.', 'newspack-electionkit' ),
					$credit_allowed_html
				);
			?>
		</div>
		<div class="spinner"><img alt="spinner" src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'img/25.gif' ); ?>"></div>
		<div class="ek-error"><?php esc_html_e( 'There was an error retrieving the sample ballot.  Please try again later.', 'newspack-electionkit' ); ?></div>
		<div class="sample-ballot"></div>
	</div>

	<?php
	return ob_get_clean();
}
add_shortcode( 'sample_ballot', 'np_sample_ballot_form' );

/**
 * Generate sample ballot.
 */
function np_sample_ballot() {
	$google_maps_api_url_legacy = defined( 'NEWSPACK_ELECTIONKIT_GOOGLE_API_KEY' ) ? NEWSPACK_ELECTIONKIT_GOOGLE_API_KEY : null;
	$election_date              = '2020-11-03';
	$google_api_key             = get_option( 'newspack_electionkit_google_api_key', $google_maps_api_url_legacy );
	$google_maps_api_url        = 'https://maps.googleapis.com/maps/api/geocode/json';
	$bp_sample_ballot_elections = 'https://api4.ballotpedia.org/myvote_elections';
	$bp_sample_ballot_results   = 'https://api4.ballotpedia.org/myvote_results';
	$response                   = array();
	$ballot_measures_on_top     = false;
	$ballotpedia_api_key        = get_option( 'newspack_electionkit_ballotpedia_api_key', false );
	$bp_request_headers         = array(
		'headers' => array(
			'x-api-key' => $ballotpedia_api_key,
		),
	);

	if ( ! $google_api_key ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'No Google API key. Please add to Settings->Election Kit in wp-admin as described in plugin README.', 'newspack-electionkit' ),
			)
		);
	}

	if ( ! $ballotpedia_api_key ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'No Ballotpedia API key. Please add to Settings->Election Kit in wp-admin as described in plugin README.', 'newspack-electionkit' ),
			)
		);
	}

	$address = ! empty( $_REQUEST['address'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['address'] ) ) : null; //phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$google_compose_url = $google_maps_api_url . '?' . http_build_query(
		array(
			'address' => $address,
			'key'     => $google_api_key,
		)
	);

	$show_bios = ! empty( $_REQUEST['show_bios'] ) && 'true' === $_REQUEST['show_bios']; //phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$google_request = wp_safe_remote_get( $google_compose_url );
	$google_data    = '';

	if ( is_wp_error( $google_request ) ) {
		wp_send_json_error( "google didn't work" );
	} else {
		$google_data = json_decode( wp_remote_retrieve_body( $google_request ) );
	}

	if ( 'OK' !== $google_data->status ) {
		wp_send_json_error(
			array(
				'message' => "google didn't return any data",
			)
		);
	}

	// Country check.
	$location_result = $google_data->results[0];

	$in_the_united_states = false;
	foreach ( $location_result->address_components as $component ) {
		if ( 'United States' === $component->long_name ) {
			$in_the_united_states = true;
		}
	}

	if ( ! $in_the_united_states ) {
		wp_send_json_error(
			array(
				'message'        => esc_html__( 'Address did not return a valid US location.', 'newspack-electionkit' ),
				'locationResult' => $location_result,
			)
		);
	}

	$bp_compose_url = $bp_sample_ballot_elections . '?' . http_build_query(
		array(
			'lat'  => $google_data->results[0]->geometry->location->lat,
			'long' => $google_data->results[0]->geometry->location->lng,
		)
	);

	$bp_districts_request = wp_safe_remote_get( $bp_compose_url, $bp_request_headers );
	$bp_district_data     = '';
	$bp_district_array    = array();

	if ( is_wp_error( $bp_districts_request ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( "Ballotpedia sample ballot elections call didn't work.", 'newspack-electionkit' ),
			)
		);
	} else {
		$bp_district_data = json_decode(
			wp_remote_retrieve_body( $bp_districts_request )
		);
	}

	if ( ! $bp_district_data || ! $bp_district_data->success ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( "Ballotpedia sample ballot elections didn't return any data.", 'newspack-electionkit' ),
			)
		);
	}

	foreach ( $bp_district_data->data->districts as $district ) {
		$bp_district_array[] = $district->id;
	}

	$bp_compose_url = $bp_sample_ballot_results . '?' . http_build_query(
		array(
			'districts'     => implode( ',', $bp_district_array ),
			'election_date' => $election_date,
		)
	);

	$bp_ballot_request = wp_safe_remote_get( $bp_compose_url, $bp_request_headers );
	$bp_ballot_data    = '';

	if ( is_wp_error( $bp_ballot_request ) ) {
		wp_send_json_error(
			array(
				'message'     => esc_html__( "Ballotpedia sample ballot results call didn't work.", 'newspack-electionkit' ),
				'information' => $bp_ballot_request,
			)
		);
	} else {
		$bp_ballot_data = json_decode(
			wp_remote_retrieve_body( $bp_ballot_request )
		);

	}

	if ( ! $bp_ballot_data || ! $bp_ballot_data->success ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( "Ballotpedia sample ballot results didn't return any data.", 'newspack-electionkit' ),
			)
		);
	}

	$district_order = array(
		'Country',
		'Congress',
		'State',
		'State Legislative (Upper)',
		'State Legislative (Lower)',
		'County',
		'County subdivision',
		'City',
		'City-town subdivision',
		'Judicial District',
		'Judicial district subdivision',
		'Special District',
		'School District',
	);

	$districts = $bp_ballot_data->data->districts;

	usort(
		$districts,
		function( $a, $b ) use ( $district_order ) {
			return array_search( $a->type, $district_order, true ) - array_search( $b->type, $district_order, true );
		}
	);

	$district_types  = array();
	$ballot_measures = array();

	foreach ( $districts as $district ) {
		if ( $district->ballot_measures ) {
			foreach ( $district->ballot_measures as $ballot_measure ) {
				$ballot_measures[] = $ballot_measure;
			}
		}
	}

	$response['ballot_measures'] = $ballot_measures;

	usort(
		$ballot_measures,
		function( $a, $b ) {
			return strcmp( $a->official_title, $b->official_title );
		}
	);

	ob_start();

	if ( $ballot_measures && $ballot_measures_on_top ) {
		?>
		<div class="district">
			<h2 class="district-type"><?php esc_html_e( 'Ballot Measures', 'newspack-electionkit' ); ?></h2>
			<ul class="measures">
				<?php foreach ( $ballot_measures as $ballot_measure ) { ?>
					<li class="measure-name">
						<a href="<?php echo esc_url( $ballot_measure->url ); ?>" target="_blank">
							<?php echo esc_html( $ballot_measure->name ); ?>
						</a>
					</li>
				<?php } // ballot measure foreach ?>
			</ul>
		</div>
		<?php
	} // if/else ballot_measures check

	foreach ( $districts as $district ) {
		$district_types[] = $district->type;
		if ( $district->races || $district->ballot_measures ) {
			?>
			<div class="district" data-district-id="<?php echo esc_attr( $district->id ); ?>">
				<h2 class="district-type"><?php echo esc_attr( $district->type . ' - ' . $district->name ); ?></h2>
				<?php
				foreach ( $district->races as $race ) {
					if ( $race->candidates ) {
						?>
						<div class="race">
							<h3 class="race-name">
								<?php echo esc_html( $race->office->name ); ?>
								<?php
								if ( $race->office_position ) {
									echo ' <em>(' . esc_html( $race->office_position ) . ')</em>';
								}
								?>
							</h3>
							<div class="candidates">
								<?php
								foreach ( $race->candidates as $candidate ) {
									?>
									<div class="candidate">
										<div class="candidate-image">
											<?php
											if ( $candidate->person->image ) {
												?>
												<img alt="portrait of <?php echo esc_html( $candidate->person->name ); ?>" src="<?php echo esc_url( $candidate->person->image->thumbnail ); ?>">
												<?php
											} else {
												?>
												<img alt="default candidate portrait" src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'img/person-placeholder.jpg' ); ?>">
											<?php } ?>
										</div>
										<div class="candidate-content">
											<h4 class="candidate-name">
												<a href="<?php echo esc_url( $candidate->person->url ); ?>" target="_blank">
													<?php echo esc_html( $candidate->person->name ); ?>
													<?php
													if ( $candidate->is_incumbent ) {
														?>
														<em>Incumbent</em>
													<?php } ?>
												</a>
											</h4>
											<div class="candidate-party">
												<?php
												foreach ( $candidate->party_affiliation as $party ) {
													?>
													<?php echo esc_html( $party->name ); ?>
												<?php } ?>
											</div>
											<?php
											if ( $show_bios ) {
												?>
												<div class="candidate-summary">
													<?php echo esc_html( $candidate->person->summary ); ?>
												</div>
												<?php
											}
											?>
											<div class="social">
												<?php if ( $candidate->person->contact_facebook ) { ?>
													<a href="<?php echo esc_url( $candidate->person->contact_facebook ); ?>" target="_blank" class="icon-facebook">
														<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
															<path d="M12 2C6.5 2 2 6.5 2 12c0 5 3.7 9.1 8.4 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.3v7C18.3 21.1 22 17 22 12c0-5.5-4.5-10-10-10z"></path>
														</svg>
														<span class="screen-reader-text"><?php esc_html_e( 'Facebook', 'newspack-electionkit' ); ?></span>
													</a>
													<?php
												}
												?>
												<?php
												if ( $candidate->person->contact_twitter ) {
													?>
													<a href="https://twitter.com/<?php echo esc_attr( $candidate->person->contact_twitter ); ?>" target="_blank" class="icon-twitter">
														<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
															<path d="M22.23,5.924c-0.736,0.326-1.527,0.547-2.357,0.646c0.847-0.508,1.498-1.312,1.804-2.27 c-0.793,0.47-1.671,0.812-2.606,0.996C18.324,4.498,17.257,4,16.077,4c-2.266,0-4.103,1.837-4.103,4.103 c0,0.322,0.036,0.635,0.106,0.935C8.67,8.867,5.647,7.234,3.623,4.751C3.27,5.357,3.067,6.062,3.067,6.814 c0,1.424,0.724,2.679,1.825,3.415c-0.673-0.021-1.305-0.206-1.859-0.513c0,0.017,0,0.034,0,0.052c0,1.988,1.414,3.647,3.292,4.023 c-0.344,0.094-0.707,0.144-1.081,0.144c-0.264,0-0.521-0.026-0.772-0.074c0.522,1.63,2.038,2.816,3.833,2.85 c-1.404,1.1-3.174,1.756-5.096,1.756c-0.331,0-0.658-0.019-0.979-0.057c1.816,1.164,3.973,1.843,6.29,1.843 c7.547,0,11.675-6.252,11.675-11.675c0-0.178-0.004-0.355-0.012-0.531C20.985,7.47,21.68,6.747,22.23,5.924z"></path>
														</svg>
														<span class="screen-reader-text"><?php esc_html_e( 'Twitter', 'newspack-electionkit' ); ?></span>
													</a>
												<?php } ?>
											</div>
										</div>
									</div>
								<?php } // foreach candidates ?>
							</div><!-- .candidates -->
						</div>
					<?php } // if/else blank candidates check ?>
				<?php } // foreach races ?>
				<?php
				if ( $district->ballot_measures ) {
					usort(
						$district->ballot_measures,
						function( $a, $b ) {
							return strcmp( $a->official_title, $b->official_title );
						}
					);
					?>
					<div class="race">
						<h3 class="race-name"><?php esc_html_e( 'Ballot Measures', 'newspack-electionkit' ); ?></h3>
						<ul class="measures">
							<?php foreach ( $district->ballot_measures as $ballot_measure ) { ?>
								<li class="measure-name">
									<a href="<?php echo esc_url( $ballot_measure->url ); ?>" target="_blank">
										<?php echo esc_html( $ballot_measure->name ); ?>
									</a>
								</li>
							<?php } // ballot measure foreach ?>
						</ul>
					</div>
					<?php
				}
				?>
			</div>
			<?php
		} // if/else blank races check
	} // foreach districts

	$response['ballot'] = ob_get_clean();
	wp_send_json_success( $response );

}
add_action( 'wp_ajax_sample_ballot', 'np_sample_ballot' );
add_action( 'wp_ajax_nopriv_sample_ballot', 'np_sample_ballot' );

/**
 * Adding candidate custom post type
 */
function np_candidate_cpt_setup() {

	$labels = array(
		'name'                  => _x( 'ElectionKit Profiles', 'Post Type General Name', 'newspack-electionkit' ),
		'singular_name'         => _x( 'ElectionKit Profile', 'Post Type Singular Name', 'newspack-electionkit' ),
		'menu_name'             => __( 'ElectionKit Profiles', 'newspack-electionkit' ),
		'name_admin_bar'        => __( 'ElectionKit Profile', 'newspack-electionkit' ),
		'archives'              => __( 'Profile Archives', 'newspack-electionkit' ),
		'attributes'            => __( 'Profile Attributes', 'newspack-electionkit' ),
		'parent_item_colon'     => __( 'Parent Profile:', 'newspack-electionkit' ),
		'all_items'             => __( 'All Profiles', 'newspack-electionkit' ),
		'add_new_item'          => __( 'Add New Profile', 'newspack-electionkit' ),
		'add_new'               => __( 'Add New', 'newspack-electionkit' ),
		'new_item'              => __( 'New Profile', 'newspack-electionkit' ),
		'edit_item'             => __( 'Edit Profile', 'newspack-electionkit' ),
		'update_item'           => __( 'Update Profile', 'newspack-electionkit' ),
		'view_item'             => __( 'View Profile', 'newspack-electionkit' ),
		'view_items'            => __( 'View Profiles', 'newspack-electionkit' ),
		'search_items'          => __( 'Search Profiles', 'newspack-electionkit' ),
		'not_found'             => __( 'Not found', 'newspack-electionkit' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'newspack-electionkit' ),
		'featured_image'        => __( 'Profile Image', 'newspack-electionkit' ),
		'set_featured_image'    => __( 'Set profile image', 'newspack-electionkit' ),
		'remove_featured_image' => __( 'Remove profile image', 'newspack-electionkit' ),
		'use_featured_image'    => __( 'Use as profile image', 'newspack-electionkit' ),
		'insert_into_item'      => __( 'Insert into profile', 'newspack-electionkit' ),
		'uploaded_to_this_item' => __( 'Uploaded to this profile', 'newspack-electionkit' ),
		'items_list'            => __( 'Profiles list', 'newspack-electionkit' ),
		'items_list_navigation' => __( 'Profiles list navigation', 'newspack-electionkit' ),
		'filter_items_list'     => __( 'Filter profiles list', 'newspack-electionkit' ),
	);
	$args   = array(
		'label'               => __( 'ElectionKit Profile', 'newspack-electionkit' ),
		'description'         => __( 'Profiles for candidates, judges and politicians', 'newspack-electionkit' ),
		'labels'              => $labels,
		'supports'            => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
		'taxonomies'          => array(),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'show_in_rest'        => true,
		'menu_position'       => 25,
		'show_in_admin_bar'   => true,
		'show_in_nav_menus'   => false,
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'capability_type'     => 'post',
	);
	register_post_type( 'ek_person', $args );

	$district_labels = array(
		'name'                       => _x( 'Districts', 'Taxonomy General Name', 'newspack-electionkit' ),
		'singular_name'              => _x( 'District', 'Taxonomy Singular Name', 'newspack-electionkit' ),
		'menu_name'                  => __( 'Districts', 'newspack-electionkit' ),
		'all_items'                  => __( 'All Districts', 'newspack-electionkit' ),
		'parent_item'                => __( 'Parent District', 'newspack-electionkit' ),
		'parent_item_colon'          => __( 'Parent District:', 'newspack-electionkit' ),
		'new_item_name'              => __( 'New District Name', 'newspack-electionkit' ),
		'add_new_item'               => __( 'Add New District', 'newspack-electionkit' ),
		'edit_item'                  => __( 'Edit District', 'newspack-electionkit' ),
		'update_item'                => __( 'Update District', 'newspack-electionkit' ),
		'view_item'                  => __( 'View District', 'newspack-electionkit' ),
		'separate_items_with_commas' => __( 'Separate districts with commas', 'newspack-electionkit' ),
		'add_or_remove_items'        => __( 'Add or remove districts', 'newspack-electionkit' ),
		'choose_from_most_used'      => __( 'Choose from the most used', 'newspack-electionkit' ),
		'popular_items'              => __( 'Popular Districts', 'newspack-electionkit' ),
		'search_items'               => __( 'Search Districts', 'newspack-electionkit' ),
		'not_found'                  => __( 'Not Found', 'newspack-electionkit' ),
		'no_terms'                   => __( 'No districts', 'newspack-electionkit' ),
		'items_list'                 => __( 'Districts list', 'newspack-electionkit' ),
		'items_list_navigation'      => __( 'Districts list navigation', 'newspack-electionkit' ),
	);

	$district_args = array(
		'labels'            => $district_labels,
		'hierarchical'      => true,
		'public'            => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_nav_menus' => true,
		'show_tagcloud'     => false,
		'rewrite'           => false,
	);
	register_taxonomy( 'ek_district', array( 'ek_person' ), $district_args );

	$race_labels = array(
		'name'                       => _x( 'Races', 'Taxonomy General Name', 'newspack-electionkit' ),
		'singular_name'              => _x( 'Race', 'Taxonomy Singular Name', 'newspack-electionkit' ),
		'menu_name'                  => __( 'Races', 'newspack-electionkit' ),
		'all_items'                  => __( 'All Races', 'newspack-electionkit' ),
		'parent_item'                => __( 'Parent Race', 'newspack-electionkit' ),
		'parent_item_colon'          => __( 'Parent Race:', 'newspack-electionkit' ),
		'new_item_name'              => __( 'New Race Name', 'newspack-electionkit' ),
		'add_new_item'               => __( 'Add New Race', 'newspack-electionkit' ),
		'edit_item'                  => __( 'Edit Race', 'newspack-electionkit' ),
		'update_item'                => __( 'Update Race', 'newspack-electionkit' ),
		'view_item'                  => __( 'View Race', 'newspack-electionkit' ),
		'separate_items_with_commas' => __( 'Separate races with commas', 'newspack-electionkit' ),
		'add_or_remove_items'        => __( 'Add or remove races', 'newspack-electionkit' ),
		'choose_from_most_used'      => __( 'Choose from the most used', 'newspack-electionkit' ),
		'popular_items'              => __( 'Popular Races', 'newspack-electionkit' ),
		'search_items'               => __( 'Search Races', 'newspack-electionkit' ),
		'not_found'                  => __( 'Not Found', 'newspack-electionkit' ),
		'no_terms'                   => __( 'No races', 'newspack-electionkit' ),
		'items_list'                 => __( 'Races list', 'newspack-electionkit' ),
		'items_list_navigation'      => __( 'Races list navigation', 'newspack-electionkit' ),
	);
	$race_args   = array(
		'labels'            => $race_labels,
		'hierarchical'      => false,
		'public'            => false,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_nav_menus' => true,
		'show_tagcloud'     => true,
		'rewrite'           => false,
	);
	register_taxonomy( 'ek_race', array( 'ek_person' ), $race_args );

	register_meta(
		'post',
		'political_party',
		[
			'object_subtype' => 'ek_person',
			'show_in_rest'   => true,
			'type'           => 'string',
			'single'         => true,
			'auth_callback'  => '__return_true',
		]
	);

	register_meta(
		'post',
		'telephone_number',
		[
			'object_subtype' => 'ek_person',
			'show_in_rest'   => true,
			'type'           => 'string',
			'single'         => true,
			'auth_callback'  => '__return_true',
		]
	);

}
add_action( 'init', 'np_candidate_cpt_setup', 0 );

/**
 * Enqueue editor script.
 */
function npek_enqueue_block_editor_assets() {
	wp_enqueue_script(
		'newspack-electionkit',
		plugins_url( 'dist/editor.js', __FILE__ ),
		[ 'wp-components' ],
		filemtime( dirname( __FILE__ ) . '/dist/editor.js' ),
		true
	);
}

add_action( 'enqueue_block_editor_assets', 'npek_enqueue_block_editor_assets' );

/**
 * Enqueue scripts.
 */
function np_electionkit_scripts() {
	wp_enqueue_script( 'electionkit', plugin_dir_url( __FILE__ ) . 'dist/electionkit.js', array( 'jquery' ), '1.0.0', true );

	$params = array(
		'ajaxurl'    => admin_url( 'admin-ajax.php' ),
		'ajax_nonce' => wp_create_nonce( 'electionkit-address' ),
	);
	wp_localize_script( 'electionkit', 'ajax_object', $params );

	wp_enqueue_style( 'electionkit', plugin_dir_url( __FILE__ ) . 'dist/electionkit.css', array(), '1.0.0' );
}
add_action( 'wp_enqueue_scripts', 'np_electionkit_scripts' );



if ( ! class_exists( 'Newspack_Electionkit_Settings' ) ) {
	include_once dirname( __FILE__ ) . '/includes/class-newspack-electionkit-settings.php';
}
