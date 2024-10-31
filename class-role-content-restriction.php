<?php

class Role_Content_Restriction {
    
    //name of the postmeta key where restriction parameters for each post are stored
    private $meta_key;
        
    //prefix to the name of the checkboxes
    private $checkboxes_prefix;
    
    //data for nonce verification
    private $nonce_action,$nonce_id;
    
    
    function __construct() {
        $plugin_slug = 'role-content-restriction';
        $this->meta_key = $plugin_slug;
        $this->checkboxes_prefix = $plugin_slug.'_chk';
        $this->nonce_action = $plugin_slug.'_nonce';
        $this->nonce_id = $plugin_slug.'_save';
    }
    
    /*
     * Returns array of roles using wordpress get_editable_roles() function, excluding the administrator role (which is irrelevant to restriction settings, as administrator has always access anyway). Includes additional roles defined in the current environment (for example custom roles defined by WooCommerce or other scripts), along with the standard ones.
     *
     * @since       1.0.0
     * @access      public
     * @return      array       Currently defined roles
    */
    function get_editable_roles() {
        //get available roles
        require_once( ABSPATH . 'wp-admin/includes/user.php');
        $editable_roles = array_reverse( get_editable_roles() );
        //remove admin, since admin has anyway always access so it's pointless to show in the list
        unset( $editable_roles['administrator'] );
        return $editable_roles;
    }
    /*
     * Returns an array of all available post types, the standard ones (post, page) along with the custom post type defined in the current environment
     *
     * @since       1.0.0
     * @access      public
     * @return      array     An array of post types
    */
    function get_post_types() {
        //standard post types
        $post_types = array( 'post', 'page' );
       
        //add custom post types
        $args = array(
           'public'   => true,
           '_builtin' => false
        );
        //paramters for get_post_types()
        $output = 'names'; // names or objects
        $operator = 'and'; // 'and' or 'or'
        foreach ( get_post_types( $args, $output, $operator )  as $post_type ) {
            $post_types[] = $post_type;
        }
        
        return $post_types;
    }
    
    /*
     * Provide the content of the metabox that will be added to post creator,
     * allowing user to chose the role that have access to that content
     *
     * @since       1.0.0
     * @access      public
     * @param       post $post The post object.
     * @return      string  The html output to be used in the metabox
    */
    function return_user_interface($post) {
        $restrict_content_label = __( 'Restrict access to this content', 'role-content-restriction' );
        $instructions = __( 'Display this content only for ...', 'role-content-restriction' );
        
        //Get current restriction settings if available
        $current_meta_value = get_post_meta( $post->ID, $this->meta_key, true );
        //in the stored value roles are separeted by ";", convert into an array of roles
        $selected_roles = explode( ';', $current_meta_value);
        
        
        //Checkbox to trigger restriction
        $is_trigger_checked = ( !empty( $current_meta_value ) ) ? ' checked="checked"': null;
        $trigger_restriction = '<p><input type="checkbox" name="role-content-restriction_restrict_content" value="1" ' . $is_trigger_checked . '/> ' . $restrict_content_label;
        
        //prefix to the name of the checkboxes
        $checkboxes_prefix = $this->checkboxes_prefix;
        
        $editable_roles = $this->get_editable_roles();
        
        //Prepares dropdown of available roles
        $checkboxes = array();
        foreach ( $editable_roles as $role => $details ) {
            //prepare data for the checkbox
            $role_name = translate_user_role( $details['name'] );
            $checkbox_name_attr = $checkboxes_prefix . esc_attr( $role );
            
            //is this role currently selected?
            $is_currently_selected = (in_array( esc_attr( $role ), $selected_roles)) ? 'checked="checked"' : null;
            
            $checkboxes[] = '<input type="checkbox" name="' . $checkbox_name_attr . '" value="' . esc_attr( $role ) . '" ' . $is_currently_selected. '/> ' . $role_name;
        }
        
        
        //prepare final output
        $instruction_output = "<p>$instructions</p>";
        $role_selector = '<p>' . implode( '</p><p>', $checkboxes) . '</p>';
        
        //wrap the output, in order to allow user to add custom styles or interact with the box
        $wrapper =
             '<div id="role-content-restriction_metabox">'
                    . '<div id="role-content-restriction_restrict_post">' . $trigger_restriction . '</div>'
                    . '<div id="role-content-restriction_instructions">' . $instruction_output . '</div>'
                    . '<div id="role-content-restriction_role_selector">' . $role_selector . '</div>'
                    //add nonce field for security
                    . wp_nonce_field( $this->nonce_id, $this->nonce_action)
            . '</div>';
        
        return $wrapper;
    }
    
    //echoes return_user_interface
    function output_user_interface($post_id) {
        echo $this->return_user_interface($post_id);
    }
    
