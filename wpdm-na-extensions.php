<?php
/**
Plugin Name: wpdm-na-customizations
Plugin URI: none
Description: NA customizations to Download Manager.
Author: Ron B
Version: 0.1.0
*/
class wpdm_na_extenstions {
    function __construct(){
        /** Shortcode for 2 level hierarchies */
        add_shortcode('wpdm_na_section', array($this, 'do_wpdm_na_section'));
        /** Shortcode for 3 level hierarchies */
        add_shortcode('wpdm_na_page', array($this, 'do_wpdm_na_page'));
        /** Shortcodes for Mediatek, can also be used to add a 4th level hierarchy */
        add_shortcode('wpdm_na_category_list', array($this, 'do_wpdm_na_category_list'));
        add_shortcode('wpdm_na_insert_term', array($this, 'do_wpdm_na_insert_term'));
        add_shortcode('wpdm_na_force_login', array($this, 'do_wpdm_na_force_login'));
        /** Add text to modal login form */
        add_action( "wpdm_before_login_form", array($this, 'wpdm_login_form_instructions'));
        /** Add tab to properties page */
        add_filter('add_wpdm_settings_tab', array($this, 'Tab'));
        /** Remove unnecessary fields from the backend upload page */
        add_action('init', array($this, 'init_remove_editor_from_wpdm'),100);
        add_action('wpdm_meta_box', array($this, 'filter_meta_boxes_from_wpdm'), 100);
        /** Instead of paging, we add a link to the category page */
        add_action( 'wpdmcategory_add_form_fields', array($this,'CategoryFields'), 11, 0 );
        add_action( 'wpdmcategory_edit_form_fields', array($this,'CategoryFieldsEdit'), 11, 1 );
        /** To access a file that is not stored in WPDM (eg, MeetingList PDFs that are generated)
         *  don't upload a file, and put a link in the extract.
         */
        add_filter('wpdm_after_prepare_package_data', array($this,'wpdm_download_link'),10,1);
        /**
         * Show only post that the use is allowed to edit.
         */
        add_filter('pre_get_posts', array($this,'posts_for_current_author'), 10,1);
        /**
         * Show only catagories that are in the tree whose root is the author's name.
         *
         * Don't allow authors to create top level categories!
         */
        add_action('submitpost_box', array($this,'filter_terms'), 10, 1);
        add_action('wpdmcategory_pre_add_form', array($this,'filter_terms'), 10, 1);
        add_action('edit_form_advanced', array($this,'unfilter_terms'), 10, 1);
        add_action('after-wpdmcategory-table', array($this,'unfilter_terms'), 10, 1);

        add_filter('get_terms', array($this,'author_terms'), 10, 2);
        /** 
         * Users that are not yet registered at this site, but are known on the network
         * automatically get the default user at this site.
         */
        add_action('wp_login', array($this,'handle_login'), 10, 2);

        add_action("admin_menu", array(&$this, "admin_menu_link"));
        add_action("admin_enqueue_scripts", array(&$this, "enqueue_backend_files"));
        add_action( 'show_user_profile', array(&$this, 'extra_user_profile_fields' ));
        add_action( 'edit_user_profile', array(&$this, 'extra_user_profile_fields' ));
        add_action( 'edit_user_profile_update', array($this,'save_extra_user_profile_fields' ));
    }
    private function get_user_category() {
        if( current_user_can( 'edit_others_posts' ) ) {
            return null;
        }
        $user = wp_get_current_user();
        $cat_name = $user->user_login;
        return get_term_by('slug', $cat_name, 'wpdmcategory');
    }
    function author_categories($args, $post_id) {
        $term = $this->get_user_category();
        if (!$term) return $args;
        $args['descendants_and_self'] = $term->term_id;
        return $args;
    }
    var $filter=false;
    function filter_terms($post) {
        $this->filter = true;
    }
    function unfilter_terms($post) {
        $this->filter = false;

    }
    function author_terms($terms, $taxonomies) {
        if (!$this->filter) return $terms;
        if (!is_array($taxonomies) || count($taxonomies)==0 || $taxonomies[0] != 'wpdmcategory') {
            return $terms;
        }
        $term = $this->get_user_category();
        if (!$term) return $terms;
		if (!is_admin()) return $terms;
        foreach ($terms as $key=>$data) {
            $id = $data;
            if ($data instanceof WP_Term) $id = $data->term_id;
            if ($id == $term->term_id) continue;
            $ans = get_ancestors($id, 'wpdmcategory', 'taxonomy');
            if (is_array($ans) && in_array($term->term_id,$ans)) continue;
            unset($terms[$key]);
        }
        return $terms;
    }
    function author_term_parent($args) {
        if ($args['taxonomy'] != 'wpdmcategory') return $args;
        $term = $this->get_user_category();
        if (!$term) return $args;
        $args['show_option_none'] = '';
        $args['option_none_value'] = $this->get_user_category()->term_id;
        
        return $args;
    }
    function posts_for_current_author($query) {
        global $pagenow;
     
        if( 'edit.php' != $pagenow || !$query->is_admin )
            return $query;
     
        if( !current_user_can( 'edit_others_posts' ) ) {
            global $user_ID;
            $query->set('author', $user_ID );
        }
        return $query;
    }
    /** 
     *  To access a file that is not stored in WPDM (eg, MeetingList PDFs that are generated)
     *  don't upload a file, and put a link in the extract.
     *  This filter is called when gettting the download ready, and sets the download link.
     */
    function wpdm_download_link($package){ 
        if (is_array($package['files']) && count($package['files'])==1 &&
            trim($package['files'][0])=='') {
                $link = $package['post_excerpt'];
                $package['download_link'] = '<a class="wpdm-download-link  btn btn-primary " rel="nofollow" href="'.$link.'">Download</a>';
        }
        return $package;
    }
    /**
     * Get the text that was given in our tab on the properties page, and add it to
     * the modal login form */
    function wpdm_login_form_instructions() {
        $text = get_option('wpdm_na_login_before_text');
        if ($text)
            echo $text;
    }
    /** Clean up the backend UI */
    function init_remove_editor_from_wpdm(){
        remove_post_type_support( 'wpdmpro', 'editor');
    }
    /** Clean up the backend UI */
    function filter_meta_boxes_from_wpdm($metaboxes) {
        $post_type = 'wpdmpro';
        remove_meta_box( 'authordiv',$post_type,'normal' ); // Author Metabox
        remove_meta_box( 'commentstatusdiv',$post_type,'normal' ); // Comments Status Metabox
        remove_meta_box( 'commentsdiv',$post_type,'normal' ); // Comments Metabox
        remove_meta_box( 'postcustom',$post_type,'normal' ); // Custom Fields Metabox
        remove_meta_box( 'slugdiv',$post_type,'normal' ); // Slug Metabox
        remove_meta_box( 'trackbacksdiv',$post_type,'normal' ); // Trackback Metabox
        remove_meta_box( 'pagetemplatediv',$post_type,'normal' ); // PageTemplate Metabox
        return $metaboxes;
    }
    /**
     * Creates a string with the wpdm_all_packages shortcode, and calls do_shortcode.
     */
    function do_wpdm_table( $attributes, $content = null ) {
        $content = '[wpdm_all_packages jstable=0  cols="title,download_count|download_link" colheads="|::20px"';
        foreach($attributes as $att=>$value) {
            $content .= ' '.$att.'="'.$value.'"';
        }
        $content .= ']';
        return do_shortcode($content);
    }
    function display_title($title) {
        $content = '<div class="wpdm-na-data">';
        $content .= '<h3 class="av-special-heading-tag " itemprop="headline">';
        $content .= $title; 
        $content .= '</h3>';
        return $content;
    }
    function get_box_ready(&$col_knt, $first, $ncols, $row_class, $row_style, $col_class, $col_style ) {
        $col_knt = $col_knt +1;
        $content = '';
        if ($col_knt > $ncols) $col_knt = $col_knt - $ncols;
        if ($col_knt == 1) {
            if (!$first) {
                $content .= '</div>';
            }
            $first = false;
            $content .= "<div class=\"$row_class\" style=\"$row_style\" >";
        } else {
            $content .= '<div class="av-flex-placeholder"></div>';
        }
        $content .= "<div class=\"$col_class\" style=\"$col_style\" >";

        return $content;
    }
    /** Shortcode for 3 level hierarchies */
    function do_wpdm_na_page($attributes, $inner) {
        $parent_slug = $attributes['category'];
        unset($attributes['category']);
        $terms = get_terms( 'wpdmcategory', ['slug'=>$parent_slug] );
        if (!$terms or sizeof($terms)==0) return '';
    
        $parent = $terms[0];
        $args = array(  'parent'        => $parent->term_id,
                        'hide_empty'    => false);
        $terms = get_terms(['wpdmcategory'], $args);
        $user = wp_get_current_user();
        $sc = '[wpdm_na_section category="'.$parent->slug.'" ';
        foreach($attributes as $att=>$value) {
            $sc .= ' '.$att.'="'.$value.'"';
        }
        $sc .= ']';
        $content = do_shortcode($sc);
        foreach ($terms as $term) {
            if (!$this->is_allowed($term,$user)) continue;
            $args = array(  'parent'        => $term->term_id,
                            'hide_empty'    => false);
            $children = get_terms(['wpdmcategory'], $args);
            if (!is_array($children)||count($children)==0) continue;
            $content .= '<div class="CategoryOfCategoriesHead"><h3>';
            $content .= $term->name.'</h3></div>';
            $sc = '[wpdm_na_section category="'.$term->slug.'" ';
            foreach($attributes as $att=>$value) {
                $sc .= ' '.$att.'="'.$value.'"';
            }
            $sc .= ']';
            $content .= '<div class="CategoryOfCategoriesBody">';
            $content .= do_shortcode($sc).'</div>';
        }
        return $content;
    }
    function is_allowed($term,$user) {
        $roles = maybe_unserialize(get_term_meta($term->term_id, '__wpdm_access', true));
        if ( is_array( $roles ) && count($roles)>0 && (count($roles)>1 || $roles[0]!='__wpdm__')) {
            foreach($roles as $role) {
                if ($role == 'guest' ||
                    (in_array($role, (array)$user->roles))) {
                    return true;
                }
            }
            return false;
        }
        return true;
    }
    function do_wpdm_na_force_login( $attributes, $inner ) {
        if (!is_user_logged_in()) {
            return do_shortcode('[wpdm_login_form]');
        }
        return do_shortcode($inner);
    }
    /**
     * This shortcode was added to support the mediatek.  It's a two level hiearchy, but 
     * instead of boxes with contents, it's just a bunch of links, pointing to a page that
     * can deal with the "term_slug" query string, eg, a page that has the wpdm_na_insert_term
     * shortcode.
     */
    function do_wpdm_na_category_list( $attributes ) {

        if (!is_user_logged_in()) {
            return do_shortcode('[wpdm_login_form]');
        }
        $parent_slug = $attributes['category'];
        unset($attributes['category']);
        $category_page = $attributes['category_page'];
        $terms = get_terms( 'wpdmcategory', ['slug'=>$parent_slug] );
        if (!$terms or sizeof($terms)==0) return 'No Terms';
    
        $parent = $terms[0];
        $content = '';
        $args = array(  'parent'        => $parent->term_id,
                        'hide_empty'    => false);
        $terms = get_terms(['wpdmcategory'], $args);
        $user = wp_get_current_user();
        $args = array(
            'name'        => $category_page,
            'post_type'   => 'any',
            'post_status' => 'publish',
            'numberposts' => 1
        );
        $target_posts = get_posts($args);
        if (count($target_posts)==1) {
            $category_page = get_permalink($target_posts[0]);
        }
        $content .= '<ul class="wpdm_na_category_list">';
        foreach ($terms as $term) {
            if (!$this->is_allowed($term,$user)) continue;
            if ($term->count == 0) continue;
            $content .= '<li class="wpdm_na_category_list_item">';
            $text = $term->name;
            if (!empty($term->description)) $text = $term->description;
            $link = $category_page.'?wpdmc='.$term->slug;
            $content .= '<a href="'.$link.'">'.$text.'</a>';
            $content .= '</li>';
        }
        $content .= '</ul>';
        return $content;
    }
    function do_wpdm_na_insert_term( $attributes, $inner ) {
        if (empty($_GET['wpdmc'])) return;
        $slug = $_GET['wpdmc'];
        $term = get_term_by('slug', $slug, 'wpdmcategory');
        if (!$term) return '';
        $content = '<h2>'.$term->name.'</h2>'.$term->description.'<br/>';
        $inner= str_replace('%slug%',$slug,$inner);
        return $content.do_shortcode($inner);
    }
    /** Shortcode for 3 level hierarchies */
    function do_wpdm_na_section( $attributes, $inner ) {
        $parent_slug = $attributes['category'];
        unset($attributes['category']);
        $ncols = 2;
        $width = 'av_one_half';
        if (isset($attributes['ncols'])) {
            $temp = $attributes['ncols'];
            unset($attributes['ncols']);
            if ($temp == 3) {
                $ncols = 3;
                $width = 'av_one_third';
            }
        }   
        $row_class = "flex_column_table av-equal-height-column-flextable -flextable";
        $row_style = "margin-top:0px; margin-bottom:5px;";
        $col_class = "flex_column $width  av-animated-generic left-to-right  flex_column_table_cell av-equal-height-column av-align-top first  avia-builder-el-8  el_before_av_one_half  avia-builder-el-first   avia_start_animation avia_start_delayed_animation";
        $col_style = "background: #ffffff; border-style:solid; padding:10px; background-color:#ffffff; border-radius:0px;";
        $style = "border-width: 5px;border-color: #294272;";
        if (isset($attributes['style'])) {
            $style = $attributes['style'];
            unset($attributes['style']);
        }
        $col_style .= $style;
        $items_per_page = 20;
        if (isset($attributes['items_per_page'])) {
            $items_per_page = $attributes['items_per_page'];
        }
        $terms = get_terms( 'wpdmcategory', ['slug'=>$parent_slug] );
        if (!$terms or sizeof($terms)==0) return '';
    
        $parent = $terms[0];
        $args = array(  'parent'        => $parent->term_id,
                        'hide_empty'    => false);
        $terms = get_terms(['wpdmcategory'], $args);
        $content = '<style>.wpdm-na-data .pagination {display:none;}
                       .wpdm-na-data thead {display:none;}
                       </style>';
        $ncols = 2;
        $col_knt = 0;
        $first = true;
        $user = wp_get_current_user();
        foreach ($terms as $term) {
            if (!$this->is_allowed($term,$user)) continue;
            if ($term->count == 0) continue;
            $content .= $this->get_box_ready($col_knt, $first, $ncols, $row_class, $row_style, $col_class, $col_style );
            $first = false;
            $content .= $this->display_title($term->name);
            $box_attributes = $attributes;
            $box_attributes['categories'] = $term->slug;
            if (!isset($box_attributes['order_by'])) {
                $box_attributes['order_by'] = 'publish_date';
                $test = get_term_meta($term->term_id, '__wpdm_na_order_by', true);
                $arr = explode(',',$test);
                if (count($arr)==2) {
                    $box_attributes['order_by'] = trim($arr[0]);
                    $box_attributes['order'] = trim($arr[1]);
                }
            }
            if (!isset($box_attributes['order'])) {
                $box_attributes['order'] = 'DESC';
            }
            $content .= $this->do_wpdm_table($box_attributes);
            if ($term->count > $items_per_page) {
                $content .= '<div style="text-align:center"><a href="'.get_category_link($term).'">Mehr...</a></div>';
            }
            $content .= '</div></div>';
        }
        if (!$first) {
            $content .= '</div>';
        }
        return $content;
    }
    function Tab($tabs) {
        $tabs['wpdm_na_login_form'] = \WPDM\admin\menus\Settings::createMenu('wpdm_na_login_form', 'NA WPDM Ext.', array($this, 'LoginFormPage'), 'fa fa-magic');
        return $tabs;
    }
    function LoginFormPage(){
        if (isset($_POST['section']) && $_POST['section'] == 'wpdm_na_login_form' && is_admin()
            && isset($_POST['wpdm_na_login_before_text'])) {
            if (!wp_verify_nonce($_POST['__wpdms_nonce'], NONCE_KEY)) 
                die(__('Security token is expired! Refresh the page and try again.', 'download-manager'));
            update_option('wpdm_na_login_before_text', wp_kses_post($_POST['wpdm_na_login_before_text']));  
            update_option("__wpdm_cpage",maybe_serialize($_POST['__wpdm_cpage']));          
            die('Settings Saved Successfully');
        }
        include("panel.php");
    }
    function CategoryFields($attr='') {
?>
    <div class="form-field w3eden">
    <div class="panel panel-default card-plain panel-plain">
    <div class="panel-heading">NA Extenstions</div>
     <div class="panel-body">
    <div class="panel panel-default">
        <div class="panel-heading"><?php echo 'Order By Parameters'; ?></div>
        <div class="panel-body">
            <div class="form-group">
                <label for="wpdm_na_order_by">Order By: </label>
                <select id="wpdm_na_order_by"  name="__wpdmcategory[na_order_by]" >
                    <option value="publish_date,DESC" <?php echo ($attr=='publish_date,DESC')?'selected':'';?>>publish_date,DESC</option>
                    <option value="publish_date,ASC" <?php echo ($attr=='publish_date,ASC')?'selected':'';?>>publish_date,ASC</option>
                    <option value="title,ASC" <?php echo ($attr=='title,ASC')?'selected':'';?>>title,ASC</option>
                    <option value="title,DESC" <?php echo ($attr=='title,DESC')?'selected':'';?>>title,DESC</option>
                </select><br/>
            </div>
        </div>
    </div></div></div></div>
<?php
    }
    function CategoryFieldsEdit() {
        $order_by = get_term_meta(wpdm_query_var('tag_ID', 'int'), '__wpdm_na_order_by', true);
        echo '<tr><td>';
        $this->CategoryFields($order_by);
        echo '</td></tr>';
    }
    function handle_login( $user_login, $user ) {
        /**
         * This adds users from other blogs to this blog, with Subscriber rights.
         */
        if ( !is_user_member_of_blog() ) {
            $blog_id = get_current_blog_id();
            $user->for_site($blog_id);
            $roles = $user->roles;
            if (!is_array($roles) && count($roles)>0) {
                $user_id = $user->get('ID');
                add_user_to_blog( $blog_id, $user_id, get_blog_option( $blog_id, 'default_role', 'subscriber' ) );
            }
        }
        /**
         * This adds (or removes) the roles necessary for downloads based on the committee member list.
         */
        $roles_adjust = array(
            "NA-Mitglied" => true,
        );
        $authors = get_users(array(
            'role'    => 'author',
        ));
        foreach($authors as $author) {
            $komitee = $author->get('komitee-role');
            if (empty($komitee)) continue;
            $list = $author->get('komitee-mitglieder');
            $roles_adjust[$komitee] = in_array($user->login, $list);
        }
        $roles = $user->roles;
        foreach ($roles_adjust as $role => $adjust) {
            if (in_array($role,$roles)) {
                if (!$adjust) {
                    $user->remove_role($role);
                }
            } else {
                if ($adjust)  {
                    $user->add_role($role);
                }
            }
        }
    }
    /**
     * This set of functions is what I need to add a new capability
     */
    public static function activate() {
        add_action( 'admin_init','wpdm_na_extenstions::add_cap');
    }
    public static function add_cap() {
        $role = $GLOBALS['wp_roles']->role_objects['administrator'];
        if (isset($role) && !$role->has_cap('manage_komitee')) {
            $role->add_cap('manage_komitee');
        }
    }
    public static function deactivate() {
        add_action( 'admin_init','wpdm_na_extenstions::remove_cap');
    }
    public static function remove_cap() {
        $role = $GLOBALS['wp_roles']->role_objects['administrator'];
        if (isset($role) && $role->has_cap('manage_komitee')) {
            $role->remove_cap('manage_komitee');
        }
    }
    /**
     * The script for managing the list, adding and remobing rows, is JQuery,
     * so I need to add that...
     */
    function enqueue_backend_files($hook) {
        if( $hook == 'toplevel_page_wpdm-na-extensions' ) {
            wp_enqueue_script('common');
            wp_enqueue_script('jquery');
            wp_enqueue_style("admin", plugin_dir_url(__FILE__) . "css/komitee.css", false, "1.2", 'all');
        }
    }
    /**
	 * @desc Adds the committee-member sub-panel
	 */
	function admin_menu_link() 	{
        global $my_admin_page;
        $komitee_role = wp_get_current_user()->get('komitee-role');
        if (!empty($komitee_role) || current_user_can('manage_options')) {
		    wpdm_na_extenstions::add_cap();
            $my_admin_page = add_menu_page( 'Komitee Mitgliedern', 'Komitee Mitgliedern', 'manage_komitee', basename(__FILE__), array(&$this, 'komitee_page'), 'dashicons-admin-page');
        }
	}
	function komitee_page() {
        $komitee_mitglieder = array();
        $user = get_current_user_id();
        if (current_user_can('manage_options')) {
            if (!empty($_REQUEST['user'])) {
                $user = $_REQUEST['user'];
            } else {
                $args = array(
                    'role'    => 'author',
                    'orderby' => 'user_nicename',
                    'order'   => 'ASC'
                );
                $authors  = get_users( $args );
                foreach ($authors as $author) {
                    if (!$author->has_cap('manage_komitee')) continue;?>
                    <p><a href="<?php echo $_SERVER['REQUEST_URI'].'&user='.$author->id;?>">
                    <?php echo $author->get('user_login');?></a><br>
                <?php }
                return;
            }
        }
        if ($_POST['komitee_action']) {
            if (!wp_verify_nonce($_POST['pwsix_mitglieder_settings'], 'pwsix_mitglieder_settings'))
                die('Whoops! There was a problem with the data you posted. Please go back and try again.');
            if (!current_user_can('manage_komitee')) {
                return;
            }
            if (empty($_POST['komitee-mitglieder']))
                return;
            $komitee_mitglieder = $_POST['komitee-mitglieder'];
            if (get_user_meta( $user, 'komitee-mitglieder')) {
                update_user_meta($user, 'komitee-mitglieder', $komitee_mitglieder);
            } else {
                add_user_meta($user, 'komitee-mitglieder', $komitee_mitglieder);
            }

            do_action( 'wpdm_na_mitglieder', $komitee_mitglieder, $user );
        } else {
            $komitee_mitglieder = get_user_meta( $user, 'komitee-mitglieder');
            if (is_array($komitee_mitglieder) && is_array($komitee_mitglieder[0]))
                $komitee_mitglieder = $komitee_mitglieder[0];
            if (count($komitee_mitglieder)==0) {
                $komitee_mitglieder[] = '';
            }
        }
?>	
<div id="mitglieder">	
<form method="POST" id="mitglieder_settings" name="mitglieder_settings">
	<?php wp_nonce_field( 'pwsix_mitglieder_settings', 'pwsix_mitglieder_settings' ); ?>
    <input type="hidden" name="komitee_action" value="true" />
    <label><h2>Komitee Mitglieder</h2></label>
    <div class="mitglieder-fieldset">
        <div class="mitglieder-wrapper">
        <?php foreach($komitee_mitglieder as $mitglied) {?>
            <div class="mitglieder">
                <ul>
                    <li>
                        <input type="text" size="25" name="komitee-mitglieder[]" value="<?php echo $mitglied;?>">
                    </li>
                </ul>
                <button type="button" class="remove-line">Entfernen</button>
            </div>
            <?php } ?>
        </div>
        <button type="button" class="add-field">Mitglied Hinzufügen</button>
    </div>
    <p><input type="submit" value="Speichern"></p>
</form>
<script>jQuery(function($){
$('.mitglieder-fieldset').each(function () {
    var $wrapper = $('.mitglieder-wrapper', this)[0];
    $(".add-field", $(this)).click(function (e) {
        $('.mitglieder:first-child', $wrapper).clone(true).appendTo($wrapper).find('input').val('').focus();
    });
    $('.mitglieder .remove-line', $wrapper).click(function () {
        if ($('.mitglieder', $wrapper).length > 1) $(this).parent('.mitglieder').remove();
    });
});
});
</script>
</div>
<?php	
    }
    function extra_user_profile_fields($user) {
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }
        global $wp_roles;
        $roles = $wp_roles->role_objects;
        $komitee_role = get_user_meta($user->id,'komitee-role',true);
        $none = (empty($komitee_role)) ? ' selected' : '';
        $listId = get_user_meta( $user->id, 'komitee-mitglieder-list', true);
        $apikey = get_user_meta( $user->id, 'komitee-mitglieder-key', true);
?>
<div>
<h3>Komitee Diener</h3>
        <table class="form-table">
        		<tr>
        			<td style="width:200px;" scope="row">Komitee Mitglieder haben Role:</td>
        			<td>
<select id='select_komitee_role' name='komitee-role'>
    <option value='' <?php echo $none;?>></option>  
<?php foreach ($roles as $role) {
    $selected = ($komitee_role == $role->name) ? ' selected': '';
    $atts = 'value="'.$role->name.'"'.$selected; ?>
    <option <?php echo $atts;?>><?php echo $role->name;?></option>
<?php } ?>
</select>
</td>
</tr>
<tr><td style="width:200px;">SWM API Key</td><td><input name='swm_apikey' type='text' size=20 value='<?php echo $apikey?>'></tr>
<tr><td style="width:200px;">SWM List ID</td><td><input name='swm_listId' type='text' size=3 value='<?php echo $listId?>'></tr>
</table>
</div>
<?php
    }

    function save_extra_user_profile_fields( $user_id ) {
        if ( !current_user_can( 'manage_options' )) { 
            return; 
        }
        $user = get_user_by('ID',$user_id );
        if (!$user->has_cap('manage_komitee')) {
            return;
        }
        if (empty($_POST['komitee-role'])) {
            delete_user_meta($user_id, 'komitee-role');
        } else {
            update_user_meta( $user_id, 'komitee-role', $_POST['komitee-role'] );
        }
        if (empty($_POST['swm_apikey'])) {
            delete_user_meta($user_id, 'komitee-mitglieder-key');
        } else {
            update_user_meta( $user_id, 'komitee-mitglieder-key', $_POST['swm_apikey'] );
        }
        if (empty($_POST['swm_listId'])) {
            delete_user_meta($user_id, 'komitee-mitglieder-list');
        } else {
            update_user_meta( $user_id, 'komitee-mitglieder-list', $_POST['swm_listId'] );
        }
    }
}
new wpdm_na_extenstions();
register_activation_hook( __FILE__, 'wpdm_na_extenstions::activate' );
register_deactivation_hook( __FILE__, 'wpdm_na_extenstions::deactivate' );
?>
