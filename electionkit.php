<?php
/**
 * @package NewsPack Election Kit
 * @version 1.0.0
 */
/*
 * Plugin Name: NewsPack Election Kit
 * Plugin URI: https://willenglishiv.com/newspack-election-kit
 * Description: Provides a sample ballot for NewsPack Customers.  Install on the page of your choice with the shortcode [sample_ballot]
 * Author: Will English IV
 * Author URI: https://willenglishiv.com/
 * Version: 1.0.0
 */


add_shortcode('sample_ballot', 'np_sample_ballot_form');
function np_sample_ballot_form($atts) {
	$a = shortcode_atts(array(
		'show_bios' => 'false',
		'debug_location' => ''
	), $atts);
	ob_start(); ?>

	<div class="newspack-electionkit">
		<form class="address-form">
			<input type="hidden" id="ea-show-bio" name="ea-show-bio" value="<?php echo $a['show_bios']; ?>">
			<label for="ek-address">Enter your address where you're registered to vote:</label>
			<br>
			<input type="text" id="ek-address" name="ea-address" value="<?php echo $a['debug_location']; ?>" required>
			<br><br>
			<input type="submit" value="Submit">
		</form>
		<div class="spinner"><img src="<?php echo plugin_dir_url( __FILE__ ) . "img/25.gif"; ?>"></div>
		<div class="ek-error">There was an error with the sample ballot tool.  Please try again.</div>
		<div class="sample-ballot"></div>
	</div>

	<?php
	return ob_get_clean();
}

