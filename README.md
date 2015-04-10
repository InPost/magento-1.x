# magento_easypack
A widget to allow the creation of parcels for Magento and a fix for the express checkout method

## Installation
The code should be copied into the appropriate Magento root directory.

## Recommended Settings

### Fire Checkout
The lookup for the user's locker works best based upon the city of their delivery. So it is best if the **Ajax save and reload rules** contains the City in the Shipping Address Save Rules.

### One Step Checkout
The lookup for the user's locker works best based upon the city of their delivery. So it is best if the **AJAX update shipping/payment methods** contains the City in the AJAX save billing fields.

**NB** Neither of the above recommendations is essential, the user can always select their locker from the map view. But it would make quite a difference to the way the user can select their locker. If the city field is missed then the search will try and use the postcode. This is an exact match lookup which will rarely find any lockers.

Or if the user fills the postcode field before the city then it is very likely no lockers will be shown in the select. The user will then have to click on the *Show terminals in other cities* button to see **all** of the terminals, roughly 1000. This is quite a daunting list to go through.
Hence the recommendation.

