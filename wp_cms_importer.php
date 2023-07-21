<?php

/**
 * Imports WordPress Posts or Pages Content from XML export file into external MySQl database
 *
 * @author Kevin Rounsavelle
 */

class wp_cms_importer
{

    /**
     * Sets the initial variables for the wp_cms_importer class and DB connection object.
     *
     * @param string $wp_xml Wordpress XML file name and path
     * @param string $uploads_folder Folder name on this server to copy image files
     * @param string $images_css Space seperated list of defautl css classes to be applied to all images in HTML
     * @param string $development_url URL of the temporary development site (optional)
     */

    public function __construct($wp_xml, $uploads_folder, $images_css, $development_url)

    {

        $this->feed                 = $wp_xml;
        $this->uploads_folder       = $uploads_folder;
        $this->images_css           = $images_css;
        $this->processed            = "";
        $this->uploads_folder_path  = "/";
        $this->dev_url              = $development_url;

        if (WP_CMS_MODE == "DATABASE") {

            $this->cms_db = new mysqli(WP_CMS_HOSTNAME, WP_CMS_USERNAME, WP_CMS_PASSWORD, WP_CMS_DATABASE);

            if ($this->cms_db->connect_errno > 0) {

                die('Unable to connect to database [' . $cms_db->connect_error . ']');
            }

            # this is the connection for the procedural mysqli database calls
            $this->cms_db_procedural = mysqli_connect(WP_CMS_HOSTNAME, WP_CMS_USERNAME, WP_CMS_PASSWORD, WP_CMS_DATABASE);
        }
    }

    public function process($type, $path = null)
    {
        set_time_limit(3600);

        if ($type == trim(strtolower("xml"))) {

            if (WP_CMS_MODE == "DATABASE") {
                $this->db_check_tables(); // check if sample CMS import tables have been installed.
            }

            $this->process_xml(); // process xml content 

        } elseif ($type == trim(strtolower("images"))) {

            if (WP_CMS_MODE == "DATABASE") { // only execute copy process if not in test-mode.

                $this->db_copy_images(); // copy images off old server to new server.
            }
        } else {
            exit;
        }

        if ($path != "") {
            $this->uploads_folder_path = $path;
        }
    }

