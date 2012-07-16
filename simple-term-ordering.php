<?php
/**
 Plugin Name: Simple Term Ordering
 Plugin URI:
 Description: Order your terms using drag and drop on the built in term list. For further instructions, open the "Help" tab on the Pages screen. This is a fork of the popular <a href="http://wordpress.org/extend/plugins/simple-page-ordering/">Simple Page Ordering</a> plugin. Also took some code from <a href="http://wordpress.org/extend/plugins/order-up-custom-taxonomy-order/">Custom Taxonomy Order</a> plugin.
 Version: 0.1
 Author: ralcus
 Author URI: http://ralcus.com

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class simple_term_ordering {

  function simple_term_ordering() {
    // filter terms on the front end
    add_filter('get_terms_orderby', array( $this, 'frontend_terms_order_filter' ), 10, 2);

    if ( is_admin() ){
      add_action( 'admin_init', array( $this, 'admin_init' ) );
      add_action( 'load-edit-tags.php', array( $this, 'wp_edit' ) );
      add_action( 'wp_ajax_simple_term_ordering', array( $this, 'ajax_simple_term_ordering' ) );
    }

    register_activation_hook(__FILE__, array( $this, 'add_term_order' ));

  }

  function admin_init() {
    load_plugin_textdomain( 'simple-term-ordering', false, dirname( plugin_basename( __FILE__ ) ) . '/localization/' );
  }

  function wp_edit() {
    if ( !current_user_can('edit_others_pages') ) // check permission
      return;

    add_filter( 'contextual_help', array( $this, 'contextual_help' ) ); // add contextual help to hierarchical post screens

  /*
   * term filter
   * ensures terms are ordered by term_order
   */
    add_filter('get_terms_orderby', function($orderby, $args){
      return 't.term_order';
    }, 10, 2);

    wp_enqueue_script( 'simple-term-ordering', plugin_dir_url( __FILE__ ) . 'simple-term-ordering.js', array('jquery-ui-sortable'), '0.9.7', true );
    $js_trans = array(
        'RepositionTree' => __("Items can only be repositioned within their current branch in the page tree / hierarchy (next to pages with the same parent).\n\nIf you want to move this item into a different part of the page tree, use the Quick Edit feature to change the parent before continuing.", 'simple-term-ordering')
        );
    wp_localize_script( 'simple-term-ordering', 'simple_term_ordering_l10n', $js_trans );

  }

  function contextual_help( $help )
  {
    return $help . '
      <p><strong>'. __( 'Simple Term Ordering', 'simple_term_ordering' ) . '</strong></p>
      <p>' . __( 'To reposition an item, simply drag and drop the row by "clicking and holding" it anywhere (outside of the links and form controls) and moving it to its new position.', 'simple-term-ordering' ) . '</p>
      <p>' . __( 'To keep things relatively simple, the current version only allows you to reposition items within their current tree / hierarchy (next to pages with the same parent). If you want to move an item into or out of a different part of the page tree, use the "quick edit" feature to change the parent.', 'simple-term-ordering' ) . '</p>
    ';
  }

  function ajax_simple_term_ordering() {
    global $wpdb;

    // check permissions again and make sure we have what we need
    if ( ! current_user_can('edit_others_pages') || empty( $_POST['id'] ) || ( ! isset( $_POST['previd'] ) && ! isset( $_POST['nextid'] ) ) )
      die(-1);

    // real term?
    if ( ! $term = get_term( $_POST['id'], $_POST['taxonomy'] ) )
      die(-1);

    $previd = isset( $_POST['previd'] ) ? $_POST['previd'] : false;
    $nextid = isset( $_POST['nextid'] ) ? $_POST['nextid'] : false;
    $new_pos = array(); // store new positions for ajax

    $siblings = get_terms($term->taxonomy, array(
      'hide_empty' => 0,
      'hierarchical' => 1,
      'parent' => $term->parent,
      'orderby' => 'term_order',
      'order' => 'ASC',
      'exclude' => $term->term_id
    )); // fetch all the siblings (relative ordering)

    $term_order = 0;

    foreach( $siblings as $sibling ) :

      // if this is the term that comes after our repositioned term, set our repositioned term position and increment term order
      if ( $nextid == $sibling->term_id ) {
        //update term_order
        $wpdb->query($wpdb->prepare(
          "
            update $wpdb->terms set term_order = '%s'
            where term_id = '%s'
          ",
          $term_order,
          $term->term_id
        ));
        $new_pos[$term->term_id] = $term_order;
        $term_order++;
      }

      // if repositioned term has been set, and new items are already in the right order, we can stop
      if ( isset( $new_pos[$term->term_id] ) && $sibling->term_order >= $term_order ){
        //break;
      }

      // set the menu order of the current sibling and increment the menu order
      $wpdb->query($wpdb->prepare(
        "
          update $wpdb->terms set term_order = '%s'
          where term_id = '%s'
        ",
        $term_order,
        $sibling->term_id
      ));
      $new_pos[$sibling->term_id] = $term_order;
      $term_order++;


      // if this is the term that comes before our repositioned term, set our repositioned term position and increment term order
      if ( ! $nextid && $previd == $sibling->ID ) {
        $wpdb->query($wpdb->prepare(
          "
            update $wpdb->terms set term_order = '%s'
            where term_id = '%s'
          ",
          $term_order,
          $term->term_id
        ));
        $new_pos[$term->term_id] = $term_order;
        $term_order++;
      }


    endforeach;

    // if the moved term has children, we need to refresh the page
    $children = get_terms($term->taxonomy, array( 'hide_empty' => 0, 'hierarchical' => 1, 'parent' => $term->term_id ));
    if ( ! empty( $children ) )
      die('children');

    die( json_encode($new_pos) );
  }

  /*
   * term filter
   * on the front end if 'term_order' is set as the orderby then it ensures terms are ordered by term_order
   */
  function frontend_terms_order_filter($orderby, $args) {
    if ( $args['orderby'] == 'term_order' ) {
      $orderby =  't.term_order';
    }

    return $orderby;
  }

  function add_term_order() {
    global $wpdb;
    $init_query = $wpdb->query("SHOW COLUMNS FROM $wpdb->terms LIKE 'term_order'");
    if ($init_query == 0) { $wpdb->query("ALTER TABLE $wpdb->terms ADD `term_order` INT( 4 ) NULL DEFAULT '0'"); }
  }

}

$simple_term_ordering = new simple_term_ordering;