magento_easypack
================
A widget to allow the creation of parcels for Magento and a fix for the express checkout method

Directory Structure
===================
There is a directory called 'paypal' which contains the file reveiw.phtml and
review(2).phtml.

The appropriate file for your version of Magento, needs to be installed into the template that is being used by the store. You must either replace the existing file in the default directory structure or ammend the current template version.

***Always make sure that the file in your current install matches the one here.***
If you have **any** doubts, **merge**, the two files, change by change.

The files, review.phtml and review(2).phtml, have been annotated to show the changes that have been made and that should be made to your local file.

It should exist in a directory structure similar to the following:-

	app/design/frontend/default/<Your Template>/template/paypal/express/review.phtml

Always remember that the default Magento files could be overwritten during an update so always use a templated file to edit / change.
