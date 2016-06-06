<?php
/*
Plugin Name: If Menu
Plugin URI: http://wordpress.org/plugins/if-menu/
Description: Show/hide menu items with conditional statements
Version: 0.4.1
Author: Andrei Igna
Author URI: http://rokm.ro
License: GPL2
*/

/*  Copyright 2012 Andrei Igna (email: andrei@rokm.ro)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


class If_Menu {

	protected static $has_custom_walker = null;

	public static function init() {
		self::$has_custom_walker = 'Walker_Nav_Menu_Edit' !== apply_filters( 'wp_edit_nav_menu_walker', 'Walker_Nav_Menu_Edit' );

		if( is_admin() ) {
			add_action( 'admin_init', 'If_Menu::admin_init' );
			add_action( 'wp_update_nav_menu_item', 'If_Menu::wp_update_nav_menu_item', 10, 2 );
			add_filter( 'wp_edit_nav_menu_walker', create_function( '', 'return "If_Menu_Walker_Nav_Menu_Edit";' ) );
      add_action( 'wp_nav_menu_item_custom_fields', 'If_Menu::menu_item_fields' );
      add_action( 'wp_nav_menu_item_custom_title', 'If_Menu::menu_item_title' );

      if ( self::$has_custom_walker && 1 != get_option( 'if-menu-hide-notice', 0 ) ) {
        add_action( 'admin_notices', 'If_Menu::admin_notice' );
        add_action( 'wp_ajax_if_menu_hide_notice', 'If_Menu::hide_admin_notice' );
      }
		} else {
			add_filter( 'wp_get_nav_menu_items', 'If_Menu::wp_get_nav_menu_items' );
		}

	}

	public static function admin_notice() {
		global $pagenow;

		if( current_user_can( 'edit_theme_options' ) ) {
      ?>
      <div class="notice error is-dismissible if-menu-notice">
        <p><b>If Menu</b> plugin detected a conflict with another plugin or theme and may not work as expected. <a href="https://wordpress.org/plugins/if-menu/faq/" target="_blank">Read more about the issue here</a></p>
      </div>
      <?php
		}

	}

  public static function hide_admin_notice() {
    $re = update_option( 'if-menu-hide-notice', 1 );

    echo $re ? 1 : 0;

    wp_die();
  }

	public static function get_conditions( $for_testing = false ) {
		$conditions = apply_filters( 'if_menu_conditions', array() );

		if( $for_testing ) {
			$c2 = array();
			foreach ( $conditions as $condition ) {
        $c2[$condition['name']] = $condition;
      }
			$conditions = $c2;
		}

		return $conditions;
	}

	public static function wp_get_nav_menu_items( $items ) {
		$conditions = If_Menu::get_conditions( $for_testing = true );
		$hidden_items = array();

		foreach ( $items as $key => $item ) {
			if ( in_array( $item->menu_item_parent, $hidden_items ) ) {
				unset( $items[$key] );
				$hidden_items[] = $item->ID;
			} elseif ( get_post_meta( $item->ID, 'if_menu_enable', true ) ) {
				$condition_type = get_post_meta( $item->ID, 'if_menu_condition_type', true );
				$condition = get_post_meta( $item->ID, 'if_menu_condition', true );

				$should_hide_item = call_user_func( $conditions[$condition]['condition'], $item );
				if ( $condition_type == 'show' ) {
          $should_hide_item = ! $should_hide_item;
        }

				if ( $should_hide_item ) {
					unset( $items[$key] );
					$hidden_items[] = $item->ID;
				}
			}
		}

		return $items;
	}

	public static function admin_init() {
		global $pagenow;

    if ( $pagenow == 'nav-menus.php' || self::$has_custom_walker ) {
      wp_enqueue_script( 'if-menu-js', plugins_url( 'if-menu.js', __FILE__ ), array( 'jquery' ) );
    }

		if ( $pagenow == 'nav-menus.php' || defined( 'DOING_AJAX' ) ) {
      require_once( ABSPATH . 'wp-admin/includes/nav-menu.php' );
      require_once( plugin_dir_path( __FILE__ ) . 'if-menu-nav-menu.php' );
		}
	}

  public static function menu_item_fields( $item_id ) {
    $conditions = If_Menu::get_conditions();
    $if_menu_enable = get_post_meta( $item_id, 'if_menu_enable', true );
    $if_menu_condition_type = get_post_meta( $item_id, 'if_menu_condition_type', true );
    $if_menu_condition = get_post_meta( $item_id, 'if_menu_condition', true );
    ?>

    <p class="if-menu-enable description description-wide">
      <label>
        <input <?php checked( $if_menu_enable, 1 ) ?> type="checkbox" value="1" class="menu-item-if-menu-enable" name="menu-item-if-menu-enable[<?php echo $item_id; ?>]" />
        <?php _e( 'Enable Conditional Logic', 'if-menu' ) ?>
      </label>
    </p>

    <p class="if-menu-condition description description-wide" style="display: <?php echo $if_menu_enable ? 'block' : 'none' ?>">
      <select id="edit-menu-item-if-menu-condition-type-<?php echo $item_id; ?>" name="menu-item-if-menu-condition-type[<?php echo $item_id; ?>]">
        <option <?php selected( 'show', $if_menu_condition_type ) ?> value="show"><?php _e( 'Show', 'if-menu' ) ?></option>
        <option <?php selected( 'hide', $if_menu_condition_type ) ?> value="hide"><?php _e( 'Hide', 'if-menu' ) ?></option>
      </select>
      <?php _e('if', 'if-menu'); ?>
      <select id="edit-menu-item-if-menu-condition-<?php echo $item_id; ?>" name="menu-item-if-menu-condition[<?php echo $item_id; ?>]">
        <?php foreach( $conditions as $condition ): ?>
          <option <?php selected( $condition['name'], $if_menu_condition ) ?>><?php echo $condition['name']; ?></option>
        <?php endforeach ?>
      </select>
    </p>

    <?php
  }

  public static function menu_item_title( $item_id ) {
    $if_menu_enabled = get_post_meta( $item_id, 'if_menu_enable', true );
    $conditionType = get_post_meta( $item_id, 'if_menu_condition_type', true );
    $condition = get_post_meta( $item_id, 'if_menu_condition', true );
    if ( $conditionType === 'show' ) {
      $conditionType = '';
    }

    if ( $if_menu_enabled ) {
      ?>
      <span class="is-submenu"><?php _e( sprintf('%s if %s', $conditionType, $condition), 'if-menu' ) ?></span>
      <?php
    }
  }

	public static function wp_update_nav_menu_item( $menu_id, $menu_item_db_id ) {
		$if_menu_enable = isset( $_POST['menu-item-if-menu-enable'][$menu_item_db_id] ) && $_POST['menu-item-if-menu-enable'][$menu_item_db_id] == 1;
		update_post_meta( $menu_item_db_id, 'if_menu_enable', $if_menu_enable ? 1 : 0 );

		if( $if_menu_enable ) {
			update_post_meta( $menu_item_db_id, 'if_menu_condition_type', $_POST['menu-item-if-menu-condition-type'][$menu_item_db_id] );
			update_post_meta( $menu_item_db_id, 'if_menu_condition', $_POST['menu-item-if-menu-condition'][$menu_item_db_id] );
		}
	}

}



/* ------------------------------------------------
	Include default conditions for menu items
------------------------------------------------ */

include 'conditions.php';



/* ------------------------------------------------
	Run the plugin
------------------------------------------------ */

add_action( 'init', 'If_Menu::init' );
