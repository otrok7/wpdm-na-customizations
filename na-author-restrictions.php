<?php
/**
Plugin Name: NA Author View
Plugin URI: none
Description: Restricts the documents and other posts as well as categories that are visible to a comittee web servant.
Author: Ron B
Version: 0.1.0
*/
class na_author_restrictions {
    function __construct(){

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
}
new na_author_restrictions();
?>
