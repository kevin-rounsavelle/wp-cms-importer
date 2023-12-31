
# wp_cms_importer

The wp_cms_importer PHP class will parse, clean and import WordPress posts and/or pages using the standard WP Export XML files and add the document records directly into generic (non WP) mysql tables without the WordPress-specific content. The wp_cms_importer class allows the WordPress content to be directly integrated into any CMS without manually copying content and without having to use direct SQL queries to clean the data post-import.


## Features

- Complete parsing of WordPress XML posts or pages file(s).
- Cleaning of existing image's WP CSS classes (on/off option)
- Automatically add default CSS class(s) to all imported image references (optional)
- Removal of all WordPress Generated HTML comments
- Rename paths of all images to the new server URL and/or images directory. 
- Script automatically excludes CDN images from import to new server.
- Setting alternate server destination URL in content paths for development sites.
- Set auto-increment values to specific starting values so imported data with synch with existing CMS record counts.


## Installation | Usage

Export the WordPress site posts or WordPress pages in your /wp-admin/ under Tools > Export. 

Export each document type as a seperate XML files. (i.e. posts.xml, pages.xml)

Copy the wp_cms_importer.php file up your webserver where you want to import the data.

Include the wp_cms_imported.php class file into your php file or project and set the required config parameters. 

Recommended modifying the included import_example.php file when first using the class to learn how to apply it to your specific import requirements. The import_example.php file contains detailed comments on all config options.

## Config Settings
```
define( 'WP_CMS_MODE', "TEST");
//Sets the import run mode: TEST or DATABASE.

define( 'WP_CMS_HOSTNAME', "localhost");
//mysql database hostname

define( 'WP_CMS_USERNAME', "username");
//mysql database username
 
define( 'WP_CMS_PASSWORD', "password");
//mysql database password
 
define( 'WP_CMS_DATABASE', "database_name");
//mysql database name

define( 'WP_CMS_DOCS_INCREMENT', "1");
//Starting value for imported records.

define( 'WP_CMS_CATEGORIES_INCREMENT', "1");
//Starting value for imported categories.

define( 'WP_CMS_TAGS_INCREMENT', "1");
//Starting value for imported tags.

define( 'WP_CMS_SILENT', "OFF");
//If status messages will be outputted on screen. Set to ON for silent mode.

define( 'WP_CMS_CSS', "ON");
//Clean image css (ON or OFF).

$images_css = "img-responsive";
//Default image classes to be added to imported data (optional).

$uploads_folder = 'site-media';
//Destination folder of imported image files.

$saved_images_path = "/";
//Relative path to $uploads_folder in relation to script location. Leave as "/" for root.

$wp_xml = 'posts.xml';
//WP posts or WP pages xml export file name/path.

$development_url = "";
//URL of development server (optional).
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
- 'allow_url_fopen' is "ON" in php.ini for copying images from a remote URL to the local server (usually it will be turned on by default)
- WordPress XML export file (posts or pages).

#### Is there a limit on the amount of data that can be processed?

No, there is no limit on the number of pages, posts or images that can be imported and transferred. The script could potentially time-out when copying 100s of MBs (or GBS) of images from the old server to the new server but since the images are marked off individually as completed after transferring, you can simply re-run the image copy feature if for some reason a time-out occured. The script has a high PHP script time-out already set however. To avoid a potential time-out on very, very large sites, run the XML process and Image Copy process seperately (not sequentially). More information on how to do this is in the import_example.php file.

#### Does it work on localhost?

Yes, you can run this script on a local PHP server with MySQL and copy the content down to the local site instance. Just make sure that the copy destination folder on your local server has the proper permissions to allow PHP to write files (755 usually will work for all installs)

#### What WordPress data does it import and what does it not import?

The class processes the data that is included in the WordPress XML export file but it does not include all the fields since many of them are not relevant to another CMS platform. The following fields and data are imported/converted into the mysql tables: 

- Title of the post or page
- URL (slug) of the post or page
- Active status of doc : i,e published or draft.
- Doc Type : page, post or custom post type.
- Author / creator of the post or page.
- Doc Post Date | Published Date.
- Doc last edited date.
- Page or Post HTML content
- Images that have been cleaned from WP-specific CSS and paths.
- Images in their original status and paths.
- Tags for the individual page or post - name and slug.
- Categories for the individual page or post - name and slug

The XML import script does not import the featured images since those are not included in the default WP export XML files. There are several plugins available to include that data however. If you are using one of those plugins, you can easily modify the class and db schema to acommodate the additional field. The script also does not import other content on the page such as sidebar content, header images, etc. (That content will be specific to the WordPress install as well and not relevant for use in an external system).

## Integration Recommendations | Enhancements

If you will be integrating this class directly into an existing application and not using it as a stand-alone import script, I recommend calling it from a client-side ajax function or equivalent. The way I integrated this class into our CMS admin system was:

- Client uploads their WordPress XML posts of pages file with a standard PHP upload scripta and AJAX
- Success function of the 1st AJAX call triggers the $cms_content->process("xml") with another AJAX call.
- Success function for the second AJAX call outputs the results (number of converted records) to the client
- Secondary image copy process is triggered  with a third AJAX call... $cms_content->process("images", $saved_images_path)
- Success function of third ajax call outputs the number of converted images.
- Client is given option of viewing imported records that have been saved in MySQL.

if the global config variable 'WP_CMS_SILENT' is set to OFF (default), the messages that are outputted during the conversion process can be used directly in your JS functions for client status messages.

Another use for this script is converting all image references in the imported WP content from locally saved files to CDN distributed images. 

Adding a new simple PHP str_replace statement right after LINE #430 in the **cleanImages()** function to replace the root site URL with the CDN url provides this functionality.

Example:

430  $src = str_replace('wp-content', $this->uploads_folder, $src);

```
$src = str_replace($site_root, 'https://d3t2z2w3vv9t89j.cloudfront.net/', $src);

