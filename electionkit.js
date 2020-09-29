(($) => {
  $(document).ready(function() {
    const $sampleBallot = $('.newspack-electionkit .sample-ballot');
    const $spinner = $('.newspack-electionkit .spinner');
    const $errorState = $('.newspack-electionkit .ek-error');

    $('.newspack-electionkit .address-form').on('submit', function(e){
      e.preventDefault();
      // $('.newspack-electionkit .sample-ballot').empty();
      $.ajax({
        type: "post",
        url: ajax_object.ajaxurl,
        data: {
          action: 'sample_ballot',
          nonce: ajax_object.ajax_nonce,
          address: $('.newspack-electionkit #ek-address').val(),
          show_bios: $('.newspack-electionkit #ek-show-bio').val(),
        },
        beforeSend: function() {
          $spinner.toggleClass('is-active');
          $errorState.removeClass('is-active');
        },
        success: function(response) {
          //console.log(response);
          $spinner.toggleClass('is-active');

          if (response.success === true) {
            if (response.data.ballot !== "") {
              $sampleBallot.html(response.data.ballot);
            } else {
              $errorState.toggleClass('is-active');
            }
          } else {
            $errorState.toggleClass('is-active');
          }
        },
        error: function(response) {
          $spinner.toggleClass('is-active');
        }
      });
      return false;

    });
  });
})(jQuery);
