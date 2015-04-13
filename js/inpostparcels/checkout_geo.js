// Add the Geo Widget to the page and the callback function.
document.write('<script type="text/javascript" src="https://geowidget.inpost.co.uk/dropdown.php?field_to_update=name&field_to_update2=address&user_function=user_function"></script>');

// Once the page has loaded we can do the necessary processing to show / hide
// the InPost extra fields.
Event.observe(window, 'load', function() {
	hideShippingAll();

//	var methods = document.getElementsByName('shipping_method');
//	for(var i = 0; i < methods.length; i++)
//	{
//		console.log("Method = " + methods[i]);
//		console.log(methods[i]);
//	}

	jQuery('input[type="radio"][name="shipping_method"]').click(function(){
		hideShippingAll();
		var code = jQuery(this).val();
		if(jQuery(this).is(':checked'))
		{
			showShipping(code);
		}
	});
	jQuery('input[type="radio"][name="shipping_method"]').each(function(){
		var code = jQuery(this).val();
		if(jQuery(this).is(":checked"))
		{
			showShipping(code);
		}
	});
});

///
// user_function
//
// @param A locker machines address details.
// @brief Respond to the Map callback and update the machine select
//
function user_function(value)
{
	var address = value.split(';');
	//document.getElementById('town').value=address[1];
	//document.getElementById('street').value=address[2]+address[3];
	var box_machine_name   = document.getElementById('name').value;
	var box_machine_town   = document.value=address[1];
	var box_machine_street = document.value=address[2];

	var is_value = 0;
	document.getElementById('shipping_inpostparcels').value = box_machine_name;
	var shipping_inpostparcels = document.getElementById('shipping_inpostparcels');

	for(i = 0; i < shipping_inpostparcels.length; i++)
	{
		if(shipping_inpostparcels.options[i].value == document.getElementById('name').value)
		{
			shipping_inpostparcels.selectedIndex = i;
			is_value = 1;
		}
	}

	if (is_value == 0)
	{
		shipping_inpostparcels.options[shipping_inpostparcels.options.length] = new Option(box_machine_name+','+box_machine_town+','+box_machine_street, box_machine_name);
		shipping_inpostparcels.selectedIndex = shipping_inpostparcels.length-1;
	}
}

///
// showShipping
//
// @param The code for the field names
//
function showShipping(code)
{
	if(jQuery('#'+'shipping_form_'+code).length != 0)
	{
		jQuery('#'+'shipping_form_'+code).show();
		jQuery(this).find('.required-entry').attr('disabled','false');
	}
}

///
// hideShippingAll
//
function hideShippingAll()
{
	jQuery('input[type="radio"][name="shipping_method"]').each(function(){
		var code = jQuery(this).val();
		jQuery('#'+'shipping_form_'+code).hide();
		jQuery(this).find('.required-entry').attr('disabled','true');
	});
}

