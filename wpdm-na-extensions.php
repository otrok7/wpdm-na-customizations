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
         */
        add_filter('wp_terms_checklist_args', array($this,'author_categories'), 10, 2);
        add_filter('get_terms', array($this,'author_terms'), 10, 2);
        add_filter('taxonomy_parent_dropdown_args',array($this,'category_dropdown'), 10, 1);
        /** 
         * Users that are not yet registered at this site, but are known on the network
         * automatically get the default user at this site.
         */
        add_action('wp_login', array($this,'check_user_on_site'), 10, 2);
    }
    function check_user_on_site( $user_login, $user ) {
        if ( is_user_member_of_blog() ) {
            return;
        }
        $blog_id = get_current_blog_id();
        $user->for_site($blog_id);
        $roles = $user->roles;
        if (is_array($roles) && count($roles)>0) {
            return;
        }
        $user_id = $user->get('ID');
        add_user_to_blog( $blog_id, $user_id, get_blog_option( $blog_id, 'default_role', 'subscriber' ) );
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
    function category_dropdown($args) {
        $term = $this->get_user_category();
        if (!$term) return $args;
        $args['child_of'] = $term->term_id;
        return $args;
    }
    function author_terms($terms, $taxonomies) {
        if (!is_array($taxonomies) || count($taxonomies)==0 || $taxonomies[0] != 'wpdmcategory') {
            return $terms;
        }
        $term = $this->get_user_category();
        if (!$term) return $terms;
        $ids = get_term_children( $term->term_id,'wpdmcategory' );
        $ret = array();
        $ret[] = $term;
        foreach ($ids as $id) {
            $ret[] = get_term_by('id', $id, 'wpdmcategory');
        }
        return $ret;
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
                $package['download_link'] = '<a class="wpdm-download-link  btn btn-primary " rel="nofollow" href="'.$link.'">Download</a>;';
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
            $allowed = false;
            foreach($roles as $role) {
                if (in_array($role, (array)$user->roles)) {
                    return true;
                }
            }
            return false;
        }
        return true;
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
                $test = get_term_meta($term->id, '__wpdm_na_orderby', true);
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
                <select id="wpdm_na_order_by"  name="__wpdmcategory[order_by]" >
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
        $order_by = get_term_meta(wpdm_query_var('tag_ID', 'int'), '__wpdm_na_orderby', true);
        echo '<tr><td>';
        $this->CategoryFields($order_by);
        echo '</td></tr>';
    }
}
new wpdm_na_extenstions();
?>