```
... 

Another enhancement is adding an API call to post the files to a CDN instead of simply copying them to the local server's images folder. 

Replacing copy($file_url, $destination_save); in the **db_copy_images()** function (line # 1119) with an API call (post) to a specific provider (i.e. AWS s3) will allow you to post all the page images directly to the CDN and optimize the site pages for global content distribution in the new CMS.

I have successfully integratd the AWS PHP SDK with this class to post all images to an s3 bucket (Bucket is an assigned Cloudfront distribution).

If I receive multiple requests for a specific CDN provider such as S3/CloudFront, I may include that in a future version of this class. However, if you can sucessfully post a file to your CDN with their PHP API or code-snippet, it is very simple to replace the default copy command on line #1119 with your custom CDN API upload file command.

## Why I created WP_CMS_IMPORTER

I needed to migrate a few dozen client WordPress sites over to our new CMS platform. The WordPress installs ranged in size and complexity from small-business showcase sites to large e-commerce sites with 100s of posts and pages and 1000s of images.  

Unfortunately, after manually converting a few of the "easy" ones, I realized that it would take tons of time and repetitive tasks to get all the other client sites migrated off WordPress over to our CMS. After extensive research on Google, Stack Exchange, etc, it was evident that there were no commercial tools or open-source scripts that could even come close to automating the content migration process effectively. 

Therefore, since we also needed to clean out the css markups and comments created by both WordPress Blocks and plugins such as Elementor and Beaver Builder, as well as, automatically rename the paths from /wp-content/ to the specific images folder on the new destination server, I knew that coding a custom automated import tool from scratch was required.

Finally, since most of the new non-WP sites were still under active developement, the "old" WordPress site had to remain live and we needed an easy way to repeat the import process with the most recent content once the new site was ready for launch 

I created the WP_CMS_IMPORTER class so it could be used on WordPress site conversion projects of any size and the imported data can then be easily ported into any existing CMS or custom DB schema. The original version of this script was mapped to our CMS DB schema but the published wp_cms_importer code has been modified to import the WordPress content directly into generic temporary MySQL tables so you can then easily copy those records into your specific custom tables (if required).

By using the wp_cms_importer class instead of manually importing the WP posts and pages into a non-WordPress system, the content conversion time per site has decreased from hours to minutes. I hope this PHP class helps other developers who have been struggling with importing WP content into other sytems and please reach out if you have any questions or need assistance on modifying the _db functions inside the class to integrate with your MySQL DB schema.
## Authors

- [@kevin-rounsavelle](https://www.github.com/kevin-rounsavelle)