add_action('wp_ajax_sample_ballot', 'np_sample_ballot');
add_action('wp_ajax_nopriv_sample_ballot', 'np_sample_ballot');
function np_sample_ballot(){
	$election_date = "2020-11-03";
	$googleApiKey = "AIzaSyAc3rE7u30SEGhbDo8qVoV-k-UaV0hgVI4";
	$googleMapsApiUrl = 'https://maps.googleapis.com/maps/api/geocode/json';
	$bpSampleBallotElections = "https://api4.ballotpedia.org/sample_ballot_elections";
	$bpSampleBallotResults = "https://api4.ballotpedia.org/myvote_results";
	$response = array();

//	if (wp_verify_nonce( $_REQUEST['nonce'], 'electionkit-address' )) {
		$address = $_REQUEST['address'];

		$googleComposeURL = $googleMapsApiUrl . '?' . http_build_query(array(
			'address' => $address,
			'key' => $googleApiKey
		));

		$googleRequest = wp_remote_get($googleComposeURL);
		$googleData = "";

		if( is_wp_error($googleRequest) ) {
			wp_send_json_error("google didn't work");
		} else {
			$googleData = json_decode(wp_remote_retrieve_body($googleRequest));
		}

		if ($googleData->status !== "OK") {
			wp_send_json_error(array(
				"message"=>"google didn't return any data"
			));
		}

		// country check
		$locationResult = $googleData->results[0];

		$in_the_united_states = false;
//		$found_country = false;
//		$long_name_array = [];
		foreach( $locationResult->address_components as $component) {
//			$long_name_array[] = $component;
			if ( $component->long_name == "United States") {
				$in_the_united_states = true;
			}
		}

		if (!$in_the_united_states) {
			wp_send_json_error(array(
				"message"=>"address did not return a valid US location",
				"locationResult" => $locationResult,
			));
		}

//		$response['google_data'] = $googleData;

		$bpComposeURL = $bpSampleBallotElections . '?' . http_build_query(array(
			'lat' => $googleData->results[0]->geometry->location->lat,
			'long' => $googleData->results[0]->geometry->location->lng
		));

		$bpDistrictsRequest = wp_remote_get($bpComposeURL);
		$bpDistrictData = "";
		$bpDistrictArray = [];

		if ( is_wp_error($bpDistrictsRequest) ) {
			wp_send_json_error(array("message"=>"ballotpedia sample ballot elections call didn't work"));
		} else {
			$bpDistrictData = json_decode(wp_remote_retrieve_body($bpDistrictsRequest));
		}

		if (!$bpDistrictData) {
			wp_send_json_error(array("message"=>"ballotpedia sample ballot elections didn't return any data"));
		}

		foreach ($bpDistrictData->data->districts as $district) {
			$bpDistrictArray[] = $district->id;
		}

		$bpComposeURL = $bpSampleBallotResults . '?' . http_build_query(array(
			'districts' => implode(",", $bpDistrictArray),
			'election_date' => $election_date
		));

		//$response['sample_ballot_url'] = $bpComposeURL;

		$bpBallotRequest = wp_remote_get($bpComposeURL);
		$bpBallotData = "";

		if ( is_wp_error($bpBallotRequest) ) {
			wp_send_json_error(array(
				"message" =>"ballotpedia sample ballot results call didn't work",
				"information" => $bpBallotRequest
			));
		} else {
			$bpBallotData = json_decode(wp_remote_retrieve_body($bpBallotRequest));
		}

		if (!$bpBallotData) {
			wp_send_json_error(array("message"=>"ballotpedia sample ballot results didn't return any data"));
		}

		$district_order = array(
			"Country",
			"Congress",
			"State",
			"State Legislative (Upper)",
			"State Legislative (Lower)",
			"County",
			"County subdivision",
			"City",
			"City-town subdivision",
			"Judicial District",
			"Judicial district subdivision",
			"Special District",
			"School District",
		);

		$districts = $bpBallotData->data->districts;

		usort($districts, function ($a, $b) use ($district_order) {
			return array_search($a->type, $district_order) - array_search($b->type, $district_order);
		});

		$district_types = [];

		$ballot_measures = [];

		foreach ($districts as $district) {
			if ($district->ballot_measures) {
				foreach ($district->ballot_measures as $ballot_measure) {
					$ballot_measures[] = $ballot_measure;
				}
			}
		}

		$response['ballot_measures'] = $ballot_measures;

		ob_start();

		if ($ballot_measures) { ?>
			<div class="district">
				<div class="district-type">Ballot Measures</div>
				<div class="race">
					<?php foreach($ballot_measures as $ballot_measure) { ?>
						<div class="race-name">
							<a href="<?php echo $ballot_measure->url; ?>" target="_blank">
								<?php echo $ballot_measure->name; ?>
							</a>
						</div>
					<?php } // ballot measure foreach ?>
				</div>
			</div>
		<?php } // if/else ballot_measures check

		foreach($districts as $district) {
			$district_types[] = $district->type;
			if ($district->races) { ?>
				<div class="district">
					<div class="district-type"><?php echo $district->type . ' - ' . $district->name; ?></div>
					<?php foreach($district->races as $race) {
						if ($race->candidates) { ?>
							<div class="race">
								<div class="race-name">
									<?php echo $race->office->name; ?>
									<?php if ($race->office_position) {
										echo ' <span class="office-position">(' . $race->office_position . ')</span>';
									} ?>
								</div>
								<?php foreach($race->candidates as $candidate) { ?>
									<div class="candidate clearfix">
										<div class="candidate-image">
											<?php if ( $candidate->person->image ) { ?>
												<img src="<?php echo $candidate->person->image->thumbnail; ?>">
											<?php } else { ?>
												<img src="<?php echo plugin_dir_url( __FILE__ ) . "img/person-placeholder.jpg"; ?>">
											<?php } ?>
										</div>
										<div class="candidate-content">
											<div class="candidate-name">
												<a href="<?php echo $candidate->person->url ?>" target="_blank">
													<?php echo $candidate->person->name; ?>
													<?php if ($candidate->is_incumbent) echo "<em>(Incumbent)</em>"; ?>
												</a>
											</div>
											<div class="candidate-party">
												<?php foreach($candidate->party_affiliation as $party) { ?>
													<em><?php echo $party->name; ?></em>
												<?php } ?>
											</div>
											<?php if ( $_REQUEST['show_bios'] === 'true' ) { ?>
												<div class="candidate-summary">
													<?php echo $candidate->person->summary; ?>
												</div>
											<?php } ?>
											<div class="social">
												<?php if ($candidate->person->contact_facebook) { ?>
													<a href="<?php echo $candidate->person->contact_facebook ?>" target="_blank">Facebook</a>
												<?php } ?>
												<?php if ($candidate->person->contact_twitter) { ?>
													<a href="<?php echo $candidate->person->contact_twitter ?>" target="_blank">Twitter</a>
												<?php } ?>
											</div>
										</div>
									</div>
								<?php } // foreach candidates ?>
							</div>
						<?php } // if/else blank candidates check ?>
					<?php } // foreach races ?>
				</div>
			<?php } // if/else blank races check
		} // foreach districts

		$response['ballot'] = ob_get_clean();
		wp_send_json_success($response);
//	}


}

add_action('wp_enqueue_scripts', 'np_electionkit_scripts');
function np_electionkit_scripts() {
	wp_enqueue_script('electionkit',  plugin_dir_url( __FILE__ ) . 'electionkit.js', array('jquery'), '1.0.0', true);

	$params = array(
		'ajaxurl' => admin_url('admin-ajax.php'),
		'ajax_nonce' => wp_create_nonce('electionkit-address'),
	);
	wp_localize_script( 'electionkit', 'ajax_object', $params );

	wp_enqueue_style('electionkit',  plugin_dir_url( __FILE__ ) . 'electionkit.css', array(), '1.0.0');
}