    /**
     * This processes the Wordpress XML pages or posts file.
     */
    private function process_xml()
    {
        //document specific variables.. these are all sent to the crete document function which you can use to add the records to your database, download the images and post them to your server, etc

        $doc_title              = ""; // Title of the post or page
        $doc_slug               = ""; // URL (slug) of the post or page
        $doc_status             = ""; // Active status of doc - i,e published or draft.
        $doc_type               = ""; // Type of doc - page, post or custom post type
        $doc_creator            = ""; // Author / creator of the post or page.
        $doc_post_date          = ""; // Doc Post | Published Date.
        $doc_modified_date      = ""; // Doc last edited date.
        $doc_content            = ""; // Page or Post HTML content
        $doc_images_new         = array(); // Array of images that have been cleaned from WP-specific CSS and paths.
        $doc_images_old         = array(); // Array of images in their original status and paths.
        $doc_tags               = array(); // Array of tags for the individual page or post - name and slug.
        $doc_categories         = array(); // Array of categories for the individual [age or post - name and slug	

        $feed                   = file_get_contents($this->feed);
        $feed                   = str_replace("content:encoded", "contentEncoded", $feed);
        $feed                   = str_replace("dc:creator", "dccreator", $feed);
        $feed                   = str_replace("wp:", "wp", $feed);
        $xml                    = simplexml_load_string($feed);
        $import_wp              = $this->simplexmlToArray($xml);

        foreach ($import_wp as $key => $value) {

            if ($key == 'channel') {
                foreach ($value as $key2 => $value2) {
                    if ($key2 == 'link') {
                        $site_root = $value2 . "/";
                    }
                    if ($key2 == 'wpauthor') {

                        $loginname        	= "";
                        $loginemail       	= "";
                        $loginfirstname   	= "";
                        $loginlastname    	= "";
                        $logindisplayname 	= "";

                        if (count($value2) == count($value2, COUNT_RECURSIVE)) {
                            // this is a one-level array... single author for all content
                            foreach ($value2 as $author => $author_data) {
                                if ($author == "wpauthor_login") {
                                    $loginname = $author_data;
                                }
                                if ($author == "wpauthor_email") {
                                    $loginemail = $author_data;
                                }
                                if ($author == "wpauthor_first_name") {
                                    $loginfirstname = $author_data;
                                }
                                if ($author == "wpauthor_last_name") {
                                    $loginlastname =  $author_data;
                                }
                                if ($author == "wpauthor_display_name") {
                                    $logindisplayname =  $author_data;
                                }
                                if (WP_CMS_MODE == "DATABASE") {
                                    $this->db_check_admins($loginname, $loginemail, $loginfirstname, $loginlastname, $logindisplayname);
                                }
                            }
                        } else {
                            // this is a nested array with multiple levels..
                            // multiple authors for site content...
                            foreach ($value2 as $authorlist) {

                                foreach ($authorlist as $author => $author_data) {
                                    if ($author == "wpauthor_login") {
                                        $loginname = $author_data;
                                    }
                                    if ($author == "wpauthor_email") {
                                        $loginemail = $author_data;
                                    }
                                    if ($author == "wpauthor_first_name") {
                                        $loginfirstname = $author_data;
                                    }
                                    if ($author == "wpauthor_last_name") {
                                        $loginlastname =  $author_data;
                                    }
                                    if ($author == "wpauthor_display_name") {
                                        $logindisplayname =  $author_data;
                                    }

                                    // create temp admin accounts for authors / creators if account does not exist.
                                    if (WP_CMS_MODE == "DATABASE") {
                                        $this->db_check_admins($loginname, $loginemail, $loginfirstname, $loginlastname, $logindisplayname);
                                    }
                                }
                            }
                        }
                    }

                    if ($key2 == 'item') {

                        $itemscount = count($value2);

                        foreach ($value2 as $items) {
                            foreach ($items as $key3 => $value3) {

                                if ($key3 == 'title') {
                                    $doc_title = $value3;
                                }
                                //link
                                if ($key3 == 'link') {
                                    $link = str_replace($site_root, "", $value3);
                                    $link = rtrim($link, "/");
                                    $doc_slug = $link;
                                }
                                //status
                                if ($key3 == 'wpstatus') {
                                    $doc_status =  $value3;
                                }
                                //type
                                if ($key3 == 'wppost_type') {
                                    $doc_type =  $value3;
                                }

                                //author/creator
                                if ($key3 == 'dccreator') {
                                    $doc_creator =  $value3;
                                }

                                if ($key3 == 'wppost_date') {
                                    $doc_post_date = strtotime($value3);
                                    $doc_post_date  = date("Y/m/d h:i:s", $doc_post_date);
                                }

                                if ($key3 == 'wppost_modified') {
                                    $doc_modified_date = strtotime($value3);
                                    $doc_modified_date  = date("Y/m/d h:i:s", $doc_modified_date);
                                }

                                if ($key3 == 'contentEncoded') {
                                    $doc_images_old = "";
                                    $doc_images_new = "";
                                    $original_images = "";
                                    $cleaned_images = "";
                                    $doc_content = $value3;
                                    $doc_content = str_replace("<!-- wp:paragraph -->", "", $doc_content);
                                    preg_match_all('/<img [^>]+>/', $value3, $image_matches, PREG_SET_ORDER);
                                    foreach ($image_matches as $ikey => $ivalue) {
                                        foreach ($ivalue as $images) {
                                            $images = str_replace("&", " ", $images);
                                            $images = str_replace(",", " ", $images);
                                            $cleaned_image = "";
                                            $cleaned_image = $this->cleanImage($images, $site_root);
                                            $doc_content = str_replace($images, $cleaned_image, $doc_content);
                                            $cleaned_images = $cleaned_images . $cleaned_image . ",";
                                            $original_images = $original_images . $images . ',';
                                        }
                                    }

                                    $cleaned_images = trim($cleaned_images);
                                    $doc_images_new = explode(",", $cleaned_images);
                                    $original_images = trim($original_images);
                                    $doc_images_old = explode(",", $original_images);

                                    // cleaned post with image paths changed and comments removed.
                                    $doc_content = $this->remove_html_comments($doc_content);
                                    // replace site URL with development URL if set
                                    if ($this->dev_url != "") {
                                        $dev_url = rtrim($this->dev_url, "/");
                                        $dev_url = $dev_url . "/";
                                        $doc_content = str_replace($site_root, $dev_url, $doc_content);
                                    }
                                    $doc_images_new = array_filter($doc_images_new);
                                    $doc_images_old = array_filter($doc_images_old);
                                }
                                // categories and tags
                                if ($key3 == 'category') {

                                    //if (!isset($tag_name)) {
                                    $tag_name = "";
                                    //}
                                    //if (!isset($tag_slug)) {
                                    $tag_slug = "";
                                    //}
                                    $doc_tags          = array(); // Array of tags 
                                    $doc_categories    = array(); // Array of categories 

                                    if (count($value3) == count($value3, COUNT_RECURSIVE)) {

                                        // echo 'array is not multiple levels;
                                        // TAGS

                                        if (in_array("post_tag", $value3)) {

                                            $tags_data = "";
                                            $tag_name = "";
                                            $tag_slug = "";

                                            foreach ($value3 as $cattype => $catvalues) {


                                                if ($cattype == "value") {
                                                    $tag_name = $catvalues;
                                                }

                                                if ($cattype == "nicename") {
                                                    $tag_slug = $catvalues;
                                                }

                                                if ($tag_slug == "") {
                                                    $tag_slug = $tag_name;
                                                }

                                                $tags_data = array(
                                                    'name' => $tag_name,
                                                    'slug' => $tag_slug
                                                );
                                            }

                                            if (WP_CMS_MODE == "DATABASE") {
                                                $this->db_add_tag($tag_name, $tag_slug);
                                            }

                                            array_push($doc_tags, $tags_data);
                                        } else {

                                            //CATEGORIES
                                            $cat_name = "";
                                            $cat_slug = "";
                                            $categories_data = "";

                                            foreach ($value3 as $cattype => $catvalues) {
                                                if ($cattype == "value") {
                                                    $cat_name = $catvalues;
                                                }

                                                if ($cattype == "nicename") {
                                                    $cat_slug = $catvalues;
                                                }

                                                if ($cat_slug == "") {
                                                }

                                                $categories_data = array(
                                                    'name' => $cat_name,
                                                    'slug' => $cat_slug
                                                );
                                            }

                                            if (WP_CMS_MODE == "DATABASE") {
                                                $this->db_add_category($cat_name, $cat_slug);
                                            }

                                            array_push($doc_categories, $categories_data);
                                        }
                                    } else {

                                        // array is multi-level

                                        foreach ($value3 as $catvalue) {

                                            // TAGS

                                            if (in_array("post_tag", $catvalue)) {

                                                $tags_data = "";

                                                foreach ($catvalue as $cattype => $catvalues) {


                                                    if ($cattype == "value") {
                                                        $tag_name = $catvalues;
                                                        // echo "<br>Tag Name= " . $catvalue;
                                                    }

                                                    if ($cattype == "nicename") {
                                                        $tag_slug = $catvalues;
                                                        // echo "<br>Tag Slug= " . $catvalue;
                                                    }

                                                    if ($tag_slug == "") {
                                                        $tag_slug = $tag_name;
                                                    }



                                                    $tags_data = array(
                                                        'name' => $tag_name,
                                                        'slug' => $tag_slug
                                                    );
                                                }

                                                if (WP_CMS_MODE == "DATABASE") {
                                                    $this->db_add_tag($tag_name, $tag_slug);
                                                }

                                                array_push($doc_tags, $tags_data);
                                            } else {

                                                // CATEGORIES
                                                $cat_name = "";
                                                $cat_slug = "";
                                                $categories_data = "";

                                                foreach ($catvalue as $cattype => $catvalues) {


                                                    if ($cattype == "value") {
                                                        $cat_name = $catvalues;
                                                    }

                                                    if ($cattype == "nicename") {
                                                        $cat_slug = $catvalues;
                                                    }

                                                    if ($cat_slug == "") {
                                                    }

                                                    $categories_data = array(
                                                        'name' => $cat_name,
                                                        'slug' => $cat_slug
                                                    );
                                                }

                                                if (WP_CMS_MODE == "DATABASE") {
                                                    $this->db_add_category($cat_name, $cat_slug);
                                                }

                                                array_push($doc_categories, $categories_data);
                                            }
                                        } // end categories 
                                    } // end category array type check
                                }  // end category check
                            }

                            $this->create_document(
                                $doc_title,
                                $doc_slug,
                                $doc_status,
                                $doc_type,
                                $doc_creator,
                                $doc_post_date,
                                $doc_modified_date,
                                $doc_content,
                                $doc_images_new,
                                $doc_images_old,
                                $doc_tags, //array
                                $doc_categories //array
                            );
                        } //. end xml indidividual items
                        $this->processed = "items";
                    }
                }
            }
        }
    }

