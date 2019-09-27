CONTENTS OF THIS FILE
-----------------------

Introduction
Prerequisites
Installation
Post-Install Steps
Features
Maintainers

INTRODUCTION
-----------------

The Emerging Technology module was created by the MacEwan Library IT team as
a place to configure custom forms and pages related to current and upcoming
emerging technology projects and services offered by the library.  At this
time the module may be better named 3D Printing as its contents are specific
to that service.

This module contains a content type for tracking 3D print requests, some 
taxonomy for managing workflows and assignments and some views for staff
to use for managing the requests.  These were built as per the requirements
at MacEwan University Library and may not be suitable for all 

Originally created ad hoc within the library's Drupal-driven web site, this
module has been packaged for distribution for demonstration purposes.  It is
not maintained as an open source project.  It has been enhanced to install
cleanly and completely but does not uninstall itself at all.  It also uses
some very generic field names which may or may not cause collisions on
install.  Trying this software is recommended on a clean or simple Drupal
instance.  Backing up your Drupal database before activation is highly
recommended.  Use at your own risk.  MacEwan University Library is not
responsible for any damage to any website that installs this module.

This module was originally configured by David Pearson <pearsond@macewan.ca>
and was enhanced and packaged for distribution by Michael Schouten
<schoutenm@macewan.ca>


PREREQUISITES
-----------------

The MacEwan Emerging Technology module requires the following:

1) Drupal 8.x.x
	https://www.drupal.org

	This module is for the Drupal 8 CMS.  Note that this module does not
	uninstall cleanly.  Thus it is recommended to use a stand-alone
	drupal instance for testing or demonstration purposes.  It is also
	highly recommended that one backs up the drupal database before
	installation and use.

2) Private File System Configured
	The Drupal private file system must be configured.  This is done in
	your site's settings.php file.  If you change or enable the private
	file system, you must also clear all caches so private:// becomes
	available.

3) Node View Permissions module (Highly Recommended)
	https://www.drupal.org/project/node_view_permissions

        This module allows the hiding of published content by type.  3D Print
	Requests are published by default, so without this module they are
	visible by unauthenticated users and may end up appearing on your
	front page as content.  After installing, update "Node view
	permissions" as it may block access to all existing content types.

4) Fancy Login module (Recommended)
	https://www.drupal.org/project/fancy_login
	- Enable "Keep User on Same Page"
	
	The 3D Print Request form requires authentication.  Using this module
	allows a user to log in from the form and be returned directly there,
	functionality that does not appear to exist natively in Drupal.


INSTALLATION
----------------

Just like any other Drupal module, extract this package into the /modules
directory.  Find the module on the Extend admin page (/admin/modules)...
it's located under a "MacEwan" heading.  Select the module and click
"Install" at the bottom of the page.


POST-INSTALL STEPS
------------------------------------

After installation, this module requires some configuration before use. It
is recommended that you perform the following:

1) Modify Emails
	Email addresses have been configured directly in the controllers as
	it was simpler at the time.  This module will display email addresses
	in help text, but will also attempt to email users as well as a 
	designated service email when new requests are submitted.  To fully
	explore this functionality, you must edit the controller files at:
		./src/Controllers/ETPrintController.php
		./src/Form/ETPrintForm.php
	Search for any instance of "yourinstitution.com" and replace it and
	the related email address with appropriate emails for your institution.

2) Add Colour Taxonomy
	The module creates a "3D Print Colours" taxonomy.  This taxonomy is
	used to populate drop down menus in the 3D Print Request form.  You
	should create at least one colour term here so that primary color, a
	required field, can be selected.
		/admin/structure/taxonomy/manage/3d_print_colours/overview
	"Name" displays in the dropdown list.  "Image" will show up once
	a colour is selected to help demonstrate what is being picked.  A
	set of example colours is included with this module under
	./examples/images.  "Description" is optional text that will show up
	in the form if the colour is selected, typically used to convey
	information about the material such as the use for breakaway or
	water dissolvable PVA.  Finally, "In Stock" must be set to Yes in
	order for the colour to be selectable in the form, allowing easy
	management of colours that go out of stock and need to be reordered.

3) Add Technicians (Optional)
	Technicians are staff members assigned to complete a particular
	print request.  The list of technicians is managed through a
	taxonomy at: 
		/admin/structure/taxonomy/manage/3d_print_technician/overview
	This information is used in the workflow and displayed in the
	3D print queues.  It was simpler to use a taxonomy than mess with
	assignment by user and role, especially with the MacEwan Library
	authentication system.

