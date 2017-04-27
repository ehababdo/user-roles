<?php
/*
Plugin Name: User Roles
Plugin URI: http://fb.me/userroles
Description: Change/add/delete WordPress user roles and capabilities.
Version: 1.0
Author: ehab abdo
Author URI: https://plus.google.com/+ehababdo
*/

/*
Copyright 2010-2017  Ehab abdo  (email: ehabstar3@gmail.com)
*/
/*
 * @package WordPress
 * @subpackage UserRolesedit
 * @class Ur_editroles
*/
class Ur_editroles 
{
	public static function init (){
		   	 // add cap to user permission
         	add_action( 'admin_init', array( __CLASS__, 'Ur_add_permission_caps'));
	          // add page user Roles
	         add_action('admin_menu', array( __CLASS__, 'Ur_UserRoles_menu'));
              // delete role
	         add_action( 'wp_ajax_Ur_delete_role', array( __CLASS__, 'Ur_delete_role'));
              // page user Roles js
	         add_action( 'admin_footer', array( __CLASS__, 'Ur_user_permission_js'));
	}

	public static function Ur_add_permission_caps() {
	    $role = get_role( 'administrator' );
	    $role->add_cap( 'edit_user_permission' ); 
	}

	public static function Ur_UserRoles_menu() {
		add_users_page( __('User Roles'), __('User Roles'), 'edit_user_permission', 'user_permission', array( __CLASS__, 'Ur_page_user_permission'));
	}
	public static function Ur_delete_role(){
			$role = sanitize_text_field($_REQUEST['delete']);
         if ( ! wp_verify_nonce( $_REQUEST['token'], $role ) ) {
               $result['type'] = "error";
               $result['role_count'] = __('Sorry, you are not allowed to delete this item.');         	
			}elseif (!current_user_can('edit_user_permission') ) {
				   $result['type'] = "error";
				   $result['role_count'] = __('Sorry, you are not allowed to delete this item.');
			}elseif ($role == 'administrator') {
				   $result['type'] = "error";
				   $result['role_count'] = __('Sorry, you are not allowed to delete this item.');
			}elseif(!get_role($role)){
				   $result['type'] = "error";
	            $result['role_count'] = sprintf( __( 'The role %s does not exist.'), $role );
          }elseif( get_role($role) ){
          	$args2 = array('role' => $role);
          	$authors = get_users($args2);
          	if($authors){
          		foreach ($authors as $user) {
          			wp_update_user( array('ID' => $user->ID,'role' => get_option('default_role')) );
          		}
          	}	
          	remove_role($role);
          	$result['type'] = "success";
          }
          if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
          	$result = json_encode($result);
          	echo $result;
          }
          else {
          	header("Location: ".$_SERVER["HTTP_REFERER"]);
          }
          die();
   }
   public static function Ur_user_permission_js() {
	   	global $current_screen;
	   	$current_scre = $current_screen->id ;
	   	if( 'users_page_user_permission' != $current_scre ) {
	   		return;
	   	}
	   	?>
	   	<script type="text/javascript">jQuery(document).ready(function(a){a(document).on("click",".delete_user_roless",function(){if(!confirm(commonL10n.warnDelete))return!1;var b=a(this),c=b.data("name"),d=b.data("token"),e=a("#"+c);return a.ajax({type:"post",url:"<?php echo admin_url( 'admin-ajax.php' );?>",dataType:"json",data:{action:"Ur_delete_role",token:d,delete:c},success:function(b){"success"==b.type?e.remove():a("#ajax-response").html('<div class="error notice"><p><strong>'+b.role_count+"</strong></p></div>")}}),!1}),a(".toggle-all-terms").on("change",function(){a("#rolechecklist").closest("ul").find(":checkbox").prop("checked",this.checked)})});</script>
	   	<?php 
	}
   public static function Ur_get_rolecaps( $role ) {
			$caps = array();
			$role_obj = get_role($role);
			if ( $role_obj && isset( $role_obj->capabilities ) )
				$caps = $role_obj->capabilities;
			return $caps;
	}
	public static function Ur_get_role( $name, $role, $default = false ) {
	    $options = Ur_editroles::Ur_get_rolecaps($role);
	    // Return specific option
	    if ( isset( $options[$name] ) ) {
	        return $options[$name];
	    }
	    return $default;
	}	
	public static function UR_add_role( $role, $name, $caps ) {
		global $wp_user_roles;
		$string = preg_replace('/\s+/', '', $role);
		$role_obj = get_role( $string );
		   if (!current_user_can('edit_user_permission') ) {
			    return false;
			}elseif ( ! $role_obj ) {
			$capabilities = array();
			foreach ( (array) $caps as $cap ) {
				$capabilities[ $cap ] = true;
			}
			$result= add_role( $string, $name, $capabilities );
			if ( null !== $result ) {
				return true;
			}
			else {
				return false;
			}				
			if ( ! isset( $wp_user_roles[ $string ] ) ) {
				$wp_user_roles[ $string ] = array(
					'name' => $name,
					'capabilities' => $capabilities,
					);
			}
			eg_refresh_current_user_caps( $string );
		} else {
			return false;
		}
	}
	public static function UR_merge_rolecaps( $role, $caps ) {
			global $wp_user_roles , $wp_roles;
			$role_obj = get_role( $role );
		   if (!current_user_can('edit_user_permission') || $role == 'administrator' ) {
			    return false;
			}elseif ( ! $role_obj )
				return false;
			 $capabilities = array();
				foreach ( (array) $caps as $cap ) {
					$capabilities[ $cap ] = true;
				}
			$current_caps = Ur_editroles::Ur_get_rolecaps('administrator');
			foreach ( $current_caps as $capremove => $value ) {
			  if(isset( $capabilities[$capremove]) ){
				 $role_obj->add_cap($capremove);
				}else{
            $role_obj->remove_cap($capremove);
				}
         }
			if ( isset( $wp_user_roles[ $role ] ) ) {
			$wp_user_roles[ $role ] = array(
						'capabilities' => $caps
						);
			}
			Ur_editroles::UR_refresh_usercaps( $role );
	}
	public static function UR_refresh_usercaps( $role ) {
			if ( is_user_logged_in() && current_user_can( $role ) ) {
				wp_get_current_user()->get_role_caps();
			}
	}
	public static function UR_roles_static(){
		global $wp_roles; $result = count_users();
		foreach($result['avail_roles'] as $role => $count){   	
			if ($role == 'none') {
				$roell = __('No role');
			} else {
				$roell = _x($wp_roles->roles[ $role ]['name'],'User role');
			}
			echo '<tr><td><span class="dashicons dashicons-admin-users"></span> '.$roell.'</td><td>'.$count.'</td></tr>';
		}
	}
   public static function Ur_list_cps (){
	   global $wp_roles;
	    if ( ! isset( $wp_roles ) )
		$wp_roles = new WP_Roles();
		$roles = $wp_roles->get_names();
		foreach ($roles as $role_value => $role_name) { ?>
	      <tr id="<?php echo $role_value; ?>" class="iedit <?php echo $role_value; ?> ">
	        <td class="title column-title" data-colname="Title">
	          <a href="users.php?page=user_permission&edit&user_role=<?php echo $role_value; ?>"><strong><?php echo _x($role_name,'User role'); ?></strong></a>
	        </td>
	        <td class="slug column-slug" data-colname="Slug">
	          <?php echo $role_value; ?>
	        </td>
	        <td class="column-role" data-colname="<?php echo $role_value; ?>">
	          <a href="users.php?page=user_permission&edit&user_role=<?php echo $role_value; ?>" title="<?php _e('Edit'); ?>"><span class="dashicons dashicons-admin-generic"></span></a> |
	          <a class="delete_user_roless" href="<?php echo admin_url('admin-ajax.php?action=Ur_delete_role&delete='.$role_value.'&token='.wp_create_nonce($role_value)); ?>" data-name="<?php echo $role_value; ?>" data-token="<?php echo wp_create_nonce($role_value); ?>" title="<?php _e('Delete'); ?>" ><span class="dashicons dashicons-trash"></span></a>
	        </td>
	      </tr>
<?php  
   } }


   public static function Ur_page_user_permission() { 
			if ( ! current_user_can('edit_user_permission') ) {
				wp_die(
					'<h1>' . __( 'Cheatin&#8217; uh?' ) . '</h1>' .
					'<p>' . __( 'Sorry, you are not allowed to manage terms in this taxonomy.' ) . '</p>',
					403
				);
			}	
			if(isset($_POST['addroless'])){
				$roless_id = sanitize_text_field( $_POST['roless_id']);
				$roless_name = sanitize_text_field( $_POST['roless_id']);
				$permission = isset( $_POST['permission'] ) ? (array) $_POST['permission'] : array();
				$permission = array_map( 'esc_attr', $permission );
				if ( ! wp_verify_nonce( $_POST['authenticity_token'], 'add_user_roless' ) ) {
					$eg_error = __('Sorry, you are not allowed to delete this item.');
				}elseif (!current_user_can('edit_user_permission') ) {
					$eg_error =  __('Sorry, you are not allowed to delete this item.');
				}elseif (empty($roless_name)) {
					$eg_error = __('Item not added.');
				}elseif (empty($roless_id)) {
					$eg_error = __('Invalid term ID.');
				}elseif (empty($permission)) {
					$eg_error = __('Item not added.');
				}else{
					$UR_add_roles = Ur_editroles::UR_add_role($roless_id, $roless_name, $permission );
					if($UR_add_roles === false) {
						$eg_error = __('Item not added.');
					}else {
						$eg_success = __('Item added.');
					}
				}
			}	
			if(isset($_POST['editrole'])){
				$idrole = sanitize_text_field( $_POST['idrole']);
				$permission = isset( $_POST['permission'] ) ? (array) $_POST['permission'] : array();
				$permission = array_map( 'esc_attr', $permission );
				if ( ! wp_verify_nonce( $_POST['authenticity_token'], 'editrole-'.$idrole ) ) {
		        $eg_error = __('Sorry, you are not allowed to delete this item.');
				}elseif (!current_user_can('edit_user_permission') ) {
				   $eg_error =  __('Sorry, you are not allowed to delete this item.');
				}elseif (empty($idrole)) {
					$eg_error = __('Item not updated.');
				}elseif (empty($permission)) {
					$eg_error = __('Item not updated.');
				}else{
		        $UR_merge_role = Ur_editroles::UR_merge_rolecaps($idrole, $permission );
				  if($UR_merge_role === false) {
				     $eg_error = __('Item not updated.');
				   }else {
				      $eg_success = __('Item updated.');
				   }
				}
			} 
			   ?>
		<div class="wrap nosubsub">
   	<h1 class="wp-heading-inline">
   		<?php if(isset($_GET['edit'])) {
   			   echo _ex('Edit', 'menu').' ' .__('Role');
   				}else{
   					echo __('User Roles');
   		} ?>
   	</h1>	
      <?php if (!empty($eg_error)) { ?>
   	<div id="message" class="error notice is-dismissible">
		<p><strong><?php echo $eg_error; ?></strong></p>
		<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php _e('Dismiss this notice.'); ?></span></button>
		</div>
		<?php } ?>
      <?php if (!empty($eg_success)) { ?>
   	<div id="message" class="updated notice is-dismissible">
		<p><strong><?php echo $eg_success; ?></strong></p>
		<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php _e('Dismiss this notice.'); ?></span></button>
		</div>
		<?php } ?>		
		<div id="ajax-response"></div>   	
   	<div class="tablenav top">
   		<div id="col-container" class="wp-clearfix">
   			<?php if(isset($_GET['edit'])) {
   				      // page edit role
   				   Ur_editroles::UR_user_page_edit();
   				}else{
   					  // page default roles
   					Ur_editroles::UR_user_page_default();
   				} ?>
   		</div> 
   	</div>
   </div>
<?php
}
public static function UR_user_page_default(){
?>	   
<div id="col-left">
  <div class="col-wrap">
    <div class="form-wrap">
      <h2><?php _ex('Add New', 'plugin'); ?></h2>
      <form method="post">
        <input type="hidden" name="authenticity_token" id="authenticity_token" value="<?php echo wp_create_nonce('add_user_roless'); ?>" />
        <div class="form-field form-required term-name-wrap">
          <label for="roless_name"><?php _e( 'Name'); ?></label>
          <input name="roless_name" id="roless_name" size="40" aria-required="true" type="text">
          <p class="description"><?php _e('The name is how it appears on your site.'); ?></p></td>
        </div>
        <div class="form-field form-required term-name-wrap">
          <label for="roless_id"><?php _e('Role'); ?> <small>(ID)</small></label>
          <input name="roless_id" id="roless_id" size="40" aria-required="true" type="text">
          <p><?php _e('Language') ?> <?php _e('English') ?></p>
        </div>
				<div id="Capabilities" class="taxonomydiv">
					<ul  class="taxonomy-tabs">
						<li class="tabs"><?php _e('Capabilities'); ?></li>
					</ul>
					<div class="tabs-panel">
						<ul id="rolechecklist" class="form-no-clear">
							<?php  $caps = Ur_editroles::Ur_get_rolecaps('administrator');
							foreach ( $caps as $key=>$value ): ?>
							<li>
								<label>
									<input  name="permission[]" value="<?php echo $key; ?>" type="checkbox"><?php echo $key; ?>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
				</div><!-- /.tabs-panel -->
				<p class="button-controls wp-clearfix">
				<span class="list-controls">
			    <label class="select-all"><input type="checkbox" class="toggle-all-terms"/><?php _e('Select All'); ?></label>
			    </span>
				</p>
			</div>
        <p class="submit">
        <input name="addroless" id="addroless" class="button button-primary" value="<?php _ex('Add New', 'plugin'); ?>" type="submit"></p>
        <p><?php _e( '<a href="https://codex.wordpress.org/Roles_and_Capabilities" target="_blank">Descriptions of Roles and Capabilities</a>' ); ?></p>
      </form>
    </div>
  </div>
</div>
<div id="col-right">
  <div class="col-wrap">
    <div class="tablenav top">
    </div>
      <table class="wp-list-table widefat fixed striped posts">
        <thead>
          <tr>
            <th scope="col" id="title" class="name column-name has-row-actions column-primary">
              <span><?php _e('Name'); ?></span><br></th>
            <th scope="col" id="slug" class="manage-column column-slug sortable desc">
              <span><?php _e('Role'); ?> <small>(ID)</small></span>
            </th>
            <th scope="col" id="Action" class="manage-column column-role"><?php _e('Actions'); ?></th>
          </tr>
        </thead>
        <tbody id="the-list">
         <?php Ur_editroles::Ur_list_cps(); ?>
        </tbody>
        <tfoot>
          <tr>
            <th scope="col" id="title" class="name column-name has-row-actions column-primary">
              <span><?php _e('Name'); ?></span><br></th>
            <th scope="col" id="slug" class="manage-column column-slug sortable desc">
              <span><?php _e('Role'); ?> <small>(ID)</small></span>
            </th>
            <th scope="col" id="Action" class="manage-column column-role"><?php _e('Actions'); ?></th>
          </tr>
        </tfoot>
      </table>
		<table class="wp-list-table widefat fixed striped">
		  <h2><span><?php _e('At a Glance'); ?></span></h2>
		  <thead>
		    <tr>
		      <th class="name column-name has-row-actions column-primary">
		        <?php _e('Name'); ?>
		      </th>
		      <th class="manage-column column-categories">
		        <?php _ex('Count','Number/count of items'); ?>
		      </th>
		    </tr>
		  </thead>
		  <tfoot>
		    <tr>
		      <th class="name column-name has-row-actions column-primary">
		        <?php _e('Name'); ?>
		      </th>
		      <th class="manage-column column-categories">
		        <?php _ex('Count','Number/count of items'); ?>
		      </th>
		    </tr>
		  </tfoot>
		  <tbody>
		    <tr>
		      <?php Ur_editroles::UR_roles_static();?>
		      <td></td>
		      <td></td>
		    </tr>
		  </tbody>
		  </table>
  </div>
</div>
<?php
}
public static function UR_user_page_edit(){
	global $wp_roles;
	$role = $_GET['user_role'];
	?>
<form name="editrole" id="editrole" method="post" class="validate">
   <input type="hidden" name="authenticity_token" id="authenticity_token" value="<?php echo wp_create_nonce('editrole-'.$role); ?>" />
   <input type="hidden" name="idrole" id="idrole" value="<?php echo $role; ?>" />
	<table class="form-table">
		<tbody><tr class="form-field form-required role-name-wrap">
			<th scope="row"><label for="name"><?php _e('Name'); ?></label></th>
			<td><input id="name" value="<?php echo _x($wp_roles->roles[ $role ]['name'],'User role'); ?>" size="40" aria-required="true" type="text"  disabled>
			<p class="description"><?php _e('The name is how it appears on your site.'); ?></p></td>
		</tr>
		<tr class="form-field role-Capabilities-wrap">
			<th scope="row"><label><?php _e('Capabilities'); ?></label></th>
			<td>
				<div id="Capabilities" class="taxonomydiv">
					<ul  class="taxonomy-tabs">
						<li class="tabs"><?php _e('Please select an option.'); ?></li>
					</ul>
					<div class="tabs-panel">
						<ul id="rolechecklist" class="form-no-clear">
							<?php  $caps = Ur_editroles::Ur_get_rolecaps('administrator');
							foreach ( $caps as $key=>$value ): ?>
							<li>
								<label>
									<input  name="permission[]" value="<?php echo $key; ?>" <?php if(Ur_editroles::Ur_get_role($key,$role) == 1){ echo'checked="checked"';} ?> type="checkbox"> <?php echo $key; ?>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
				</div><!-- /.tabs-panel -->
				<p class="button-controls wp-clearfix">
				<span class="list-controls">
			    <label class="select-all"><input type="checkbox" class="toggle-all-terms"/><?php _e('Select All'); ?></label>
			    </span>
				</p>
			</div>
			</td>
		</tr>
			</tbody></table>
   <p class="submit">
   <input name="editrole" id="editrole" class="button button-primary" value="<?php _e('Update'); ?>" type="submit"></p>
   </form>

<?php
}
}
// initialize Ur_editroles
Ur_editroles::init();