    /**
     * Removes HTML Comments From String
     *
     * @param string $content Original HTML content
     * @return string Cleaned HTML content
     */

    private function remove_html_comments($content = '')
    {
        return preg_replace('/<!--(.|\s)*?-->/', '', $content);
    }

    /**
     * Cleans Image Of CSS and Reconstructs Image Reference Link
     *
     * @param string $image_html HTML img tag
     * @param string $site_root The site URL extracted in process_xml()
     * @return string Cleaned img tag
     */

    private function cleanImage($image_html, $site_root)
    {
        $image_html = str_replace("&quot;", " ", $image_html);
        $image_html = str_replace("&", " ", $image_html);
        $image_html = str_replace(",", " ", $image_html);

        $dom = new DOMDocument();
        $dom->loadHTML($image_html);

        $imgs = $dom->getElementsByTagName('img');

        foreach ($imgs as $img) {
            $src = "";
            $width = "";
            $height = "";
            $output = "";
            $alt =  "";
            $class = "";

            if ($img->hasAttribute('src')) {
                $src = ' src="' . $img->getAttribute('src') . '"';
                if ($this->uploads_folder != "") {
                    // make sure it is not a CDN image...
                    if (strpos($src, $site_root) !== false) {
                        // this is a CDN image...
                    } else {
                        $src = str_replace('wp-content', $this->uploads_folder, $src);
                    }
                }
            }

            if ($img->hasAttribute('alt')) {
                $alt = ' alt="' . $img->getAttribute('alt') . '"';
            }

            if ($img->hasAttribute('width')) {
                $width = ' width="' . $img->getAttribute('width') . '"';
            }

            if ($img->hasAttribute('height')) {
                $height = ' height="' . $img->getAttribute('height') . '"';
            }

            if (WP_CMS_CSS == "ON") {
                if ($this->images_css != "") {
                    $class = 'class="' . $this->images_css . '"';
                }
            }

            if ($img->hasAttribute('class')) {
                if (WP_CMS_CSS == "ON") {
                    if ($this->images_css != "") {
                        $class = 'class="' . $this->images_css . '"';
                    }
                } else {
                    $class = 'class="' . $img->getAttribute('class') . '"';
                }
            }
        }
        $output = '<img ' . $class . ' ' . $src . ' ' . $width . ' ' . $height . ' ' . $alt . '>';
        return $output;
    }

