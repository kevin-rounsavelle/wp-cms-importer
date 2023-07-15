<?php
require_once('wp_cms_importer.php');

/* 
The parsed WP XML content will be outputted to the screen while in 'TEST' mode but no data will be saved.
Note: the HTML content will be escaped and printed to the screen so the content will not be rendered by the browser when in TEST mode. 
*
*
if you want to add the parsed and cleaned content into your live CMS, it is recommended that you do a test run on the sample db tables that are created with this app first and then, if succesfull, modify the table schema in the db_ functions with your install-specific specific categories, tags, and content table names and fields. 
*
*
******* CONFIG SETTINGS ******
*
Set the WP_CMS_MODE variable below to DATABASE and enter in your db connection credentials to insert (save) parsed content into the sample database tables.
*/

define( 'WP_CMS_MODE', "TEST"); // change TEST to DATABASE to save content to mysql.

define( 'WP_CMS_HOSTNAME', "localhost"); // this is the db host 
define( 'WP_CMS_USERNAME', "username"); // this is the db username 
define( 'WP_CMS_PASSWORD', "password"); // this is the db password 
define( 'WP_CMS_DATABASE', "database_name"); // this is the db name

define( 'WP_CMS_DOCS_INCREMENT', "1"); 
/* Starting ID (auto-increment value) for the posts or pages that are imported. If you will be importing the data into an existing CMS content table, set the ID to be the next value of your highest current PAGE ID number. */

define( 'WP_CMS_CATEGORIES_INCREMENT', "1"); 
/* Starting ID (auto-increment value) for the categories that are imported. If you will be importing the data into an existing CMS content table, set the ID to be the next value of your highest current CATEGORY ID number. */

define( 'WP_CMS_TAGS_INCREMENT', "1"); 
/* Starting ID (auto-increment value) for the tags that are imported. If you will be importing the data into an existing CMS content table, set the ID to be the next value of your highest current TAG ID number. */

define( 'WP_CMS_SILENT', "OFF");

/* set WP_CMS_SILENT this to "ON" to not display any status messages about the import process (i.e. record insertions, image copying, etc). If you are embedding this class in your own application and you will be redirecting to a success page, you should set this to ON so that you will not receive a page headers already sent PHP error. For testing or stand-alone use, leave it to "OFF"*/

define( 'WP_CMS_CSS', "ON"); 

/* set WP_CMS_CSS to "OFF" to keep the source content image's assigned CSS classes as-is and ignore any css classes that are specified below.*/


$images_css     = "img-responsive";	

/* $images_css sets the class (or classes) that should be assigned to all images by default. You can leave this field blank if the images should be cleaned or their existing CSS classed but not have any default class assigned. You can specify multiple classes by seperating them with a space. If you prefer to leave the image's css as-is simply set the WP_CMS_CSS to OFF.*/

$uploads_folder = 'site-media'; 

/*Replaces 'wp-content/uploads' in all image paths in the imported content. This is the directory on the New server where your images will be stored. If you will be using /wp-content/uploads/ on the new server as , instead of an alternate folder, just leave it empty > $uploads_folder = ''; */

$saved_images_path = "/"; 

/* the relative path to the $uploads_folder based on where this script will run. This is where the images will be saved when copied off the old WP server to the new server. Example  "../", "../../"
if the script is running in root and the folder uploads folder is directly above it, just enter in "/".
*/ 

$development_url = ""; 

/* The optional URL for the content paths in the imported content. If you want to develop a new site under a temporary URL such as https://development.mycmssite.com, enter it above and all URL references in the content will be changed to the temporary URL. 
/* 

*
XML SOURCE FILE SETTING -- ! MOST IMPORTANT SETTING !
*
The $wp_xml variable below sets the source file for the conversion.
You should generate this file from inside your wp-admin under Tools > Export. Export posts and/or pages as individual xml files and run them seperately.<br>

You can also set the $wp_xml value dynamically via $_GET, $_POST, $_SESSION, etc,  if you are running this via an ajax process or embedding it within your existing application workflow ..example:

if (isset($_GET['xml_file])) {
    $wp_xml = $_GET['xml_file'];
}

Just make sure that the $wp_xml value contains the correct path to your xml file relative to where you are running this script.
*/

$wp_xml      = 'pages.xml';  // WP posts or WP pages xml export file name/path.


# RUN THE WP POSTS / PAGES IMPORT PROCESS #

$cms_content = new wp_cms_importer($wp_xml, $uploads_folder, $images_css, $development_url); //initialize the class
$cms_content->process("xml"); //process the XML file.
$cms_content->process("images", $saved_images_path); // copy pages or posts imges to $uploads_folder



# READ BELOW BEFORE RUNNING THE AUTO IMAGE COPY PROCESS: $cms_content->process("images", $saved_images_path);
/* 
You can either manually copy all the content from WP site's /wp-content/uploads/ directory to the new folder destination set above using FTP, SSH or RDP, or you use the process("images") command to copy only the images that are referenced in the imported XML content records (WP pages and/or posts).
*
The primary benefit of using the "copy images" feature is that you will only copy over relevant content that is being used by the pages or posts and not tons of old, orphaned images and media that will not be used on the new site.
*
IMPORTANT *1: To copy the files from the OLD WP server to the new CMS server, you must run this script on the same server where you will be copying the images and the media folder set above must have write permissions for PHP (i.e. 755).
*
IMPORTANT *2: For very large imports, it is highly recommended that you run the XML process first then the image copy process second. i.e comment out $cms_content->process("images", $saved_images_path), load the php file. Then comment out $cms_content->process("xml") and uncomment $cms_content->process("images", $saved_images_path) and load the file page to run the copy image process by itself.
*
You can run both batches simultaneously for small to medium sites but for larger XML imports with hundreds or thousands of large images, it is recommended to run the image copy as a stand-alone process to prevent a time-out during the images copying process from the old server to the development server. The process must connect to the current WP server and copy each image individually from the source WP server to the new server folder.
*
The image copying process below must be run after the $cms_content->process("xml") statement and the WP_CMS_MODE global variable must be set to "DATABASE" for it to run. The first process("xml") command will create a table called 'imported_cms_images_copy_log' in the mysql database and the image references will be loaded from that table to copy them to the the destination folder sequentially.
*/