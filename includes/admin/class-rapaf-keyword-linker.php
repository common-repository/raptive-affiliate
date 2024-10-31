<?php


/**
 * Handle the Keyword Linker page. 
 */

class RAPAF_KeywordLinker {
    
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_keyword_linker_page' ) );
        
	}

    /**
	 * Add the submenu to the menu.
	 */
	public static function add_keyword_linker_page() {
        add_submenu_page(RAPAF_Admin_Menu::$default_page, 
            'Import Links', 
            'Keyword Linker', 
            'manage_options', 
            'keyword_linker', array( __CLASS__, 'importer_init' ) );  //make sure sub_menu_slug here is the same as main menu slug if you don't want main menu to repeat as a sub menu
	}
    
	
    public static function importer_init() {
        RAPAF_ADMIN_Commons::print_plugin_title();
        ?>
        <br>
        <p>The keywords that will be convert into affiliate links on all pages</p>

        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST" and isset($_POST['submit'])) {
            self::handle_insert();
        }
        else {
            if ($_SERVER["REQUEST_METHOD"] == "POST" and isset($_POST['raptive_selected_data'])) {
                self::handle_modify();
            } 
            self::insert_form_togglable();
        }
        $rows = RAPAF_Commons::get_keyword_links();
        self::display_list($rows);
    
    }

    public static function insert_form_togglable() {
        self::insert_form();
        ?>
        <button id="rapaf-add-new-keyword-link-button">Add New</button>
        <script>
            var insertForm = document.getElementById("rapaf-keyword-linker-insert-form");
            insertForm.style.display = "none";
            var addNewkeywordLinkButton = document.getElementById("rapaf-add-new-keyword-link-button");
            addNewkeywordLinkButton.onclick = function() {
                var insertForm = document.getElementById("rapaf-keyword-linker-insert-form");
                if (insertForm.style.display === "none") {
                    insertForm.style.display = "block";
                }
                addNewkeywordLinkButton.style.display = "none";
            };
           
        </script>
        <?php
       
    }

    public static function insert_form() {
        ?>
        <div id="rapaf-keyword-linker-insert-form" class="rapaf-input-form">
            <form method="post" enctype="multipart/form-data">
                <div><span class='label'>Keyword</span><input type='text' id='rapaf-keyword-insert-form-keyword' name='keyword'></input> </div>
                <div><span class='label'>Product Link</span><input type='text' id='rapaf-keyword-insert-form-product_link' name='product_link' class='link'></input></div>
                <?php submit_button('Add');
                ?>
            </form>
        </div>
        <?php
    }
    
    public static function print_fade_out_script() {
        echo "<script>
        var rapafInfo = document.getElementById('rapaf-input-form-feedback');
        if (rapafInfo.className == 'success') {
            timeoutMs = 2000;
        } else {
            timeoutMs = 5000;
        }
        setTimeout(function(){
            rapafInfo.style.display='none';}, timeoutMs);
        </script>";
    }


    public static function handle_modify() {
        if (isset($_POST['raptive_selected_data'])) {
            $data = json_decode(stripslashes($_POST['raptive_selected_data']), true);
            
            if ($data['action'] === 'delete') {
                $keyword_names = $data['keyword_names'];
                foreach($keyword_names as $keyword_name) {
                    $term_id = get_term_by('name', $keyword_name, RAPTIVE_KEYWORD_TAXONOMY_NAME);
                    if ($term_id) {
                        wp_delete_term( $term_id->term_id, RAPTIVE_KEYWORD_TAXONOMY_NAME );
                    }
                }
                echo "<p class='success' id='rapaf-input-form-feedback'>Keyword(s) deleted successfully</p>";
                self::print_fade_out_script();
            }
        }
    }
    public static function handle_insert() {
        if(empty($_POST["keyword"]) || empty($_POST["product_link"])) {
            echo "<p class='warning' id='rapaf-input-form-feedback'>Please enter both keyword and product link</p>";
        }
        else {
            RAPAF_Commons::register_taxonomy();
            $keyword = trim($_POST["keyword"]);
            $product_link = trim($_POST["product_link"]);
            $term_id = get_term_by('name', $keyword, RAPTIVE_KEYWORD_TAXONOMY_NAME);
            if ($term_id) {
                echo "<p class='warning' id='rapaf-input-form-feedback'>Keyword already exists</p>";
            } else {
                
                $new_term = wp_insert_term($keyword, RAPTIVE_KEYWORD_TAXONOMY_NAME);
                if ( $new_term && ! is_wp_error( $new_term ) ) {
                    $term_id = $new_term['term_id'];
                    if ($term_id) {
                        $meta_key = 'rapaf_keyword_link';
                        $meta_value = $product_link;
                        add_term_meta( $term_id, $meta_key, $meta_value, true );
                        echo "<p class='success' id='rapaf-input-form-feedback'>Keyword created successfully</p>";

                    } else {
                        echo "<p class='warning' id='rapaf-input-form-feedback'>Failed to create keyword</p>";
                    }
                } else {
                    echo "<p class='warning' id='rapaf-input-form-feedback'>".print_r($new_term->errors)."</p>";
                }
            }
        }
        self::print_fade_out_script();
        self::insert_form_togglable();
    }
    
    public static function display_list($data) {
        $rows = $data;
        if (!empty($rows)) {
            ?>
            <br><br>
            <form name="keyword_linker_list_form" action="<?php echo admin_url('admin.php')?>?page=keyword_linker" id="keyword_linker_list_form_id" method="post" >
            <input type="hidden" name="raptive_selected_data" id="raptive_selected_data" value=""></input>
            <div class="table">
            <script type="text/javascript">
                
                jQuery(document).ready(function () {
                    
                    jQuery('#keyword_links').DataTable({
                        scrollY: '500px',
                        scrollCollapse: true,
                        paging: false,
                        order: [[1, 'asc']],
                        dom: 'Bfrtip',
                        columnDefs: [ {
                            className: 'select-checkbox',
                            targets:   0
                        } ],
                        select: {
                            style: 'multi'
                        },
                        buttons: [
                            'selectAll',
                            'selectNone',
                            { // custom delete button
                                text: 'Delete',
                                action: function ( e, dt, node, config ) {
                                    var selectedRows = dt.rows( { selected: true } ).data();
                                    var selectedIds = [];
                                    for (var i = 0; i < selectedRows.length; i++) {
                                        selectedIds.push(selectedRows[i][1]);
                                    }
                                    var data = {
                                        'action': 'delete',
                                        'keyword_names': selectedIds
                                    };
                                    console.log(data);
                                    jQuery('#raptive_selected_data').val(JSON.stringify(data));
                                    document.getElementById("keyword_linker_list_form_id").submit();
                                }
                            }
                        ],
                    });
                });
            </script>
            <br><br>
            
            <table id="keyword_links" class="display">
            <thead>
                <tr>
                <th></th>
                    <th>Keyword</th>
                    <th>Product Link</th>
                </tr>
            </thead>
            <tbody>
            <?php
            
            foreach($rows as $row) {
                $url = $row['link'];
                
                print('<tr>');
                print('<td></td>');
                print('<td>'.$row['name'].'</td>');
                
                print('<td>');
                print(esc_html($url));
                print('</td>');
                
                print('</tr>');
            }
            print('</table>');
            print('</div>');
            print('</form>');
        }
    }


}
RAPAF_KeywordLinker::init();