    /**
     * Converts XML structure into PHP nested array
     *
     * @param string $xml xml document
     * @return array multi-dimensional array
     */

    private function simplexmlToArray($xml)
    {
        $ar = array();
        foreach ($xml->children() as $k => $v) {
            $child = $this->simplexmlToArray($v);
            if (count($child) == 0) {
                $child = (string) $v;
            }
            foreach ($v->attributes() as $ak => $av) {
                if (!is_array($child)) {
                    $child = array("value" => $child);
                }
                $child[$ak] = (string) $av;
            }
            if (!array_key_exists($k, $ar)) {
                $ar[$k] = $child;
            } else {
                if (!is_string($ar[$k]) && isset($ar[$k][0])) {
                    $ar[$k][] = $child;
                } else {
                    $ar[$k] = array($ar[$k]);
                    $ar[$k][] = $child;
                }
            }
        }
        return $ar;
    }

    /**
     * Outputs the imported XML document content to the screen or adds it to the database.
     *
     * if global variable WP_CMS_MODE = DATABASE, function db_add_document() called for each XML lime item.
     *
     * @param $doc_title Title of document
     * @param string $doc_slug Seo slug of document
     * @param string $doc_status status of document (draft or publish)
     * @param string $doc_type type of document (post or page or custom post type)
     * @param string $doc_creator author or admin of document
     * @param string $doc_post_date date document was published or posted 
     * @param string $doc_modified_date date document was last edited
     * @param string $doc_content The HTML content of the document
     * @param array $doc_images_new array of the converted document image HTML tags
     * @param array $doc_images_old array of the original document image HTML tags
     * @param array $doc_tags array of the post categorization tags
     * @param array $doc_categories array of the post categorization categories
     */

    private function create_document(
        $doc_title,
        $doc_slug,
        $doc_status,
        $doc_type,
        $doc_creator,
        $doc_post_date,
        $doc_modified_date,
        $doc_content,
        array $doc_images_new,
        array $doc_images_old,
        array $doc_tags,
        array $doc_categories
    ) {


        if (WP_CMS_MODE == "TEST" && WP_CMS_SILENT == "OFF") {
            echo "<hr>";
            echo "<br><h4>Title:</h4> " . $doc_title;
            echo "<br><h4>Slug:</h4> " . $doc_slug;
            echo "<br><h4>Type:</h4> " . $doc_type;
            echo "<br><h4>Status:</h4> " . $doc_status;
            echo "<br><h4>Creator:</h4> " . $doc_creator;
            echo "<br><h4>Post-Date:</h4> " . $doc_post_date;
            echo "<br><h4>Modified-Date:</h4> " . $doc_modified_date;
            echo "<br><h4>Content:</h4> ";
            echo '<code>';
            echo htmlentities($doc_content);
            echo '</code>';
            echo "<br><br><strong>Categories:</strong><br>";
            $count = 1;
            if ($doc_categories) {
                foreach ($doc_categories as $categories) {
                    echo '<span style="font-size: 16px; font-weight: 600;">' . $count . ': </span> [<br>';
                    foreach ($categories as $cattype => $catvalue) {
                        echo $cattype . ":" . $catvalue . "<br>";
                    }
                    echo "]";
                    $count++;
                }
            }
            if ($doc_tags) {
                echo "<br><strong>Tags:</strong><br>";
                $count = 1;
                foreach ($doc_tags as $tags) {
                    echo '<span style="font-size: 16px; font-weight: 600;">' . $count . ':</span> [<br>';
                    foreach ($tags as $cattype => $catvalue) {
                        echo $cattype . ":" . $catvalue . "<br>";
                    }
                    echo "]";
                    $count++;
                }
            }
            if ($doc_images_old) {
                echo "<br><strong>Images OLD:</strong><br>";
                foreach ($doc_images_old as $images) {
                    echo htmlentities($images) . "<br>";
                }
            }
            if ($doc_images_new) {
                echo "<strong>Images NEW:</strong><br>";
                foreach ($doc_images_new as $images) {
                    echo '<span style="color:red;">' . htmlentities($images) . "</span><br>";
                }
            }
        } // END WP_CMS_MODE = TEST (Output to page);


        // add document to database, get insert id, and add category and tag associations.

        if (WP_CMS_MODE == "DATABASE") {

            $this->db_add_document(
                $doc_title,
                $doc_slug,
                $doc_status,
                $doc_type,
                $doc_creator,
                $doc_post_date,
                $doc_modified_date,
                $doc_content,
                $doc_images_new,
                $doc_images_old,
                $doc_tags,
                $doc_categories
            );
        }
    }

