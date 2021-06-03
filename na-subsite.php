<?php
/*
Plugin Name: NA Subsites
Description: Make the Gebiet Pages fit with the Region.  This modifies the breadcrumbs, the logo, and the menu.
Version: 1.0
*/
class na_subsite {
    function __construct() {
        add_filter( 'avia_breadcrumbs_trail', array($this,'avia_breadcrumbs_trail_mod'), 50, 2 );
        add_filter( 'pre_wp_nav_menu', array($this,'replace_menu'), 10, 2 );
        add_filter( 'avf_logo_link', array($this,'av_change_logo_link') );
        add_action( 'ava_before_footer', array($this,'switch_to_main'), 10, 0 );
        add_action( 'wp_footer', 'restore_current_blog', 1, 0);
    }
    function switch_to_main() {
        if (get_current_blog_id()==1) return;
        switch_to_blog(1);
    }
    function avia_breadcrumbs_trail_mod( $trail, $args ) {
		if (count($trail)>1)
			$trail[0] = str_replace('Startseite',get_bloginfo('name'),$trail[0]);
		else
			$trail['trail_end'] = str_replace('Startseite',get_bloginfo('name'),$trail['trail_end']);
        return array_merge(array('<a href="/">Region</a>','<a href="/">Gebiete</a>'), $trail);
    }
    function replace_menu($output, $argsObj) {
        if (get_current_blog_id()==1) return $output;
		$args = (array)$argsObj;
		$echo = $args['echo'];
        $args['echo'] = false;
        switch_to_blog(1);
        $ret = wp_nav_menu($args);
        restore_current_blog();
		$args['echo'] = $echo;
        return $ret;
    }
    function av_change_logo_link($link) {
    	return "https://narcotics-anonymous.de";
	}
}
new na_subsite();
/****
 * 
    function __construct() {
        add_filter( 'avia_breadcrumbs_trail', array($this,'avia_breadcrumbs_trail_mod'), 50, 2 );
		//add_filter( 'avf_logo_link', array($this,'av_change_logo_link') );
    }
    function avia_breadcrumbs_trail_mod( $trail, $args ) {
		if (count($trail)>1)
			$trail[0] = str_replace('Startseite',get_bloginfo('name'),$trail[0]);
		else
			$trail['trail_end'] = str_replace('Startseite',get_bloginfo('name'),$trail['trail_end']);
        return array_merge(array('<a href="/">Region</a>','<a href="/gebiete-deutschsprachiger-raum/">Gebiete</a>'), $trail);
    }
	function av_change_logo_link($link) {
    	return "https://narcotics-anonymous.de";
	}
}
 * 
 */