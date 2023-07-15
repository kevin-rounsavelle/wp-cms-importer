
# wp_cms_importer

The wp_cms_importer class will parse, clean and import WordPress posts and/or pages using the standard WP Export XML files and add the records directly into generic (non WP) mysql tables without the WordPress-specific content. The wp_cms_importer class allows the WordPress content to be directly integrated into any CMS without manually copying content and without having to use direct SQL queries to clean the data post-import.


## Features

- Complete parsing of WordPress XML posts or pages file(s).
- Cleaning (removal) of existing image's CSS classes (on/off option)
- Automatically add specific CSS class(s) to all imported image references (optional)
- Removal of all WordPress Generated HTML comments
- Renaming paths of all images to the new server URL and/or images directory. 
- Script automatically excludes CDN images from import to new server.
- Setting development server URL in content paths.
- Set auto-increment values to specific values for imported data to synch with existing CMS record counts.


## Installation | Usage

Export the WordPress site posts or pages in your wp-admin under Tools > Export. Export each type as a seperate XML file.

Copy the wp_cms_importer.php file up your webserver where you want to import the data.

Include the wp_cms_imported.php file into your php file or project and set the required config parameters. 
(Recommended modifying the included import_example.php file when first using the class to learn how to apply it to your specific import requirements. The import_example.php file contains detailed comments on all config options)
## Config Settings
```
define( 'WP_CMS_MODE', "TEST");              // Sets the import run mode: TEST or DATABASE
define( 'WP_CMS_HOSTNAME', "localhost");     // mysql database hostname 
define( 'WP_CMS_USERNAME', "username");      // mysql database username 
define( 'WP_CMS_PASSWORD', "password");      // mysql database password 
define( 'WP_CMS_DATABASE', "database_name"); // mysql database name

define( 'WP_CMS_DOCS_INCREMENT', "1");       // Starting value for imported records.
define( 'WP_CMS_CATEGORIES_INCREMENT', "1"); // Starting value for imported categories
define( 'WP_CMS_TAGS_INCREMENT', "1");       // Starting value for imported tags.
define( 'WP_CMS_SILENT', "ON");              // If status messages will be outputted on screen.
define( 'WP_CMS_CSS', "ON");                 // Clean image css (ON or OFF)

$images_css     	= "img-responsive";  // Default image classes to be added to imported data (optional)	
$uploads_folder 	= 'site-media';      // Destination folder of imported image files
$saved_images_path 	= "/";               // Relative path to $uploads_folder in relation to script location. Leave as "/" for root.

$wp_xml      		= 'posts.xml';       // WP posts or WP pages xml export file name/path.

$development_url    = "";                // URL of development server (optional).
```

After setting the configuration variables, initilize the class and call the specific function as shown below:

```
$cms_content = new wp_cms_importer($wp_xml, $uploads_folder, $images_css, $development_url) 
$cms_content->process("xml");
$cms_content->process("images", $saved_images_path);

```

## Database Info

The following mysql database tables will be automatically created on the destination database if DATABASE is set in WP_CMS_MODE. (TEST mode will simply output the converted data to the screen but not save it anywhere).

- imported_cms_admins
- imported_cms_categories
- imported_cms_categories_assocations
- imported_cms_content
- imported_cms_images_copy_log
- imported_cms_tags
- imported_cms_tags_assocations

If you prefer to have the script directly add the content into your existing CMS tables, all DB related functions in the class file start with db_ and can be modified with your specific table and field names.

Example, to modify the script to check your existing usernames table and add new content authors if not already listed, modify the function called db_check_admin.
## License

[MIT](https://choosealicense.com/licenses/mit/)


## FAQ

#### What are the requirements to run this WP_CMS_IMPORTER?

- PHP 7.2 or higher (PHP 8.1 Recommended)
- MySQL compatible database (MySQL Server, MariaDB, Amazon Aurora-MySQL, etc)
- Allow_url_fopen is "ON" in php.ini for image copying from server to server (usually it will be turned on by default)
- WordPress XML export file (posts or pages).

#### Is there a limit on the amount of data that can be processed?

No, there is no limit on the number of pages, posts or images that can be imported and transferred. The script could potentially time-out when copying 100s of MBs )or GBS) of images from the old server to the new server but since the images are marked off individually as completed after transferring, you can simply re-run the image copy feature if for some reason a time-out occured. The script has a high PHP script time-out already set however. To avoid a potential time-out on very, very large sites, run the XML process and Image Copy process seperately (not sequentially). More information on how to do this is in the import_example.php file.

#### Does it work on localhost?

Yes, you can run this script on a local PHP server with MySQL and copy the content down to the local site instance. Just make sure that the copy destination folder on your local server has the proper permissions to allow PHP to write files (755 usually will work for all installs)

#### What WordPress data does it import and what does it not import?

The class processes the data that is included in the WordPress XML export file but it does not include all the fields since many of them are not relevant to another CMS platform. The following fields and data are imported/converted into the mysql tables: 

- Title of the post or page
- URL (slug) of the post or page
- Active status of doc - i,e published or draft.
- Author / creator of the post or page.
- Doc Post | Published Date.
- Doc last edited date.
- Page or Post HTML content
- Images that have been cleaned from WP-specific CSS and paths.
- Images in their original status and paths.
- Tags for the individual page or post - name and slug.
- Categories for the individual page or post - name and slug

The XML import script does not import the featured images since those are not included in the default WP export XML files. There are several plugins available to include that data however. If you are using one of those plugins, you can easily modify the class and db schema to acommodate the additional field. The script also does not import other content on the page such as sidebar content, header images, etc. (That content will be specific to the WordPress install as well and not relevant for use in an external system).



## Why I created WP_CMS_IMPORTER

We needed to migrate a few dozen client WordPress sites that ranged from small sites to large sites with 100s of posts and pages and 1000s of images over to our new CMS platform.  

Unfortunately, after manually converting a few sites, I realized that it would take tons of time and repetitive tasks to get all the other client sites migrated off of WordPress. After tons of research on Google, Stack Exchange, etc, it was evident there were no tools or scripts available that could automate the process effectively. 

Therefore, since we also needed to clean out all the properiety css markups and comments created by both WordPress Blocks and plugins such as Elementor and Beaver Builder, as well as, automatically rename the paths from wp-content to the specific images folder on the new destination server, I knew that a custom automated import function was required.

Finally, since most of the new sites were still under developement, the source WP site had to remain live and we needed an easy way to repeat the import process with the most recent content once the new site was ready for launch 

I created the WP_CMS_IMPORTER class so it could be used on WordPress site conversion projects of any size and the imported data can then be easily ported into any existing CMS or custom DB schema. The original version of this script was proprietary to our CMS DB schema but this class has been modified to import the data directly into generic temporary data tables so you can then easily copy those records into your specific mysql tables (if required).

The conversion time for importing clean and usable WP content has now decreased from hours to minutes and I hope this PHP class helps other developers who have been struggling with importing WP content into other sytems.
## Authors

- [@kevin-rounsavelle](https://www.github.com/kevin-rounsavelle)

