<?php
/**
 * Newspack Election Kit Profile Helper Functions
 *
 * @package Newspack
 */

namespace Newspack;

use \WP_Term_Query;
use \WP_Query;

/**
 * Handles adding new profiles importing from a successful ballot measure.
 * Districts should be filtered before calling this function to avoid importing all profiles
 *
 * @param array $districts ballotpedia myvote_results districts for parsing.
 * @return bool true if successful
 */
function np_add_new_profiles( $districts = array() ) {

	// filter by Information.
	foreach ( $districts as $district ) {

		if ( is_array( $district->races ) ) {

			$district_type_id = 0;
			$district_name_id = 0;

			// Handle District Taxonomy early.
			$district_name_query = new WP_Term_Query(
				array(
					'taxonomy'     => 'ek_district',
					'hide_query'   => false,
					'meta_key'     => 'ek_district_id', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'   => $district->id, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'meta_compare' => '=',
				)
			);

			if ( ! is_wp_error( $district_name_query ) && ! empty( $district_name_query->terms ) ) {
				$district_type_id = $district_name_query->terms[0]->term_id;
				$district_name_id = $district_name_query->terms[0]->parent;
			} else {
				// we don't have a term.

				// Handle District Type (term parent).
				$district_type_query = new WP_Term_Query(
					array(
						'taxonomy'   => 'ek_district',
						'hide_empty' => false,
						'name'       => $district->type,
						'parent'     => 0,
					)
				);

				if ( ! is_wp_error( $district_type_query ) && ! empty( $district_type_query->terms ) ) {
					$district_type_id = $district_type_query->terms[0]->term_id;
				} else {
					$district_type_insert = wp_insert_term(
						$district->type,
						'ek_district'
					);

					if ( ! is_wp_error( $district_type_insert ) ) {
						$district_type_id = $district_type_insert['term_id'];
					}
				}

				$district_name_insert = wp_insert_term(
					$district->name,
					'ek_district',
					array(
						'parent' => $district_type_id,
					)
				);

				if ( ! is_wp_error( $district_name_insert ) ) {
					$district_name_id = $district_name_insert['term_id'];
					update_term_meta( $district_name_id, 'ek_district_id', $district->id );
					update_term_meta( $district_name_id, 'ek_district_type', $district->type );
				}
			}

			foreach ( $district->races as $race ) {

				if ( is_array( $race->candidates ) ) {

					$race_id = 0;

					// Handle Race Taxonomy.

					$race_query = new WP_Term_Query(
						array(
							'taxonomy'     => 'ek_race',
							'hide_empty'   => false,
							'meta_key'     => 'ek_race_id', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
							'meta_value'   => $race->id, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
							'meta_compare' => '=',
						)
					);

					if ( ! is_wp_error( $race_query ) && ! empty( $race_query->terms ) ) {
						$race_id = $race_query->terms[0]->term_id;
					} else {
						$race_name = $race->office->name;
						if ( $race->office_position ) {
							$race_name .= ' (' . $race->office_position . ')';
						}

						$race_insert = wp_insert_term(
							$race_name,
							'ek_race'
						);

						if ( ! is_wp_error( $race_insert ) ) {
							$race_id = $race_insert['term_id'];
							update_term_meta( $race_id, 'ek_race_id', $race->id );
							update_term_meta( $race_id, 'ek_office_id', $race->office->id );
						}
					}

					foreach ( $race->candidates as $candidate ) {

						$candidate_query = new WP_Query(
							array(
								'post_type'    => 'ek_person',
								'meta_key'     => 'ek_bp_candidate_id', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
								'meta_value'   => $candidate->person->id, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
								'meta_compare' => '=',
							)
						);

						if ( ! $candidate_query->have_posts() ) {
							$thumbnail = '';
							if ( $candidate->person->image ) {
								$thumbnail = $candidate->person->image->thumbnail;
							}

							$candidate_id = wp_insert_post(
								array(
									'post_title'  => $candidate->person->name,
									'post_type'   => 'ek_person',
									'post_status' => 'publish',
									'meta_input'  => array(
										'ek_bp_bio'       => $candidate->person->summary,
										'ek_bp_url'       => $candidate->person->url,
										'ek_bp_candidate_id' => $candidate->person->id,
										'ek_url_override' => '',
										'ek_bp_is_incumbent' => $candidate->is_incumbent,
										'ek_bp_party_affiliation' => $candidate->party_affiliation[0]->name,
										'ek_bp_contact_facebook' => $candidate->person->contact_facebook,
										'ek_bp_contact_twitter' => $candidate->person->contact_twitter,
										'ek_bp_thumbnail_image' => $thumbnail,
									),
								)
							);

							if ( $candidate_id > 0 ) {
								wp_set_object_terms( $candidate_id, array( $district_name_id, $district_type_id ), 'ek_district' );
								wp_set_object_terms( $candidate_id, $race_id, 'ek_race' );
							}
						}
					}
				}
			}
		}
	}
	return true;
}
add_action( 'np_event_add_new_profiles', 'np_add_new_profiles', 10, 1 );