    /*
     * Save restriction settings for this post in the post's metadata, using the postmeta table
     *
     * @since       1.0.0
     * @access      public
     * 
     * @param int $post_id The post ID.
     * @param post $post The post object.
     * @return  void
     * 
     */
    function save_restriction( $post_id, $post) {
        /*
         * Security checking before saving data
        */
        
        /* Check if metabox can be saved (include nonce verification and  excludes Wordpress automatica savings */
        if ( ! $this->is_valid_post_type() || ! $this->metabox_can_be_saved( $post_id, $this->nonce_id, $this->nonce_action ) ) {
           return;
        }
        
        /*
         * Security checking ok
         * Prepares data on content restrictions (a list of selected roles)
        */
        $meta_key = $this->meta_key;
        $checkboxes_prefix = $this->checkboxes_prefix;
        
        /* Does access to this content have to be restricted */
        if ( !in_array( 'role-content-restriction_restrict_content', array_keys( $_POST ) ) || !boolval( $_POST['role-content-restriction_restrict_content'] ) ) {
            /* No restriction, so won't save any meta data for this post (or remove them if previously stored) */
            $new_meta_value = '';
        } else {
            /*
             * Restriction has been triggered on this post
            */
            
                        //will store the selected roles in this array
            $selected_roles = array();
            
            //to get the value, loop over the available roles
            $editable_roles = $this->get_editable_roles();
            $editable_roles_keys = array_keys( $editable_roles );
            
            //Loop over posted values 
            foreach ( $_POST as $key => $value ) {
                //find those relative to our plugin
                if ( substr( $key, 0, strlen( $checkboxes_prefix ) ) == $checkboxes_prefix) {
                    $posted_role = sanitize_text_field( $value );
                    //check if it is an available role
                    if ( in_array( $posted_role, $editable_roles_keys )) {
                        
                        //add this role to the list of those with access to this content
                        $selected_roles[] =  $posted_role;
                    }
                }
            }
            
            //Meta value to store for this post, which will be a list of available roles
            $new_meta_value = implode( ';', $selected_roles );
        }
        
        /*
         * Save content restriction settings as meta value (if necessary)
        */

        /* Get the current restrictions parameters on this post*/
        $current_meta_value = get_post_meta( $post_id, $meta_key, true );

        /* If no previous restriction parameters are available, store new value */
        if ( $new_meta_value && '' == $current_meta_value ) {
            add_post_meta( $post_id, $meta_key, $new_meta_value, true );
            
        /* If restriction parameters have been changed, update them */
        } elseif ( $new_meta_value && $new_meta_value != $current_meta_value ) {
            update_post_meta( $post_id, $meta_key, $new_meta_value );
        
        /* If new restriction parameter string is empty, remove old value (means that no restriction is set on this post */
        } elseif ( '' == $new_meta_value && $current_meta_value ) {
            delete_post_meta( $post_id, $meta_key, $current_meta_value );
        }
    }
    
    /*
     * Function used when display the content of a post.
     * It will check the restriction settings for this post and return the original content is user has access, or an error message if the user does not have access
     *
     * @since       1.0.0
     * @access      public
     * @param       string $content the original content of the post
     * @return      string Either the original content, or an error message if user has no access to this content
    */
    function content_restriction( $content ) {
        $post_id = get_the_ID();
        
        //fetch restriction settings for this post
        $restriction = get_post_meta( $post_id, $this->meta_key, true );
        
        if ( !$restriction ) return $content;
        
        //access to this content is restricted; convert the meta value for this post's restriction to an array of roles
        
        $selected_roles = explode( ';', $restriction );
        
        
        /* What will be show to user who do not have access */
        $restricted_content_message_not_logged = '<a href="'
                                //link to login page and redirect to current page
                                .wp_login_url( get_permalink() )
                                .'">'.__( "Restricted content! Please log in to access this page.", 'role-content-restriction' ) . '</a>';
        $restricted_content_message_no_access = __( "You do not have permission to access this content!", 'role-content-restriction' );
        
        //if user is not logged in it cannot have the required role
        if ( !is_user_logged_in() ) {
            return $restricted_content_message_not_logged;
        } else {
            //user is logged in: fetch its current role
            $user_data = wp_get_current_user();
            $user_roles = ( array ) $user_data->roles;
            
            $user_has_access = false;
            foreach ( $selected_roles as $role_with_access ) {
                if ( in_array( $role_with_access, $user_roles ) ) $user_has_access = true;
            }
            
            
            //check if role is among those who have access
            if ( $user_has_access
                    //admin has always access regardless of restriction settings
                    || in_array( 'administrator', $user_roles)
                ) {
                //has access
                return $content;
            } else {
                //no access
                return $restricted_content_message_no_access;
            }
        }
            
                
    }
    
    /*
     * Private functions used for security checks
    */
    
    
    
    /**
    * Verifies that the post type that's being saved is valid
    *
    * @since       1.0.0
    * @access      private
    * @return      bool      Return if the current post type is a post; false, otherwise.
    */
    private function is_valid_post_type() {
        $post_types = $this->get_post_types();
        
        return ! empty( $_POST['post_type'] ) && in_array( $_POST['post_type'] , $post_types);
    }
    
    /**
    * Determines whether or not the metabox data can be saved, by checking if current user has the ability to save meta data associated with this post and whether the nonce data is valid
    *
    * @since       1.0.0
    * @access      private
    * @param       int     $post_id      The ID of the post being save
    * @param       string  $nonce_action The name of the action associated with the nonce.
    * @param       string  $nonce_id     The ID of the nonce field.
    * @return      bool                  Whether or not the user has the ability to save this post.
    */
   private function metabox_can_be_saved( $post_id, $nonce_id, $nonce_action ) {
        //does not need to change metadata if Wordpress is automatically saving this data
       $is_autosave = wp_is_post_autosave( $post_id );
       $is_revision = wp_is_post_revision( $post_id );
       
       //check the nonce 
       $is_valid_nonce = ( isset( $_POST[ $nonce_action ] ) && wp_verify_nonce( $_POST[ $nonce_action ], $nonce_id ) );
    
       // Return true if meta data can be saved, false otherwise
       return ! ( $is_autosave || $is_revision ) && $is_valid_nonce;
   }
} //ENd of class Role_Content_Restriction