    /**
     * Checks if the required database tables have been installed and if not installs them into the mysql database.
     */

    private function db_check_tables()
    {

        $query_cms_import_tables = "SHOW TABLES LIKE 'imported_cms_content'";
        if (!$cms_import_tables = mysqli_query($this->cms_db_procedural, $query_cms_import_tables)) die(mysqli_error($this->cms_db_procedural));
        $row_cms_import_tables = mysqli_fetch_assoc($cms_import_tables);
        $totalRows_cms_import_tables = mysqli_num_rows($cms_import_tables);
        if ($totalRows_cms_import_tables == 0) {
            // tables not found.. install them...

            $insertSQL = "CREATE TABLE `imported_cms_admins` (
  							`admin_id` int NOT NULL AUTO_INCREMENT,
  							`admin_level` int DEFAULT '0',
							`username` varchar(255) DEFAULT NULL,
							`email` varchar(255) DEFAULT NULL,
							`first_name` varchar(255) DEFAULT NULL,
							`last_name` varchar(255) DEFAULT NULL,
							`display_name` varchar(255) DEFAULT NULL,
							`login_password` varchar(255) DEFAULT NULL,
  							`date_added` datetime DEFAULT '2023-01-01 00:00:00',
  							 PRIMARY KEY (`admin_id`)
							 ) ENGINE=InnoDB
							 DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci
							 AUTO_INCREMENT=1
							 ";
            if (!$Result1 = mysqli_query($this->cms_db_procedural, $insertSQL)) die(mysqli_error($this->cms_db_procedural));

            $insertSQL = "CREATE TABLE `imported_cms_categories` (
  							`category_id` int NOT NULL AUTO_INCREMENT,
  							`category_name` varchar(255) DEFAULT NULL,
  							`category_slug` varchar(255) DEFAULT NULL,
  							PRIMARY KEY (`category_id`)
							) ENGINE=InnoDB
							DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci
							AUTO_INCREMENT=" . WP_CMS_CATEGORIES_INCREMENT;
            if (!$Result1 = mysqli_query($this->cms_db_procedural, $insertSQL)) die(mysqli_error($this->cms_db_procedural));

