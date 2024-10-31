<?php


/**
 * Handle the Link Inserter page.
 *
 * @since      
 * 
 */
class RAPAF_LinkInserter {
    public static $remote_csv_url = 'https://affiliate-public.s3.amazonaws.com/ingredient_links.csv';
    
    public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_link_inserter_page' ) );
		// add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

        
	}


    /**
	 * Add the submenu to the menu.
	 *
	 * @since	5.0.0
	 */
	public static function add_link_inserter_page() {
        // add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);

		add_submenu_page( 'rapaf_main', 'Link Inserter', 'Link Inserter', 
            'manage_options', //RAPAF_Settings::get( 'features_manage_access' ), 
            'rapaf_main', array( __CLASS__, 'inserter_init' ) );  //make sure sub_menu_slug here is the same as main menu slug if you don't want main menu to repeat as a sub menu
	}
    
	
    public static function inserter_init() {
        ?>
    
        <h1>Raptive Affiliate - WP Recipe Maker affiliate link inserter</h1>

        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['fetch_remote'])) {
            self::fetch_remote();
        } else if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['upload_csv'])) {
            self::handle_upload();
        } else if ($_SERVER["REQUEST_METHOD"] == "POST" 
                    && (isset($_POST['uploaded_file']) || isset($_POST['remote_file']))
                    && !isset($_POST['cancel_button'])  ) {
            self::handle_update_db();
        } else {
            self::upload_form();
            // self::rollback_form();
        }
    
    }

    public static function upload_form() {
        ?>
        <div id="adaf-main">
            <h2>Upload the csv file here</h2>
            <p class="info">Note that the file should not have headers. Columns are: ingredient_name, link, update (Y/N)</p> 
            <!-- Form to handle the upload - The enctype value here is very important -->
            <form  method="post" enctype="multipart/form-data">
                <input type='file' id='upload_csv' name='upload_csv'></input>
                <?php submit_button('Upload');
                ?>
                <!-- <input type="submit" name="fetch_remote" id="submit" class="button button-primary" value="Fetch from remote">    -->
            </form>

            
        </div>
        <?php
    }
    
    public static function handle_upload() {
        // First check if the file appears on the _FILES array
        if(isset($_FILES['upload_csv'])) {
            $csv = $_FILES['upload_csv'];
            // Use the wordpress function to upload
            // upload_csv corresponds to the position in the $_FILES array
            // 0 means the content is not associated with any other posts
            $uploaded = media_handle_upload('upload_csv', 0);
            // Error checking using WP functions
            //echo 'uploaded is '.$uploaded;
            if(is_wp_error($uploaded)){
                echo "<p>Error uploading file: " . $uploaded->get_error_message()."</p>";
            } else {
                echo '<p class="success">File uploaded successfully!</p><br>';
                
            
                $to_update = self::get_update_list_from_csv_file(get_attached_file($uploaded));
                if (!empty($to_update)){
                    self::display_list($to_update);
                    self::update_database_form('upload', $uploaded);
                } else {
                    self::no_valid_rows_form();
                    
                }
                
            }
        }
    }

    public static function fetch_remote() {
        // First check if the file appears on the _FILES array
        $csv = file_get_contents(self::$remote_csv_url);
        
        $to_update = self::get_update_list_from_csv($csv);
        if (!empty($to_update)){
            self::display_list($to_update);
            
            self::update_database_form('remote', $csv);
        } else {
            self::no_valid_rows_form();
        }
            
    }

    public static function display_list($rows) {
        $SHOW_THUMBNAIL = false;

        stream_context_set_default( [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        
        if (!empty($rows)) {
            ?>
            <div class="title">Ingredient-Links that will be inserted or updated</div>
            <div class="table">
                <div class="tr">
                    <!-- <div class="td">Operation</div> -->
                    <div class="td">Ingredient Name</div>
                    <!-- <div class="td">term_id</div> -->
                    <div class="td link">Existing Link</div>
                    <div class="td link">Our Recommendations</div>
                    <div class="td">Number of Recipes</div>
                </div>
            <?php
            foreach($rows as $row) {
                $url = $row['new_link'];
                //$headers = get_headers($url);
                //$metatags = get_meta_tags($url);
                //print_r(get_meta_tags($url));
                
                //this check could take a long time.
                // $file_headers = @get_headers($url);
                // $page_exists = true;
                // if(!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found') {
                //     $page_exists = false;
                //     //echo $url . ' does not exist<br>';
                //     //continue;
                // }


                /////////// <tr>
                print('<div class="tr '.$row['operation'].'">');


                //print('<div class="td">'.$row['operation'].'</div>');
                print('<div class="td">'.$row['name'].'</div>');
                //print('<div class="td">'.$row['term_id'].'</div>');
                print('<div class="td">'.$row['old_link'].'</div>');
                print('<div class="td">');
                $image_url = $row['image_url']; //$page_exists ? self::get_prod_image_from_url($url) : ''; 

                
                if ($SHOW_THUMBNAIL && !empty($image_url)) {
                    print('<img src="'.$image_url.'" alt="image" class="rapaf_prod_thumbnail">');
                }
                echo '<a href="'.esc_url($url).'" target="_blank">'.esc_html($url).'</a>';
                print('</div>');
                
                print('<div class="td">');
                $posts = self::get_posts_by_term($row['term_id']);
                print(count($posts));
                //print_r(array_column($posts, 'post_title'));
                print('</div>');


                ///////////
                print('</div>');
            }
            print('</div>');
        } 
    }

    public static function get_posts_by_term($term_id) {
        //$count_posts = wp_count_posts( WPRM_POST_TYPE );
       
        $args = array(
            'post_type' => 'wprm_recipe',
            'post_status' => 'any',
            'nopaging' => true,
            'tax_query' => array(
                array(
                    'taxonomy' => 'wprm_ingredient',
                    'field' => 'id',
                    'terms' => $term_id,
                ),
            )
        );

        $query = new WP_Query( $args );
        $posts = $query->posts;
        //$post['post_title']
        return $posts;
    }


    public static function get_prod_image_from_url($url) {
        $dom = new DOMDocument();
        @$dom->loadHTML(file_get_contents($url));
        //$title = $dom->getElementsByTagName('title')->item(0)->nodeValue;
        $meta = $dom->getElementsByTagName('meta');
        $image_url = ''; //$image_url = 'placeholder.png';
        foreach ($meta as $tag) {
            if ($tag->getAttribute('property') == 'og:image') {
                $image_url = $tag->getAttribute('content');
            }
        }
        return $image_url;
    }


    public static function no_valid_rows_form() {
        ?>
         <form  method="post" enctype="multipart/form-data">
        <p class="warning">No applicable rows found. Please double check the CSV file.</a>
        <p class="actions">
        <input type="submit" name="cancel_button" class="button" value="Cancel"> 
        </p>
        </form>  
        <?php
    }

    public static function update_database_form($type, $data_file) {
        ?>
        <form  method="post" enctype="multipart/form-data">
        <?php if ($type == 'upload') { ?>
            <input type='hidden' id='uploaded_file' name='uploaded_file' value='<?=$data_file?>'></input>
        <?php } else { ?>
            <input type='hidden' id='remote_file' name='remote_file' value='<?=self::$remote_csv_url?>'></input>
        <?php } ?>
        <p class="actions">
        <input type="submit" name="submit" id="submit" class="button button-primary" value="Update Database">    
        <input type="submit" name="cancel_button" class="button" value="Cancel">   
        <?php
        //submit_button('Update Database');
        ?>
         
        </p>
        </form>
        <?php
    }

    public static function get_update_list_from_csv_file($file) {
        $data = file_get_contents($file);
        return self::get_update_list_from_csv($data);
    }

    public static function get_update_list_from_csv($data) {
        
        $rows = explode("\n",$data);
        
        $allowed_operations = array('insert'); //no_change
        //$allowed_operations = array( 'update', 'insert'); //'no_change'
        
        $to_update = array();
        foreach($rows as $row) {
            $cols = str_getcsv($row);
            // ignore non 'Y' rows and links that are empty
            if (count($cols) > 2 && $cols[2] == 'Y' && trim($cols[1]) != '') {
                $ingredient_name = $cols[0];
                $ingredient_link = trim($cols[1]);
                //print('<br>'.$ingredient_name.'<br>');

                if (filter_var($ingredient_link, FILTER_VALIDATE_URL) === FALSE) {
                    //print('Not a valid URL, skipping');
                    continue;
                }

                $ingredient_link = self::insert_target_tracking_param($ingredient_link);

                $term = get_term_by( 'name', $ingredient_name, 'wprm_ingredient' );

                // only include existing terms
                if ( $term && ! is_wp_error( $term ) ) {
                    $term_id = $term->term_id; 
                    $old_link = get_term_meta($term_id, 'wprmp_ingredient_link', true); //return single value
                    if ($old_link) {
                        // update
                        if ($old_link == $ingredient_link) {
                            $operation = 'no_change';
                        } else {
                            $operation = 'update';
                        }
                        
                    } else {
                        $operation = 'insert';
                        $old_link = '';
                    }

                    $image_url = count($cols) > 3 ? $cols[3] : '';
                    $image_url = sanitize_url($image_url);
                 
                    if (in_array($operation, $allowed_operations) ) {
                        $found = 0;
                        // if the term_id is already in the set, overwrite the link instead of adding a new one
                        foreach($to_update as &$update_row) {
                            
                            if ($term_id == $update_row['term_id']) {
                                $update_row['new_link'] = $ingredient_link;
                                $update_row['operation'] = $operation;
                                $update_row['image_url'] = $image_url;
                                $found = 1;
                                break;
                            }
                        }
                        if ($found == 0) {
                            $to_update[] = array('name'=> $ingredient_name, 
                            'operation'=>$operation,
                            'term_id'=> $term_id,
                            'new_link'=> $ingredient_link, 
                            'old_link'=> $old_link,
                            'image_url'=> $image_url);
                        }
                        
                    }
                    
                }
            }
        }
        return $to_update;
     }


     public static function handle_update_db() {
        global $wpdb;
        
        if (isset($_POST['uploaded_file'])) {
            $rows = self::get_update_list_from_csv_file(get_attached_file($_POST['uploaded_file']));
        } else if (isset($_POST['remote_file'])) {
            $rows = self::get_update_list_from_csv_file($_POST['remote_file']);
        } else {
            return;
        }
        if (!empty($rows)) {
            $meta_key = 'wprmp_ingredient_link';
            $meta_key_nofollow = 'wprmp_ingredient_link_nofollow';
            $meta_value_nofollow = 'nofollow';
            $count_update = 0;
            $count_insert = 0;
            $count_error = 0;
            $count_success = 0;
            $count_update_noop = 0;
            $batch_id = wp_generate_uuid4();

            foreach($rows as $row) {
            
                    $ingredient_name = $row['name'];
                    $ingredient_link = $row['new_link'];
                    $old_link = $row['old_link'];
                    //print('<br>'.$ingredient_name.'<br>');
                    $term_id = $row['term_id'];  

                    if ($row['operation'] == 'update' || $row['operation'] == 'insert') {
                        //Meta ID if the key didn't exist. true on successful update, false on failure or if the value passed to the function is the same as the one that is already in the database.
                        $result_check = update_term_meta( $term_id, $meta_key, $ingredient_link );

                        $old_nofollow_value = get_term_meta($term_id, $meta_key_nofollow, true); //return single value
                        $result_check2 = update_term_meta( $term_id, $meta_key_nofollow, $meta_value_nofollow);
                        
                        if ( is_wp_error($result_check) ) {
                            print('<p class="error">Update term error: '. $result->get_error_message().'</p>');
                        } else if (is_int($result_check)) {  // int would also be seen as == true
                            $meta_id = (int)$result_check;
                            $count_insert++;
                            $count_success ++; 
                            self::create_audit_trail(array("term_id"=>$term_id, "batch_id"=>$batch_id, "meta_key"=>$meta_key,
                                                            "meta_value_old"=>NULL, "meta_value_new"=>$ingredient_link));
                        } else if ($result_check == true) {
                            $count_update++;
                            $count_success ++; 
                            self::create_audit_trail(array("term_id"=>$term_id, "batch_id"=>$batch_id, "meta_key"=>$meta_key,
                                                            "meta_value_old"=>$old_link, "meta_value_new"=>$ingredient_link));
                        } else if ($result_check == false) {
                            $count_update_noop++;
                        } 
                        if (is_int($result_check2) || $result_check2 == true) {
                            self::create_audit_trail(array("term_id"=>$term_id, "batch_id"=>$batch_id, "meta_key"=>$meta_key_nofollow,
                                                            "meta_value_old"=>$old_nofollow_value, "meta_value_new"=>$meta_value_nofollow));
                        }
                    }

            } 
            print('<div class="success"><p>Updated '.$count_update.' links</p>');  
            print('<p>Inserted '.$count_insert.' links</p></div>');
        
            //print('<p class="info">Attempted to update with the same value: '.$count_update_noop.' links</p>');
            //print('<p class="success">Rows affected: '.$count_success.'</p>');
            print('<p class="info">Errors: '.$count_error.'</p>');

            print('<button class="button" onclick="window.location.href=\''.admin_url( 'admin.php?page=rapaf_main').'\';">
                Return
                </button>');
        }
    }

    // for every operation, we add a new audit trail record for old_value, new_value
    // no need for meta_id because we will use update_term_meta() or delete_term_meta(), neither needs the meta_id
    // $data columns: term_id, batch_id, meta_key, meta_value_old, meta_value_new
    public static function create_audit_trail($data) {
        global $wpdb;

        $table_name = RAPTIVE_TERMMETA_AUDITTRAIL_TABLE;
        $format = array( '%d', '%s', '%s', '%s', '%s');

        $result_check = $wpdb->insert($table_name, $data, $format);
        $x= $wpdb->last_query;
        $audit_trail_id = $wpdb->insert_id;

        if ($audit_trail_id) {
            //echo 'Inserted with audit_trail_id '.$audit_trail_id.' <br>';
        } else {
            //echo 'No audit trail record created for data {$data}<br>';
        }
    }

    /* example inserts 
    https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688
    -> https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688?aflt=plt
    
    https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688?something=a
    -> https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688?something=a&aflt=plt
    
    https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688?aflt=a
    -> https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688?aflt=a
    
    https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688#aflt=plt
    -> https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688?aflt=plt#aflt=plt
    */
    public static function insert_target_tracking_param($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== 'www.target.com') {
            return $url;
        }
        $pieces = explode("#", $url);
        $url_before_hash = $pieces[0];
        
        $query = parse_url($url_before_hash, PHP_URL_QUERY);
        if ($query) {
           
            parse_str($query, $query_params);

            //if "aflt" is already in the query string, do nothing
            if (array_key_exists('aflt', $query_params)) {
                //print('found aflt');
            } else {
                $url_before_hash .= '&aflt=plt';
            }
            
        } else {
            $url_before_hash .= '?aflt=plt';
        }
        if (count($pieces)>1) {
            return $url_before_hash.'#'.$pieces[1];
        }
        return $url_before_hash;
    }


}


RAPAF_LinkInserter::init();