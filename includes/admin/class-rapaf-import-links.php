<?php


/**
 * Handle the Link Inserter page.
 *
 * @since      
 * 
 */
class RAPAF_ImportLinks {
    
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_import_links_page' ) );
        // add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}


    /**
	 * Add the submenu to the menu.
	 *
	 * @since	5.0.0
	 */
	public static function add_import_links_page() {
        // add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);

		add_submenu_page( 'shoppable_recipes', // use another submenu page as parent to avoid adding a new menu item
            'Import Links', 
            'Import Links', 
            'manage_options',
            'import_links', array( __CLASS__, 'importer_init' ) );  //make sure sub_menu_slug here is the same as main menu slug if you don't want main menu to repeat as a sub menu
	}
    
	
    public static function importer_init() {
        RAPAF_ADMIN_Commons::print_plugin_title();
        ?>
        <br>
        <p>Step 1: Edit the ingredient/link list from a template</p>
        <p>Step 2: Export to an csv file. Note that the columns should be: <b>ingredient_name</b>, <b>link</b></p>
        <p>Step 3: User the buttons below to choose and upload the csv</p>
        
        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['upload_csv'])) {
            self::handle_upload();
        } else if ($_SERVER["REQUEST_METHOD"] == "POST" 
                    && (isset($_POST['uploaded_file']))
                    && !isset($_POST['cancel_button'])  ) {
            $rows = RAPAF_ADMIN_Commons::get_update_list_from_csv_file(get_attached_file($_POST['uploaded_file']));
            if(!empty($rows) && !empty($rows['valid'])) {
                RAPAF_ADMIN_Commons::handle_update_db($rows['valid'], 'import');
            }
           
        } else {
            self::upload_form();
        }
    
    }

    public static function upload_form() {
        ?>
        <div id="adaf-main">
            
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
                
            
                $to_update = RAPAF_ADMIN_Commons::get_update_list_from_csv_file(get_attached_file($uploaded));
                if (!empty($to_update) && !empty($to_update['valid'])){
                    self::display_list($to_update);
                    self::update_database_form('upload', $uploaded);
                } else {
                    self::no_valid_rows_form();
                }
            }
        }
    }


    public static function display_list($data) {
        $SHOW_THUMBNAIL = false;
        //print_r($data);
        $rows = $data['valid'];
        if (!empty($rows)) {
            ?>
            <p><?php echo count($rows);?> links have been uploaded, review them and click the apply button to insert affiliate links</p>
            <div class="table">
                <div class="tr">
                    <!-- <div class="td">Operation</div> -->
                    <div class="td">Ingredient Name</div>
                    <!-- <div class="td">term_id</div> -->
                    <!-- <div class="td link">Existing Link</div> -->
                    <div class="td link">Link</div>
                    <div class="td">Number of Recipes</div>
                </div>
            <?php
            foreach($rows as $row) {
                $url = $row['new_link'];
                
                /////////// <tr>
                print('<div class="tr '.$row['operation'].'">');


                //print('<div class="td">'.$row['operation'].'</div>');
                print('<div class="td">'.$row['name'].'</div>');
                //print('<div class="td">'.$row['term_id'].'</div>');
                //print('<div class="td">'.$row['old_link'].'</div>');
                print('<div class="td">');
                $image_url = $row['image_url'];

                
                if ($SHOW_THUMBNAIL && !empty($image_url)) {
                    print('<img src="'.$image_url.'" alt="image" class="rapaf_prod_thumbnail">');
                }
                echo '<a href="'.esc_url($url).'" target="_blank">'.esc_html($url).'</a>';
                print('</div>');
                
                print('<div class="td">');
                $posts = RAPAF_ADMIN_Commons::get_posts_by_term($row['term_id']);
                print(count($posts));
                //print_r(array_column($posts, 'post_title'));
                print('</div>');

                ///////////
                print('</div>');
            }
            print('</div>');
        }
        $rows = $data['invalid'];
        if (!empty($rows)) {
            ?>
            <br><br>
            <div class="title">The following links were invalid and will not be inserted. </div>
            <div class="table">
                <div class="tr">
                   <div class="td">Ingredient Name</div>
                    <div class="td link">Link</div>
                </div>
            <?php
            foreach($rows as $row) {
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
        print('<br><br>');
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
        <input type="submit" name="submit" id="submit" class="button button-primary" value="Apply all recommendations">    
        <input type="submit" name="cancel_button" class="button" value="Cancel">   
        <?php
        //submit_button('Update Database');
        ?>
         
        </p>
        </form>
        <?php
    }

}
RAPAF_ImportLinks::init();