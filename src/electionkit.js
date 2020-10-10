import './electionkit.scss';

( $ => {
	$( document ).ready( function() {
		const { ajax_object } = window;
		const $sampleBallot = $( '.newspack-electionkit .sample-ballot' );
		const $spinner = $( '.newspack-electionkit .spinner' );
		const $errorState = $( '.newspack-electionkit .ek-error' );

		$( '.newspack-electionkit .address-form' ).on( 'submit', function( e ) {
			e.preventDefault();
			// $('.newspack-electionkit .sample-ballot').empty();
			$.ajax( {
				type: 'post',
				url: ajax_object.ajaxurl,
				data: {
					action: 'sample_ballot',
					nonce: ajax_object.ajax_nonce,
					address: $( '.newspack-electionkit #ek-address' ).val(),
					show_bios: $( '.newspack-electionkit #ek-show-bio' ).val(),
				},
				beforeSend() {
					$sampleBallot.fadeOut();
					$spinner.toggleClass( 'is-active' );
					$errorState.removeClass( 'is-active' );
				},
				success( response ) {
					$spinner.toggleClass( 'is-active' );

					if ( response.success === true ) {
						if ( response.data.ballot !== '' ) {
							$sampleBallot.html( response.data.ballot );
							$sampleBallot.show();
						} else {
							$errorState.toggleClass( 'is-active' );
						}
					} else {
						$errorState.toggleClass( 'is-active' );
					}
				},
				error() {
					$spinner.toggleClass( 'is-active' );
				},
			} );
			return false;
		} );
	} );
} )( window.jQuery );
