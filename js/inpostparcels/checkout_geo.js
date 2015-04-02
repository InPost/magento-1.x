// Add the Geo Widget to the page and the callback function.
document.write('<script type="text/javascript" src="https://geowidget.inpost.co.uk/dropdown.php?field_to_update=name&field_to_update2=address&user_function=user_function"></script>');

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
