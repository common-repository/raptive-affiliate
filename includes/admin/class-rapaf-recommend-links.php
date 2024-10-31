<?php


/**
 * Handle the Recommendations page.
 *
 * @since      
 * 
 */



class RAPAF_RecommendLinks {
    
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_recommend_links_page' ) );
        // add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}


    /**
	 * Add the submenu to the menu.
	 *
	 * @since	5.0.0
	 */
	public static function add_recommend_links_page() {
        // add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);

            add_submenu_page( RAPAF_Admin_Menu::$default_page, 
            'Shoppable Recipes', 
            'Shoppable Recipes', 
            'manage_options',
            'shoppable_recipes', array( __CLASS__, 'inserter_init' ) );  //make sure sub_menu_slug here is the same as main menu slug if you don't want main menu to repeat as a sub menu
       
	}
    
	
    public static function inserter_init() {
        RAPAF_ADMIN_Commons::print_plugin_title();
        ?>
        <br>
        <h2>Shoppable Recipes</h2>
        
        <?php
        
        
        if (!RAPAF_ADMIN_Commons::are_third_party_dependencies_met()) {
            self::enable_dependencies_warning();
            return;
        } 
	    
        if ($_SERVER["REQUEST_METHOD"] == "POST" 
                    && (isset($_POST['raptive_selected_data']))
                    && !isset($_POST['raptive_cancel_button'])  ) {
            $data = json_decode(stripslashes($_POST['raptive_selected_data']), true);
            $rows = RAPAF_ADMIN_Commons::get_update_list_from_json($data, false);
            RAPAF_ADMIN_Commons::handle_update_db($rows['valid'], 'recommendations');
        } else {
            self::fetch_remote();
        }
        ?>
        <br>
        <div style="width:1300px;">
        Applied recommendations accidentally? Revert them <a href="?page=rapaf_rollback">here</a>.<br>
        Want to add your own recommendations? Use the <a href="?page=import_links">csv upload</a> to apply the recommendations.<br>
        </div>
        <?php

    }


    public static function fetch_remote() {
        //this has [valid_list, invalid_list]
        $settingsObj = RAPAF_Commons::get_affiliate_settings_object();
        $affiliateApiPath = $settingsObj['affiliateApiPath'];
        
        $catalog_url = $affiliateApiPath.'/v1/get/catalog?format=csv&header=false';
        if (isset($_GET['debug'])) {
            $debug = $_GET['debug'];
            if ($debug == 'true') {
                $catalog_url .= '&debug=true';
            } 
        }
        
        $to_update = RAPAF_ADMIN_Commons::get_update_list_from_remote_csv($catalog_url);
        if ($to_update == false) {
            self::remote_server_error_form();
        } else if (!empty($to_update) && !empty($to_update['valid'])){
            self::display_list($to_update);
            
        } else {
            self::no_valid_rows_form();
        }
            
    }

    

