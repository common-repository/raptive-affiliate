<?php

/**
 * Handle the Link Inserter page.
 *
 * @since      
 * 
 */
class RAPAF_Rollback {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_rollback_page' ) );
    }


    /**
	 * Add the submenu to the menu.
	 *
	 * @since	5.0.0
	 */
	public static function add_rollback_page() {
       
		add_submenu_page(
            'shoppable_recipes', // use another submenu page as parent to avoid adding a new menu item
            'Last Action', 'Last Action', 
            'manage_options', 
            'rapaf_rollback', array( __CLASS__, 'rollback_init' ) );
	}
    
	
    public static function rollback_init() {
        RAPAF_ADMIN_Commons::print_plugin_title();
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['batch_id'])) {
            self::handle_rollback($_POST['batch_id']);
        } else {
            self::rollback_form();
        }
    }

    public static function rollback_form() {
        global $wpdb;
        $table_name = RAPTIVE_TERMMETA_AUDITTRAIL_TABLE;

        $sql = "SELECT *, UNIX_TIMESTAMP(created_at) as created_unix FROM {$table_name} 
                WHERE batch_id IN
                (
                SELECT batch_id FROM {$table_name}  
                WHERE created_at IN 
                (
                SELECT max(created_at) FROM {$table_name}  WHERE batch_id <> '' 
                 AND rolled_back is null
                ))
                AND rolled_back is null
                AND meta_key = 'wprmp_ingredient_link'
            ";
        //the second "rolled_back is null" is for the edge case where not all last batch actions were rolled back

        //var_dump($sql);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        //print_r($rows);

        
        if (empty($rows)) {
            print('<p class="info">No past action found in audit trail</p>');
            return;
        }


        $created_at = $rows[0]['created_unix'];
        $datetime = new \DateTime();  //global class use "\" to escape current namespace
        $datetime->setTimestamp($created_at);
        $datetime->setTimeZone(new \DateTimeZone('US/Eastern'));

        $page_urls = self::get_affected_pages($rows);
        
        ?>
        <br><br>
        <h2>Last batch action</h2>
        <form  method="post" enctype="multipart/form-data">
        <input type='hidden' id='batch_id' name='batch_id' value='<?php echo esc_html($rows[0]['batch_id']); ?>'></input>
        

        <p><?php echo count($rows); ?> ingredient links have been inserted in this previous action (at <?php echo $datetime->format('Y-m-d H:i:s'); ?> EST)
        <input type="submit" name="submit" id="submit" class="button button-primary" value="Rollback Previous Changes" style="margin-left:100px">
        </p>
        </form>
        <br><br>
        <h2>Here is a list of some of the recipe pages that have been updated.</h2>
        <div class="table">
            <div class="tr">
                <div class="td">URL</div>
            </div>
            <?php
            foreach($page_urls as $url) {
                ?>
                <div class="tr">
                <div class="td">
                    <a target="_blank" href="<?php echo esc_html($url); ?>"><?php echo esc_html($url); ?></a>
                </div>
                </div>
            <?php
            }
        ?>
        </div>

        <br><br><br><br>
        <h2>Here's the list of ingredients that have been updated in the previous action.</h2>
        <div class="table">
            <div class="tr">
                <div class="td">Ingredient Name</div>
                <!-- <div class="td">term_id</div> -->
                <!-- <div class="td link">old value</div> -->
                <div class="td link">Links</div>
                
            </div>
        <?php
        foreach($rows as $row) {
            print('<div class="tr">');
            $term = get_term($row['term_id'], 'wprm_ingredient', ARRAY_A);
            print('<div class="td">'.esc_html($term['name']).'</div>');
            //print('<div class="td">'.esc_html($row['meta_value_old']).'</div>');
            print('<div class="td">'.esc_html($row['meta_value_new']).'</div>');
            
            print('</div>');
        }
        print('</div>');
        ?>
       
        
        <?php 
       
    }

    public static function get_affected_pages($rows) {
        $MAX_PAGE_NUM = 5;
        $ingredient_posts = array();
        for($i=0; $i < min($MAX_PAGE_NUM*2, count($rows)); $i++) {
            $term = $rows[$i];
            $posts = RAPAF_ADMIN_Commons::get_posts_by_term($term['term_id']);
            if (!empty($posts)) {
                $ingredient_posts[] = $posts;
            }
        }
        
        $affected_pages = array();
        for($i=0; $i<count($ingredient_posts); $i++) {
            $posts = $ingredient_posts[$i];
            
            if (!empty($posts)) {
                $post = $posts[0];
                
                $page_post_id = get_post_meta($post->ID, 'wprm_parent_post_id', TRUE);
                if ($page_post_id) {
                    $page_url = get_permalink($page_post_id);
                    if (!in_array($page_url, $affected_pages)) {
                        $affected_pages[] = $page_url;
                    }
                }
            }
        }
        return array_slice($affected_pages, 0, $MAX_PAGE_NUM);
    }
	public static function handle_rollback($batch_id) {
        global $wpdb;

        $table_name = RAPTIVE_TERMMETA_AUDITTRAIL_TABLE;
        $sql = $wpdb->prepare("SELECT * FROM {$table_name}
                WHERE batch_id = %s AND rolled_back is null", $batch_id);
              
        //var_dump($sql);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        //print_r($rows);
        if (empty($rows)) {
            print('<p class="info">No row found </p>');
            return;
        }
        $count_success = 0;
        $count_ingredient_link_success = 0;

        // there is an edge case where the last batch action is not rolled back completely, say there were two rows of the same ingredient
        // in the audit trail, and the first one is rolled back, but the second one is not. But the second one will not be able to get rolled back
        // since the meta record is already deleted.  We are not handling this edge case here. This case should be prevented in the update step.
        foreach($rows as $row) {
            if (!is_null($row['meta_value_old']) && strlen($row['meta_value_old']) > 0) {
                // update
                $result_check = update_term_meta( $row['term_id'], $row['meta_key'], $row['meta_value_old'] );
            } else {
                $result_check =  delete_term_meta($row['term_id'], $row['meta_key']);
            }
            if ($result_check == true) {
                $sql = "update {$table_name} SET rolled_back = true WHERE audit_trail_id = %d";
                $sql = $wpdb->prepare($sql, $row['audit_trail_id']);
                $result_check = $wpdb->query($sql);
                $count_success ++;
                if ($row['meta_key'] == 'wprmp_ingredient_link') {
                    $count_ingredient_link_success ++;
                }
                
            }
            
        }
        print('<div class="success"><p>Rolled back '.$count_ingredient_link_success.' ingredient links</p></div>'); 
        //print('<div class="success"><p>Rolled back '.$count_success.' records</p></div>');  
        ?>
         <form  method="post" enctype="multipart/form-data">
        <p class="actions">
      
        </p>
        </form>  
        <?php
    }


}


RAPAF_Rollback::init();