4) Check File Upload Limits (Optional)
	Check your file upload and memory limits in PHP to ensure they're
	larger than any STL files you may be uploading.  As things stand,
	the error when attempting to upload a file that's too big is
	difficult to decipher.


FEATURES
----------------

Upon a successful installation of the Emerging Technology module, the 
following data structures and paths will be enabled:

3D Print Request (Content Type):
	This is the primary content of the 3D print request workflow and
	where all information concerning a request is stored.  Content
	of this time are created when a user fills in the 3D Print Request
	Form and the resulting requests are displayed and ordered in the 
	various	views created by this module.

	As a side note, the list of printers available is managed in the
	fields of this content type rather than as a separate taxonomy.
	This is just how we did it originally and it stuck.

3D Print Colours (Taxonomy):
	This taxonomy stores the colours of fillament that can be selected
	when filling in the 3D Print Request Form.  It includes an uploadable
	image (stored in the public file system) that will be displayed as
	a helpful reference when selecting the colour in the form as well
	as a description box which will display in the form to describe
	additional details about the colour or material.  While this taxonomy
	is called "Colours", it is also about the fillament material when
	applicable.

3D Print Status (Taxonomy):
	This list of possible statuses is populated when the module is 
	installed.  While workflow is not enforced by the module, requests
	should be assigned appropriate statuses as they progress through
	the workflow.  The statuses are:
		Submitted - for newly created requests only, auto-assigned
			    by the request form
		In Review - when a request has been picked up by a technician
		In Queue - after a request has been sliced and appropriate
			   gcode, printer, time and technician assigned, but
			   before actual printing begins
		Printing - the request is currently being printed
		Print Error - the print failed and is waiting analysis and
			      re-slicing
		Waiting for Pickup - the print succeeded and is ready
		Picked Up - the requestor has picked up the print (Complete)
		Abandoned - the print has not been picked up (Complete)
		Denied - the request cannot/will not be fulfilled (Complete)
		Postponed - (Deprecated)

3D Print Technician (Taxonomy):
	This is a list of staff who slice and/or print requests.  The 
	"initials" field provides a shorthand for the technician to save
	space on the request views.

Print Manager (Role):
	This user role is created to control view and edit access to the
	view and content type.  Permissions should be set up correctly
	on install.  This role includes create and edit rights and would
	normally be assigned to print technicians.

Print Viewer (Role):
	As directly above, only this role is for people who can view
	but not edit the requests.  Used for front line staff who aren't
	involved in the printing process but still must interact with
	and check on requests on behalf of requestors.

3D Print Request Form (Form):
	/services/3d-printing/request-3d-print

	This is the main feature of the module, an authenticated form
	through which 3D Print Requests are made.  There is a link to 
	this form that may have been added to a side menu depending on 
	your settings.  It's been changed to use Drupal authentication
	rather than the MacEwan Library's custom authentication module.
	As such, the automatically populated data (name, person_id, 
	email) are not optimal, but it gives you an idea of what MacEwan
	is doing here.  If you're not logged in when accessing the page,
	a simple "Log In" message will display instead.  The form also
	enforces one open request per user.

3D Print Request Staff View (View):
	A variety of views for different staff purposes, these include:

	/admin/3d-print-requests
	A general print queue viewer for all requests of all statuses.

	/admin/3d-print-requests/complete
	A list of all requests in one of the complete print statuses.
	
	/admin/3d-print-requests/in-process
	The print queue for unfinished requests.

	/admin/3d-print-requests/overdue
	A simplified view of unfinished requests that are past their due 
	date.

	Access to view these views is restricted by role (administrator,
	print viewer and print manager)

Service Unavailable Page (Controller):
	We have had the need to temporarily stop all new requests from being
	submitted.  As such, we included a simple page controller that can
	be used to replace the 3D Print Request Form.  This is activated
	through the module routing file emerging_technology.routing.yml


MAINTAINERS
---------------

This module is not actively maintained and is presented for demonstration
purposes only.  Feel free to take this code and use it for your own purposes
if helpful.  For feedback or to ask questions, please contact the developers:

Michael Schouten - schoutenm@macewan.ca
David Pearson - pearsond@macewan.ca

This code is made available through the GNU General Public License.