    public static function display_list($data) {
        $SHOW_THUMBNAIL = false;

        $rows = $data['valid'];
        if (!empty($rows)) {
            ?>
            <script type="text/javascript">
                
                jQuery(document).ready(function () {
                    //alert('ready');
                    
                    jQuery('#recommendations').DataTable({
                        //"bProcessing"   :   true,
                        scrollY: '500px',
                        scrollCollapse: true,
                        paging: false,
                        columnDefs: [ {
                            className: 'select-checkbox',
                            targets:   0
                        } ],
                        order: [[3, 'desc']],
                        select: {
                            style: 'multi'
                        },
                        dom: 'Bfrtip',
                        buttons: [
                            //'copyHtml5',
                            //'excelHtml5',
                            //'csvHtml5'
                            'selectAll',
                            'selectNone',
                            {
                                text: 'Top 50',
                                action: function ( e, table, node, config ) {
                                    length = table.rows().data().length;
                                
                                    table.rows().deselect();
                                    table.rows( ':lt(50)' ).select();
                                    
                                    //alert(table.rows('.selected').data().length + ' row(s) selected');
                                }
                            },
                            {
                                text: 'Top 100',
                                action: function ( e, table, node, config ) {
                                    length = table.rows().data().length;
                                    table.rows().deselect();
                                    table.rows( ':lt(100)' ).select();
                                }
                            }
                        ],
                        language: {
                            buttons: {
                                selectAll: "All",
                                selectNone: "None"
                            }
                        }
                        
                        
                    });
                   
                      
                    jQuery('#raptive_apply_button').on("click", function(){
                        
                        var table = jQuery('#recommendations').DataTable();
                       
                        var all = table.rows().data();
                        var selected = table.rows('.selected').data();
                        
                        if (selected.length == 0) {
                            alert('Please select at least one row');
                            return false;
                        }
                        plugin_version = jQuery('div#rapaf_version_tag>span.version_number').text();
                        
                        send_ingredient_match_event(plugin_version, window.location.hostname, all.length, selected.length);

                        //using json to handle comma in ingredients
                        //can't use selected.map to generate toUpdate cause selected is a circular object
                        //toUpdate is an array of objects
                        //
                        let i = 0;
                        let toUpdate = [];
                        while (i < selected.length) {
                            toUpdate[i] = {
                                "ingredient_name": selected[i][1],
                                "link": jQuery(selected[i][2]).text()
                            };
                            i++;
                        }

                        console.log(toUpdate);
                        jQuery('#raptive_selected_data').val(JSON.stringify(toUpdate));
                    });
                    
                });
            </script>
            <form  method="post" enctype="multipart/form-data">
            When enabled, Raptive will convert selected ingredients in your recipe card into an affiliate link for retailers in our network.<br>
            You must sign Raptive’s Affiliate Terms of Service Addendum before running this feature. Reach out to 
            <a href="https://raptive.com/contact">Raptive Support</a> for more details.


            <div style="width:1300px;text-align:right">
                <p class="actions">
                <input type='hidden' id='raptive_selected_data' name='raptive_selected_data'></input>
                <input type="submit" name="submit" id="raptive_apply_button" class="button button-primary" value="Apply link recommendations">    
                
                </p>
            </div>
            <div class="title">We found <?php echo count($rows);?> link recommendations for ingredients, review them and click the apply button to insert affiliate links.
            </div>
            <br>
            <div class="table">
            <table id="recommendations" class="display">
                <thead>
                    <tr>
                        <th></th>
                        <th>Ingredient Name</th>
                        <th>Link</th>
                        <th>Number of Recipes</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                foreach($rows as $row) {
                    $url = $row['new_link'];
                    
                    print('<tr class="'.$row['operation'].'">');
                    print('<td></td>');
                    print('<td>'.$row['name'].'</td>');
                    
                    print('<td>');

                    $image_url = $row['image_url'];
                    if ($SHOW_THUMBNAIL && !empty($image_url)) {
                        print('<img src="'.$image_url.'" alt="image" class="rapaf_prod_thumbnail">');
                    }
                    
                    echo '<a href="'.esc_url($url).'" target="_blank">'.esc_html($url).'</a>';
                    print('</td>');
                    
                    print('<td>');
                    $posts = RAPAF_ADMIN_Commons::get_posts_by_term($row['term_id']);
                    print(count($posts));
                    print('</td>');


                    ///////////
                    print('</tr>');
                }
                ?>
                </tbody>
                </table>
                </div>
                </form>
               
        <?php
        } 

        $rows = $data['invalid'];
        if (!empty($rows)) {
            ?>
            <br><br>
            <div class="title">The following links are invalid and will not be inserted. </div>
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
    }

    public static function remote_server_error_form() {
        ?>
         <form  method="post" enctype="multipart/form-data">
        <p class="warning">Sorry, we are unable to pull your recommendations. Please try again later.</a>
        <p class="actions">
        <input type="submit" name="raptive_cancel_button" class="button" value="Cancel"> 
        </p>
        </form>  
        <?php
    }

    public static function no_valid_rows_form() {
        ?>
         <form  method="post" enctype="multipart/form-data">
        <p class="warning">No new recommendations at this moment</a>
        <p class="actions">
        <input type="submit" name="raptive_cancel_button" class="button" value="Cancel"> 
        </p>
        </form>  
        <?php
    }

    public static function enable_dependencies_warning() {
    ?>
        <p class="warning">Some features are not available. WordPress Recipe Maker Premium is required to enable Shoppable Recipes.</a>
        <?php
    }

}


RAPAF_RecommendLinks::init();