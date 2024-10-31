<?php


/**
 * Common backend functions
 *
 * @since      
 * 
 */
function warning_handler($errno, $errstr) { 
    // do something
}


class RAPAF_Commons {

    public static function print_plugin_title() {
        ?>
        <div id="rapaf_version_tag">Version: <span class="version_number"><?php echo RAPTIVE_AFFILIATE_VERSION; ?></span></div>
        <h1>Raptive Affiliate Plugin</h1>

        <?php
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


    // this is a costly function since we have to get the entire HTML. OK for one link but not for hundreds. 
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


    public static function get_update_list_from_remote_csv($url) {
        $auth_key_name = 'x-api-key';
        $auth_key_val = 'ox8IWkcay_3D';
        //print($url);
        
        set_error_handler("warning_handler", E_WARNING);
        $request = wp_remote_get( $url, array( 'headers' => array($auth_key_name => $auth_key_val) ));
        $csv = wp_remote_retrieve_body( $request );
        if ($csv === FALSE) {
            return FALSE;
        }
        restore_error_handler();
            
        return self::get_update_list_from_csv($csv);
    }


    public static function get_update_list_from_csv_file($file) {
        $data = file_get_contents($file);
        return self::get_update_list_from_csv($data);
    }


    public static function process_update_row($cols, &$result, $insertTargetParam=True) {
        $allowed_operations = array('insert'); //no_change
        //$allowed_operations = array( 'update', 'insert'); //'no_change'

        if (count($cols) >=2 && trim($cols[1]) != '') {
            $ingredient_name = $cols[0];
            $ingredient_link = trim($cols[1]);
            // print('<br>'.$ingredient_name.'<br>');
            
            // print($ingredient_link.'<br>');
            $term = get_term_by( 'name', $ingredient_name, 'wprm_ingredient' );
            
            // only include existing terms
            if ( $term && ! is_wp_error( $term ) ) {
                $term_id = $term->term_id; 
                $old_link = get_term_meta($term_id, 'wprmp_ingredient_link', true); //return single value

                if (filter_var($ingredient_link, FILTER_VALIDATE_URL) === FALSE) {
                    //print($ingredient_link.' is not a valid URL, skipping');
                    $result['invalid'][] = array('name'=> $ingredient_name, 'new_link'=> $ingredient_link);
                    return;
                }
                if ($insertTargetParam) {
                    $ingredient_link = self::insert_target_tracking_param($ingredient_link);
                }


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

                //print($operation.'<br>');
                $image_url = count($cols) > 2 ? $cols[2] : '';
                if (filter_var($image_url, FILTER_VALIDATE_URL) === FALSE) {
                    $image_url = '';
                } else {
                    $image_url = sanitize_url($image_url);
                }
             
                if (in_array($operation, $allowed_operations) ) {
                    $found = 0;
                    // if the term_id is already in the set, overwrite the link instead of adding a new one
                    foreach($result['valid'] as &$update_row) {
                        if ($term_id == $update_row['term_id']) {
                            $update_row['new_link'] = $ingredient_link;
                            $update_row['operation'] = $operation;
                            $update_row['image_url'] = $image_url;
                            $found = 1;
                            break;
                        }
                    }
                    if ($found == 0) {
                        $result['valid'][] = array('name'=> $ingredient_name, 
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


    public static function get_update_list_from_csv($data, $insertTargetParam=True) {
        $rows = explode("\n",$data);
        $result = array('valid'=>array(), 'invalid'=>array());
        foreach($rows as $row) {
            $cols = str_getcsv($row);
            // ignore links that are empty
            self::process_update_row($cols, $result, $insertTargetParam);
        }
        return $result;
     }


     // $data is an array of objects 
     // {
     //     "ingredient_name": x[1],
     //     "link": jQuery(x[2]).text()
     // }
     public static function get_update_list_from_json($data, $insertTargetParam=True) {
        $result = array('valid'=>array(), 'invalid'=>array());

        foreach($data as $row) {
            $cols = array($row['ingredient_name'], $row['link']);
            self::process_update_row($cols, $result, $insertTargetParam);
        }
        return $result;
     }


     public static function handle_update_db($rows, $page_source) {
        global $wpdb;
        
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
            $errored_rows = array();
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
                            //print('<p class="error">Update term error: '. $result->get_error_message().'</p>');
                            $count_error ++;
                            $errored_rows[] = $row;

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
            print('<div class="success">');
            // print('<p>Updated '.$count_update.' links</p>');  
            // print('<p>Inserted '.$count_insert.' links</p>');
            print('<p>Success! We\'ve added affiliate links for '.$count_success.' ingredients.</p>' );
            print('</div>');
        
            RAPAF_Analytics::send_affiliate_link_insertion_analytics(
                    array(
                        'page_source'=>$page_source,
                        'success'=> $count_success, 
                        'error'=>$count_error, 
                        'total'=>count($rows)
                        )
                );


            //print('<p class="info">Attempted to update with the same value: '.$count_update_noop.' links</p>');
            //print('<p class="success">Rows affected: '.$count_success.'</p>');
            if ($count_error > 0) {
                print('<p>However, we were unable to update the links for the following ingredients: </p>');
                
                if (!empty($errored_rows)) {
                    ?>
                    <br><br>
                    <div class="table">
                        <div class="tr">
                           <div class="td">Ingredient Name</div>
                            <div class="td link">Link</div>
                        </div>
                    <?php
                    foreach($errored_rows as $row) {
                        $url = $row['new_link'];
                        
                        print('<div class="tr">');
                        //print('<div class="td">'.$row['operation'].'</div>');
                        print('<div class="td">'.$row['name'].'</div>');
                        
                        print('<div class="td">');
                        print(esc_html($url));
                        print('</div>');
                        
                        print('</div>');
                    }
                    print('</div>');
                } 
            }
        }
    }

    
    /**
     * for every operation, we add a new audit trail record for old_value, new_value
     * no need for meta_id because we will use update_term_meta() or delete_term_meta(), neither needs the meta_id
     * $data columns: term_id, batch_id, meta_key, meta_value_old, meta_value_new
     * @param array $data
     * @return None
     *
     */
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

    /**
     * Insert affiliate tracking param.
     * @param string $url The URL to insert the tracking param into.
     * @return string The URL with the tracking param inserted.
     *  example inserts 
     *  https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688
     *  -> https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688?aflt=plt
     *    
     *    https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688?something=a
     *   -> https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688?something=a&aflt=plt
        
     *    https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688?aflt=a
     *    -> https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688?aflt=a
     *    
     *    https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688#aflt=plt
     *    -> https://www.target.com/p/daisy-pure-38-natural-sour-cream-16oz/-/A-13451688?aflt=plt#aflt=plt
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


    // This takes a really long time for non-existing urls.  Not suitable for verifying the list of imported links. 
    public static function does_page_exist($url) {
        //$metatags = get_meta_tags($url);
        //print_r(get_meta_tags($url));
        
        $file_headers = @get_headers($url);
        $page_exists = true;
        if(!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found') {
            $page_exists = false;
            //echo $url . ' does not exist<br>';
         
        }
        return $page_exists;
    }

    
    
}
