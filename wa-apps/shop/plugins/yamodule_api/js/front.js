$(document).ready(function(){
	// alert(12);
	$form = $('#cart-form, .purchase.addtocart');
	$form.submit(function(){
		var f = $(this);
		$.post('/shop/metrika/cart/info', f.serialize(), function (response) {
			if (response)
				metrikaReach('metrikaCart', response);
		});
	});
});

function metrikaReach(goal_name, params) {
	for (var i in window) {
		if (/^yaCounter\d+/.test(i)) {
			window[i].reachGoal(goal_name, params);
		}
	}
}