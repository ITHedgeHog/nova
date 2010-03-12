Nova RPG Management System
==========================
Anodyne Production's next-generation RPG management system combines popular features from the SIMM Management System as well as new features like internationalization, developer enhancements, commenting on entries, dynamic forms, multiple characters per player and much more to make Nova the premier web-based solution for managing your RPG game.

Current Version
---------------
Release Candidate 2 (0.9.7)

Last Update
-----------
12 March 2010

Latest Updates
--------------
* updated the install language file
* updated the install controller to remove some debug code

Changes in 0.9.7
----------------
* added the 0.9.7 update file
* added the shiloh skin
* removed the get\_author\_user\_ids() method from the posts model
* removed the beta skin
* removed the titan skin
* updated the install data
    * version info
    * system component info
* updated the constants config file with the new version info
* updated the jquery ui to version 1.8rc3
* updated the look and feel of the installation center
* updated the pulsar skin
    * [admin] added admin section
    * [main] added the jquery ui theme
    * [main] added panel control images
    * [main] updated the stylesheets
    * [main] removed unused images
    * [login] updated the stylesheets
    * [wiki] added wiki section
    * added the jquery block ui plugin
* updated the titan skin
    * [admin] added the genre logos
    * [admin] updated the jquery ui theme
    * [admin] updated the stylesheets
    * [admin] updated the template file
    * [main] updated the jquery ui theme
    * [main] updated the stylesheets
    * [main] updated the genre logos
    * [main] updated the template file
    * [wiki] added the genre logos
    * [wiki] updated the stylesheets
    * [wiki] updated the template file
* updated the specifications listing to clean up some small issues
* updated the pulsar skin
* updated the look and feel of the update center
* updated the look and feel of the upgrade center
* updated the controllers to remove calls to load the string helper (it's autoloaded now)
* updated the autoload config to pull in the string helper automatically
* updated jquery qtip plugin to version 1.0-r29
* updated the tooltip location to the upper right of the target
* updated the default style for the uniform stylesheet
* updated the install language file
* updated the install options screen
* updated the ftp config file to set debug to false
* updated the install controller to remove some debug code
* fixed bug where error was thrown when submitting a wiki comment (#77)
* fixed bug where error was thrown when submitting a log comment (#78)
* fixed bug where error was thrown when submitting a post comment (#79) - would also affect sending post save and post delete emails as well
* fixed bug where the submit button on the contact form didn't work (#80)
* fixed bug where the character bio editing didn't work with character selection (#73)
* fixed bug where IE would cache the ajax views and won't let go (#81)
* fixed bug where the lazy plugin was throwing errors with the qtip plugin
* fixed error with the bl5 install file
* fixed error with the baj install file

Version History
---------------
<table>
	<tr>
		<th>Version</th><th>Description</th><th>Start Date</th><th>End Date</th>
	</tr>
	<tr>
		<td>Release Candidate</td><td>Final candidate builds for golden master</td><td>01 March 2010</td><td>-</td>
	</tr>
	<tr>
		<td>Beta</td><td>Feature complete testing version</td><td>10 Nov 2009</td><td>01 March 2010</td>
	</tr>
	<tr>
		<td>M7</td><td>Thresher Release 1</td><td>02 Nov 2009</td><td>09 Nov 2009</td>
	</tr>
	<tr>
		<td>M6</td><td>Reports, upgrading from SMS, updating Nova</td><td>02 Sep 2009</td><td>30 Oct 2009</td>
	</tr>
	<tr>
		<td>M5</td><td>Character and user management</td><td>01 Jul 2009</td><td>31 Sep 2009</td>
	</tr>
	<tr>
		<td>M4</td><td>Site and data management</td><td>01 Mar 2009</td><td>30 Jun 2009</td>
	</tr>
	<tr>
		<td>M3</td><td>Authentication, writing and private messaging</td><td>14 Jan 2009</td><td>27 Feb 2009</td>
	</tr>
	<tr>
		<td>M2</td><td>Un-authenticated system</td><td>30 Sep 2008</td><td>13 Jan 2009</td>
	</tr>
	<tr>
		<td>M1</td><td>CodeIgniter and Nova setup</td><td>01 Jul 2008</td><td>30 Sep 2008</td>
	</tr>
	<tr>
		<td>M0</td><td>Development start</td><td>-</td><td>30 May 2008</td>
	</tr>
</table>