/**
 * Output HTML needed for Sample Ballot.  Includes merging of new profiles and overrides of information
 *
 * @param array $race current race for processing from ballotpedia myvote_results endpoint.
 * @param false $show_bios flag for showing the bios in the sample ballot.
 *
 * @return string[]
 */
function np_sample_ballot_candidates( $race = array(), $show_bios = false ) {

	$data = array(
		'success'        => 'false',
		'candidate_html' => '',
	);

	$candidates     = array();
	$new_candidates = array();

	if ( $race->candidates ) {
		foreach ( $race->candidates as $candidate ) {
			$profile = array(
				'name'              => $candidate->person->name,
				'url'               => $candidate->person->url,
				'thumbnail'         => '',
				'is_incumbent'      => $candidate->is_incumbent,
				'party_affiliation' => $candidate->party_affiliation[0]->name,
				'candidate_bio'     => $candidate->person->summary,
				'contact_facebook'  => $candidate->person->contact_facebook,
				'contact_twitter'   => '',
			);

			if ( $candidate->person->image ) {
				$profile['thumbnail'] = $candidate->person->image->thumbnail;
			}

			if ( $candidate->person->contact_twitter ) {
				$profile['contact_twitter'] = 'https://twitter.com/' . $candidate->person->contact_twitter;
			}

			$candidates[ $candidate->person->id ] = $profile;
		}
	}

	$race_name = $race->office->name;
	if ( $race->office_position ) {
		$race_name .= ' (' . $race->office_position . ')';
	}

	// Querying by name of the race here and not the race id provided by ballotpedia.  If this causes confusion I can update the query.
	$candidate_query = new WP_Query(
		array(
			'posts_per_page' => 50,
			'no_found_rows'  => true,
			'post_type'      => 'ek_person',
			'tax_query'      => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'ek_race',
					'field'    => 'name',
					'terms'    => $race_name,
				),
			),
		)
	);

	if ( $candidate_query->have_posts() ) {
		// determine what candidates we have.  Perfect scenario is we have everything.
		while ( $candidate_query->have_posts() ) {
			$candidate_query->the_post();
			// Overrides.
			// Bio Override.

			$candidate_bio = $candidate_query->post->ek_bp_bio;
			$wp_bio        = $candidate_query->post->post_content;
			if ( '' !== $wp_bio ) {
				$candidate_bio = $wp_bio;
			}

			// URL Override.

			$candidate_url = $candidate_query->post->ek_bp_url;
			$url_override  = $candidate_query->post->ek_url_override;
			if ( '' !== $url_override ) {
				$candidate_url = $url_override;
			}

			$cid = $candidate_query->post->ek_bp_candidate_id;

			if ( $candidates[ $cid ] ) {
				$candidates[ $cid ]['url']           = $candidate_url;
				$candidates[ $cid ]['candidate_bio'] = $candidate_bio;
			} else {
				$profile = array(
					'name'              => $candidate_query->post->post_title,
					'url'               => $candidate_url,
					'thumbnail'         => $candidate_query->post->ek_bp_thumbnail_image,
					'is_incumbent'      => $candidate_query->post->ek_bp_is_incumbent,
					'party_affiliation' => $candidate_query->post->ek_bp_party_affiliation,
					'candidate_bio'     => $candidate_bio,
					'contact_facebook'  => $candidate_query->post->contact_facebook,
					'contact_twitter'   => $candidate_query->post->contact_twitter,
				);

				$new_candidates[] = $profile;
			}
		}
	}

	if ( ! empty( $new_candidates ) ) {
		$candidates = array_merge( $candidates, $new_candidates );
	}

	if ( ! empty( $candidates ) ) {
		ob_start();
		foreach ( $candidates as $candidate ) { ?>
			<div class="candidate">
				<div class="candidate-image">
					<?php if ( $candidate['thumbnail'] ) { ?>
						<img alt="portrait of <?php echo esc_html( $candidate['name'] ); ?>" src="<?php echo esc_url( $candidate['thumbnail'] ); ?>">
					<?php } else { ?>
						<img alt="default candidate portrait" src="<?php echo esc_url( EK_PLUGIN_DIR . 'img/person-placeholder.jpg' ); ?>">
					<?php } ?>
				</div>
				<div class="candidate-content">
					<h4 class="candidate-name">
						<a href="<?php echo esc_url( $candidate['url'] ); ?>" target="_blank">
							<?php
							echo esc_html( $candidate['name'] );
							if ( $candidate['is_incumbent'] ) {
								?>
								<em>Incumbent</em>
							<?php } ?>
						</a>
					</h4>
					<div class="candidate-party">
						<?php echo esc_html( $candidate['party_affiliation'] ); ?>
					</div>
					<?php
					if ( $show_bios ) {
						$candidate_bio_allowed_html = array(
							'p' => array(),
						);
						?>
						<div class="candidate-summary">
							<?php echo wp_kses( $candidate['candidate_bio'], $candidate_bio_allowed_html ); ?>
						</div>
					<?php } ?>
					<div class="social">
						<?php if ( $candidate['contact_facebook'] ) { ?>
							<a href="<?php echo esc_url( $candidate['contact_facebook'] ); ?>" target="_blank" class="icon-facebook">
								<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
									<path d="M12 2C6.5 2 2 6.5 2 12c0 5 3.7 9.1 8.4 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.3v7C18.3 21.1 22 17 22 12c0-5.5-4.5-10-10-10z"></path>
								</svg>
								<span class="screen-reader-text"><?php esc_html_e( 'Facebook', 'newspack-electionkit' ); ?></span>
							</a>
							<?php
						}
						if ( $candidate['contact_twitter'] ) {
							?>
							<a href="<?php echo esc_url( $candidate['contact_twitter'] ); ?>" target="_blank" class="icon-twitter">
								<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
									<path d="M22.23,5.924c-0.736,0.326-1.527,0.547-2.357,0.646c0.847-0.508,1.498-1.312,1.804-2.27 c-0.793,0.47-1.671,0.812-2.606,0.996C18.324,4.498,17.257,4,16.077,4c-2.266,0-4.103,1.837-4.103,4.103 c0,0.322,0.036,0.635,0.106,0.935C8.67,8.867,5.647,7.234,3.623,4.751C3.27,5.357,3.067,6.062,3.067,6.814 c0,1.424,0.724,2.679,1.825,3.415c-0.673-0.021-1.305-0.206-1.859-0.513c0,0.017,0,0.034,0,0.052c0,1.988,1.414,3.647,3.292,4.023 c-0.344,0.094-0.707,0.144-1.081,0.144c-0.264,0-0.521-0.026-0.772-0.074c0.522,1.63,2.038,2.816,3.833,2.85 c-1.404,1.1-3.174,1.756-5.096,1.756c-0.331,0-0.658-0.019-0.979-0.057c1.816,1.164,3.973,1.843,6.29,1.843 c7.547,0,11.675-6.252,11.675-11.675c0-0.178-0.004-0.355-0.012-0.531C20.985,7.47,21.68,6.747,22.23,5.924z"></path>
								</svg>
								<span class="screen-reader-text"><?php esc_html_e( 'Twitter', 'newspack-electionkit' ); ?></span>
							</a>
						<?php } ?>
					</div>
				</div>
			</div>
			<?php

		}
		$data['data']    = ob_get_clean();
		$data['success'] = true;
	}

	return $data;
}