            $insertSQL = "CREATE TABLE `imported_cms_categories_associations` (
  							`cat_assoc_id` int NOT NULL AUTO_INCREMENT,
  							`category_id` int DEFAULT '0',
  							`content_id` int DEFAULT '0',
  							PRIMARY KEY (`cat_assoc_id`)
							) ENGINE=InnoDB
							DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci
							AUTO_INCREMENT=1000000
							";
            if (!$Result1 = mysqli_query($this->cms_db_procedural, $insertSQL)) die(mysqli_error($this->cms_db_procedural));

            $insertSQL = "CREATE TABLE `imported_cms_content` (
  							`content_id` int NOT NULL AUTO_INCREMENT,
  							`admin_id` int DEFAULT '0',
  							`title` text DEFAULT NULL,
							`slug` varchar(255) DEFAULT NULL,
  							`status` varchar(50) DEFAULT NULL,
							`doc_type` varchar(50) DEFAULT NULL,
  							`post_date` datetime DEFAULT '2023-01-01 00:00:00',
							`modified_date` datetime DEFAULT '2023-01-01 00:00:00',
							`content_html` longtext DEFAULT NULL,
							`categories_list` text DEFAULT NULL,
							`tags_list` text DEFAULT NULL,
							`images_list_old` text DEFAULT NULL,
							`images_list_new` text DEFAULT NULL,
							PRIMARY KEY (`content_id`)
							) ENGINE=InnoDB
							DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci
							AUTO_INCREMENT=" . WP_CMS_DOCS_INCREMENT;

            if (!$Result1 = mysqli_query($this->cms_db_procedural, $insertSQL)) die(mysqli_error($this->cms_db_procedural));

            $insertSQL = "CREATE TABLE `imported_cms_tags` (
  							`tag_id` int NOT NULL AUTO_INCREMENT,
							`tag_name` varchar(255) DEFAULT NULL,
							`tag_slug` varchar(255) DEFAULT NULL,
							PRIMARY KEY (`tag_id`)
							) ENGINE=InnoDB
							DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci
							AUTO_INCREMENT=" . WP_CMS_TAGS_INCREMENT;
            if (!$Result1 = mysqli_query($this->cms_db_procedural, $insertSQL)) die(mysqli_error($this->cms_db_procedural));

            $insertSQL = "CREATE TABLE `imported_cms_tags_associations` (
  							`tag_assocation_id` int NOT NULL AUTO_INCREMENT,
  							`tag_id` int DEFAULT '0',
  							`content_id` int DEFAULT '0',
  							PRIMARY KEY (`tag_assocation_id`)
							) ENGINE=InnoDB
							DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci
							AUTO_INCREMENT=1000000
							";
            if (!$Result1 = mysqli_query($this->cms_db_procedural, $insertSQL)) die(mysqli_error($this->cms_db_procedural));

            $insertSQL = "CREATE TABLE `imported_cms_images_copy_log` (
  							`image_id` int NOT NULL AUTO_INCREMENT,
							`image_url` text DEFAULT NULL,
							`image_copied` int DEFAULT '0',
							PRIMARY KEY (`image_id`)
							) ENGINE=InnoDB
							DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci
							AUTO_INCREMENT=1
							";
            if (!$Result1 = mysqli_query($this->cms_db_procedural, $insertSQL)) die(mysqli_error($this->cms_db_procedural));
        } else { // tables already installed in database
            return;
        }
    }

    /**
     * Checks if admin (author) exists in users table and if not, adds new user to table.
     *
     * @param string $loginname Admin's Username extracted from document record in process_xml()
     * @param string $loginemail Admin's Email extracted from document record in process_xml()
     * @param string $loginfirstname Admin's First Name extracted from document record in process_xml()
     * @param string $loginlastname Admin's Last Name extracted from document record in process_xml()
     * @param string $logindisplayname Admin's Public Display Name extracted from document record in process_xml()
     */

    private function db_check_admins($loginname, $loginemail, $loginfirstname, $loginlastname, $logindisplayname)
    {

        # replace the DB lookup and insert code below with your own logins table info to check if a document creator exists as an admin in the system already and if not create a new one.

        $cms_data_lookup = $this->cms_db->prepare("SELECT username FROM imported_cms_admins WHERE username = ?");

        $cms_data_lookup->bind_param('s', $loginname);
        $cms_data_lookup->execute();
        $cms_data_lookup->store_result();
        $cms_data_lookup->bind_result($username);

        if ($cms_data_lookup->num_rows == 0 && $loginname != "") {

            // user not found .. add a new user to the sample admins table so that there is an association between the admins and the content records.

            $temp_password = md5(rand(10000, 10000000)); // random temporary password for new admin (creator) acct record.
            $add_date = date("Y-m-d G:i:s");

            $dbinsert = $this->cms_db->prepare("INSERT INTO imported_cms_admins (username,email,first_name,last_name, display_name,login_password,date_added) VALUES (?, ?, ? , ?, ?, ?, ?)");

            $dbinsert->bind_param('sssssss', $loginname, $loginemail, $loginfirstname, $loginlastname, $logindisplayname, $temp_password, $add_date);
            $dbinsert->execute();
            $dbinsert->close();
        }

        $cms_data_lookup->free_result();
        $cms_data_lookup->close();
    }


    /**
     * Add New Category To Database From XML Content If It Doesn't Already Exist in Database.
     *
     * @param string $cat_name category name extracted from document record in process_xml()
     * @param string $cat_slug category slug (seo name) extracted from document record in process_xml()
     */

    private function db_add_category($cat_name, $cat_slug)
    {

        $cms_data_lookup = $this->cms_db->prepare("SELECT category_name FROM imported_cms_categories WHERE category_name = ?");

        $cms_data_lookup->bind_param('s', $cat_name);
        $cms_data_lookup->execute();
        $cms_data_lookup->store_result();
        $cms_data_lookup->bind_result($category_name);

        if ($cms_data_lookup->num_rows == 0) {

            // category not found .. add it to the sample import categpries table...

            $dbinsert = $this->cms_db->prepare("INSERT INTO imported_cms_categories (category_name,category_slug) VALUES (?, ?)");

            $dbinsert->bind_param('ss', $cat_name, $cat_slug);
            $dbinsert->execute();
            $dbinsert->close();
        }

        $cms_data_lookup->free_result();
        $cms_data_lookup->close();
    }


    /**
     * Add New Category Association Record Between Content Record and Category Records
     *
     * @param string $cat_name category name extracted from document record in process_xml()
     * @param string $content_id new content record ID value from db_add_document() function
     */

    private function db_add_category_association($cat_name, $content_id)
    {

        $cms_data_lookup = $this->cms_db->prepare("SELECT category_id FROM imported_cms_categories WHERE category_name = ?");

        $cms_data_lookup->bind_param('s', $cat_name);
        $cms_data_lookup->execute();
        $cms_data_lookup->store_result();
        $cms_data_lookup->bind_result($category_id);
        if ($cms_data_lookup->num_rows > 0) {
            $cms_data_lookup->fetch();

            $dbinsert = $this->cms_db->prepare("INSERT INTO imported_cms_categories_associations (category_id, content_id) VALUES (?, ?)");

            $dbinsert->bind_param('ii', $category_id, $content_id);
            $dbinsert->execute();
            $dbinsert->close();
        }

        $cms_data_lookup->free_result();
        $cms_data_lookup->close();
    }

    /**
     * Add New Tag To Database From XML Content If It Doesn't Already Exist in Database.
     *
     * @param string $tag_name category name extracted from document record in process_xml()
     * @param string $tag_slug category slug (seo name) extracted from document record in process_xml()
     */

    private function db_add_tag($tag_name, $tag_slug)
    {

        $cms_data_lookup = $this->cms_db->prepare("SELECT tag_name FROM imported_cms_tags WHERE tag_name = ?");

        $cms_data_lookup->bind_param('s', $tag_name);
        $cms_data_lookup->execute();
        $cms_data_lookup->store_result();
        $cms_data_lookup->bind_result($tag_name);

        if ($cms_data_lookup->num_rows == 0) {

            // category not found .. add it to the sample import categpries table...

            $dbinsert = $this->cms_db->prepare("INSERT INTO imported_cms_tags (tag_name,tag_slug) VALUES (?, ?)");

            $dbinsert->bind_param('ss', $tag_name, $tag_slug);
            $dbinsert->execute();
            $dbinsert->close();
        }

        $cms_data_lookup->free_result();
        $cms_data_lookup->close();
    }

    /**
     * Add New Tag Association Record Between Content Record and Tag Records
     *
     * @param string $tag_name category name extracted from document record in process_xml()
     * @param string $content_id new content record ID value from db_add_document() function
     */

    private function db_add_tag_association($tag_name, $content_id)
    {

        $cms_data_lookup = $this->cms_db->prepare("SELECT tag_id FROM imported_cms_tags WHERE tag_name = ?");

        $cms_data_lookup->bind_param('s', $tag_name);
        $cms_data_lookup->execute();
        $cms_data_lookup->store_result();
        $cms_data_lookup->bind_result($tag_id);
        if ($cms_data_lookup->num_rows > 0) {
            $cms_data_lookup->fetch();

            $dbinsert = $this->cms_db->prepare("INSERT INTO imported_cms_tags_associations (tag_id, content_id) VALUES (?, ?)");

            $dbinsert->bind_param('ii', $tag_id, $content_id);
            $dbinsert->execute();
            $dbinsert->close();
        }

        $cms_data_lookup->free_result();
        $cms_data_lookup->close();
    }

    /**
     * Creates a new database record for an XML document line item.
     *
     * @param $doc_title Title of document
     * @param string $doc_slug Seo slug of document
     * @param string $doc_status status of document (draft or publish)
     * @param string $doc_type type of document (post or page or custom post type)
     * @param string $doc_creator author or admin of document
     * @param string $doc_post_date date document was published or posted 
     * @param string $doc_modified_date date document was last edited
     * @param string $doc_content The HTML content of the document
     * @param array $doc_images_new array of the converted document image HTML tags
     * @param array $doc_images_old array of the original document image HTML tags
     * @param array $doc_tags array of the post categorization tags
     * @param array $doc_categories array of the post categorization categories
     */

    private function db_add_document(
        $doc_title,
        $doc_slug,
        $doc_status,
        $doc_type,
        $doc_creator,
        $doc_post_date,
        $doc_modified_date,
        $doc_content,
        array $doc_images_new,
        array $doc_images_old,
        array $doc_tags,
        array $doc_categories
    ) {
        $admin_id = 0; // creator (admin login) id

        // lookup admin login id from username ...

        $cms_data_lookup = $this->cms_db->prepare("SELECT admin_id FROM imported_cms_admins WHERE username = ?");

        $cms_data_lookup->bind_param('s', $doc_creator);
        $cms_data_lookup->execute();
        $cms_data_lookup->store_result();
        $cms_data_lookup->bind_result($admin_id);
        if ($cms_data_lookup->num_rows > 0) {
            $cms_data_lookup->fetch();
            $admin_id  =  $admin_id;
        }

        $cms_data_lookup->free_result();
        $cms_data_lookup->close();

        $categories_list   = "";
        $tags_list         = "";
        $images_list_old   = "";
        $images_list_new   = "";

        if ($doc_categories) {
            foreach ($doc_categories as $cats) {
                foreach ($cats as $cattype => $catvalue) {
                    if ($cattype == 'name') {
                        $categories_list = $categories_list . $catvalue . ", ";
                    }
                }
            }
        }
        if ($doc_tags) {
            foreach ($doc_tags as $tags) {
                foreach ($tags as $tagtype => $tagvalue) {
                    if ($tagtype == 'name') {
                        $tags_list = $tags_list . $tagvalue . ", ";
                    }
                }
            }
        }

        if ($doc_images_old) {
            $images_list_old    = implode(", ", $doc_images_old);
        }
        if ($doc_images_new) {
            $images_list_new     = implode(", ", $doc_images_new);
        }

        $dbinsert = $this->cms_db->prepare("INSERT INTO imported_cms_content (admin_id, title, slug, status, doc_type,  post_date, modified_date, content_html, categories_list, tags_list, images_list_old, images_list_new) VALUES (?,?, ?,?,?,?,?,?,?,?,?,?)");

        $dbinsert->bind_param('isssssssssss', $admin_id, $doc_title, $doc_slug, $doc_status, $doc_type, $doc_post_date, $doc_modified_date, $doc_content, $categories_list, $tags_list, $images_list_old, $images_list_new);
        $dbinsert->execute();

        $content_id = $dbinsert->insert_id;

        if (WP_CMS_SILENT == "OFF") {
            echo "Parsed WP Content Record Added to DB Table - > ID #: " . $content_id . "<br>";
        }
        // Add CATEGORY AND TAG ASSOCIATIONS TO THIS NEW RECORD

        if ($doc_categories) {
            foreach ($doc_categories as $cats) {
                foreach ($cats as $cattype => $catvalue) {
                    if ($cattype == 'name') {
                        $cat_name = $catvalue;
                        $this->db_add_category_association($cat_name, $content_id);
                    }
                }
            }
        }
        if ($doc_tags) {
            foreach ($doc_tags as $tags) {
                foreach ($tags as $tagtype => $tagvalue) {
                    if ($tagtype == 'name') {
                        $tag_name = $tagvalue;
                        $this->db_add_tag_association($tag_name, $content_id);
                    }
                }
            }
        }

        # add images to copy log in case you want to move them from old server to new as a batch.

        foreach ($doc_images_old as $images) {

            $image_url = trim($images);

            $dbinsert = $this->cms_db->prepare("INSERT INTO imported_cms_images_copy_log (image_url) VALUES (?)");

            $dbinsert->bind_param('s', $image_url);
            $dbinsert->execute();
        }
    }

    /**
     * Copies images from the source (WP) server to the new CMS or development server.
     *
     * This function will only be triggered if public function process() $type is "images"
     */

    private function db_copy_images()

    {
        $copy_status = 0;

        $cms_data_lookup = $this->cms_db->prepare("SELECT image_id, image_url FROM imported_cms_images_copy_log WHERE image_copied = ? ORDER BY image_id ASC");

        $cms_data_lookup->bind_param('i', $copy_status);
        $cms_data_lookup->execute();
        $cms_data_lookup->store_result();
        $cms_data_lookup->bind_result($image_id, $image_url);
        if ($cms_data_lookup->num_rows > 0) {

            while ($cms_data_lookup->fetch()) {

                $dom = new DOMDocument();
                $dom->loadHTML($image_url);
                // get image name, and source directory info.
                $imgs = $dom->getElementsByTagName('img');

                foreach ($imgs as $img) {
                    $src = "";

                    if ($img->hasAttribute('src')) {
                        $src = $img->getAttribute('src');
                    }
                    $file_name = basename($src);
                    $file_url = $src;
                    $file_domain = explode("/", $src);
                    $src = str_replace($file_domain[2], "", $src);
                    $src = str_replace("https:///", "", $src);
                    $src = str_replace("http:///", "", $src);
                    if ($this->uploads_folder != "") {
                        $src = str_replace('wp-content', $this->uploads_folder, $src);
                    }
                    if ($this->uploads_folder_path == "/") {
                        $this->uploads_folder_path = "";
                    }
                    //$src = $this->uploads_folder_path. $src;
                    $folder = str_replace($file_name, "", $src);

                    // Create new folder for copied image if it doesn't exist;
                    if (!file_exists($folder)) {
                        mkdir($folder, 0755, true);
                    }
                }
                $destination_save = $folder . $file_name; // full path the file name to save copied image
                copy($file_url, $destination_save);

                if (WP_CMS_SILENT == "OFF") {
                    echo "Copied - > " . $destination_save . "<br>";
                }

                $dbupdate = $this->cms_db->prepare("UPDATE imported_cms_images_copy_log SET image_copied = 1 WHERE image_id = ?");

                $dbupdate->bind_param('i', $image_id);
                $dbupdate->execute();
            }
        }

        $cms_data_lookup->free_result();
        $cms_data_lookup->close();
        $this->processed = "images";
    }


    public function __destruct()
    {
        if ($this->processed != "" && WP_CMS_SILENT == "OFF") {
            echo "<br><hr><strong>XML Import Completed.</strong>";
        }
    }
}
