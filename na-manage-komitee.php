<?php
/**
Plugin Name: NA Manage Komitee
Plugin URI: none
Description: Allows the committees web servant to manage a member list.  This can be used to control access to documents
(by associating a role with the committee members).  The list is transmitted to SWM and can be used as a mailing list.
Author: Ron B
Version: 0.1.0
*/
class na_manage_komitee {
    function __construct(){
 
        add_action("admin_menu", array(&$this, "admin_menu_link"));
        add_action("admin_enqueue_scripts", array(&$this, "enqueue_backend_files"));
        add_action( 'show_user_profile', array(&$this, 'extra_user_profile_fields' ));
        add_action( 'edit_user_profile', array(&$this, 'extra_user_profile_fields' ));
        add_action( 'edit_user_profile_update', array($this,'save_extra_user_profile_fields' ));

        add_action('wp_login', array($this,'handle_login'), 10, 2);
    }

    /**
     * This set of functions is what I need to add a new capability
     */
    public static function activate() {
        add_action( 'admin_init','na_manage_komitee::add_cap');
    }
    public static function add_cap() {
        $role = $GLOBALS['wp_roles']->role_objects['administrator'];
        if (isset($role) && !$role->has_cap('manage_komitee')) {
            $role->add_cap('manage_komitee');
        }
    }
    public static function deactivate() {
        add_action( 'admin_init','na_manage_komitee::remove_cap');
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
        if( $hook == 'toplevel_page_na_manage_komitee' ) {
            wp_enqueue_script('common');
            wp_enqueue_script('jquery');
            wp_enqueue_style("admin", plugin_dir_url(__FILE__) . "css/komitee.css", false, "1.2", 'all');
        }
    }
    /**
	 * @desc Adds the committee-member sub-panel
	 */
	function admin_menu_link() 	{
        global $my_na_admin_page;
        $komitee_role = wp_get_current_user()->get('komitee-role');
        if (!empty($komitee_role) || current_user_can('manage_options')) {
            na_manage_komitee::add_cap();
            $my_na_admin_page = add_menu_page( 'Komitee Mitgliedern', 'Komitee Mitgliedern', 'manage_komitee', basename(__FILE__), array(&$this, 'komitee_page'), 'dashicons-admin-page');
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
            /**
             * The work of communication the new list to SWM is handed over to the NA-SWM plugin, since it has more to do with
             * SuperWebMailer than with managing committees, and anyway, the might be other use cases where we want to maintain
             * list members in WP.  
             */
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
    function handle_login( $user_login, $user ) {
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
}
new na_manage_komitee();
register_activation_hook( __FILE__, 'na_manage_komitee::activate' );
register_deactivation_hook( __FILE__, 'na_manage_komitee::deactivate' );
?>
