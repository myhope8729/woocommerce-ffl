jQuery(document).ready(function($){
	$('.button-find-dealer').click(function(){
		var zipcode = $('#store-postal-code').val();
		var radius = $('#radius').val();
		var data = {
			'action': 'search_dealers',
			'zipcode': zipcode,
			'radius' : radius
		};
		$.ajax({
			url:ajax_object.ajax_url,
			data:data,
			dataType: 'json',
			success: function(data){
				if (data.length > 0){
					$('.store-result-list').html('<h3>FFL Dealers</h3>');
					$.each(data, function(ind, dealer){
						$('.store-result-list').append('<div class="dealer col-lg-6">\
							<input id="dealer_' + dealer.id + '" class="form-check-input" type="radio" name="store" value="' + dealer.id + '">\
							<label class="form-check-label" for="dealer_' + dealer.id + '">\
							<div class="dealer-details">\
							<div class="store-name">' + dealer.business_name + '</div>\
							<address>' + dealer.street + ' ' + dealer.city + ', ' + dealer.state + ' ' + dealer.zip + 
							'<p>'+ dealer.phone +'</p>\
							</address>\
							</div></label></div>');
					});
				}
			}
		})
	});

	$(document).on('click', '.store-result-list .form-check-input', function(){
		if ($('.store-result-list .form-check-input:checked').length > 0){
			$('.select-store').removeProp('disabled');
		}
	});

	$('.select-store').click(function(e){
		e.preventDefault();
		e.stopPropagation();
		var dealer = $('.store-result-list .form-check-input:checked').parent();
		$('.dealer_id').val(dealer.find('.form-check-input').val());
		$('.ffl_dealer_wrapper .selected-store').remove();
		$('.ffl_dealer_wrapper').prepend('<div class="selected-store">\
			<div class="store-header">Selected FFL Dealer</div>\
			<div class="store-body">' + dealer.find('.dealer-details').html() + '</div>');
		$('.button-select-dealer').html('Change Dealer');
		$('#ffl_dealer_modal').modal('toggle');
	});
});