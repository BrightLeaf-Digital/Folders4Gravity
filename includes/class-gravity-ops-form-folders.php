<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

GFForms::include_addon_framework();

/**
 * Class Form_Folders
 *
 * This class extends the GFAddOn and is responsible for handling the Form Folders for Gravity Forms plugin.
 */
class Gravity_Ops_Form_Folders extends GFAddOn {


	// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
	/**
	 * The current version of the plugin
	 *
	 * @var string
	 */
	protected $_version = FOLDERS_4_GRAVITY_VERSION;
	/**
	 * A string representing the slug used for the plugin.
	 *
	 * @var string
	 */
	protected $_slug = 'go_f4g_forms-folders';
	/**
	 * The basename path of the plugin
	 *
	 * @var string
	 */
	protected $_path = FOLDERS_4_GRAVITY_BASENAME;
	/**
	 * The full file path of the current script.
	 *
	 * @var string
	 */
	protected $_full_path = __FILE__;
	/**
	 * The full title of the plugin
	 *
	 * @var string
	 */
	protected $_title = 'Folders4Gravity';
	/**
	 * The short title of the plugin.
	 *
	 * @var string
	 */
	protected $_short_title = 'Folders4Gravity';
	/**
	 * Holds a list of capabilities.
	 *
	 * @var array
	 */
	protected $_capabilities = [ 'go_folders_4_gravity_uninstall' ];
	/**
	 * Holds the capability required for uninstallation.
	 *
	 * @var string
	 */
	protected $_capabilities_uninstall = 'go_folders_4_gravity_uninstall';
	/**
	 * Holds the singleton instance of the class.
	 *
	 * @var self|null
	 */
	private static ?self $_instance = null;
	// phpcs:enable PSR2.Classes.PropertyDeclaration.Underscore

	/**
     * The prefix to be used by the plugin. Gravity Ops-Folders4Gravity
     *
     * @var string
     */
	private $prefix = 'go_f4g_';

    /**
     * Stores the name of the custom taxonomy.
     *
     * @var string
     */
    private $taxonomy_name = 'go_f4g_form_folders';
    /**
     * Stores the taxonomy name for viewing folders.
     *
     * @var string
     */
    private $view_taxonomy_name = 'go_f4g_gv_view_folders';

	/**
	 * Returns the singleton instance of this class.
	 *
	 * This method ensures that only one instance of the class is created.
	 * If the instance does not yet exist, it is created; otherwise,
	 * the existing instance is returned.
	 *
	 * @return self|null The singleton instance of the class.
	 */
	public static function get_instance(): ?self {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Initializes the class by adding necessary filters.
	 *
	 * @return void
	 */
	public function init() {
		parent::init();
		$this->register_form_folders_taxonomy();

		add_action( "wp_ajax_{$this->prefix}create_folder", [ $this, 'handle_create_folder' ] );
		add_action( "wp_ajax_{$this->prefix}assign_forms_to_folder", [ $this, 'handle_assign_forms_to_folder' ] );
		add_action( "wp_ajax_{$this->prefix}remove_form_from_folder", [ $this, 'handle_remove_form_from_folder' ] );
		add_action( "wp_ajax_{$this->prefix}rename_folder", [ $this, 'handle_folder_renaming' ] );
		add_action( "wp_ajax_{$this->prefix}delete_folder", [ $this, 'handle_folder_deletion' ] );
        add_action( "wp_ajax_{$this->prefix}duplicate_form", [ $this, 'handle_duplicate_form' ] );
        add_action( "wp_ajax_{$this->prefix}trash_form", [ $this, 'handle_trash_form' ] );
        add_action( "wp_ajax_{$this->prefix}save_form_order", [ $this, 'ajax_save_form_order' ] );
        add_action( "wp_ajax_{$this->prefix}save_folder_order", [ $this, 'ajax_save_folder_order' ] );
	}

	/**
	 * Initializes the admin functionality of the plugin.
	 *
	 * @return void
	 */
	public function init_admin() {
		parent::init_admin();
        add_action( 'admin_menu', [ $this, 'register_menus' ], 15 );
        add_action(
            'wp_dashboard_setup',
            function () {
				wp_add_dashboard_widget(
                    'folders_4_gravity_dashboard_widget',
                    'Form Folders',
                    [ $this, 'dashboard_widget' ]
				);
			}
        );
	}

    /**
     * Registers the menus used in the application.
     * This includes adding a top-level menu and submenus as needed.
     *
     * @return void
     */
    public function register_menus() {
        $this->register_form_folders_submenu();
        $this->add_top_level_menu();
    }

    /**
     * Add a top-level menu in the WordPress admin.
     *
     * @return void
     */
	public function add_top_level_menu() {

		global $menu;

		$has_full_access = current_user_can( 'gform_full_access' );
		$min_cap         = GFCommon::current_user_can_which( $this->_capabilities_app_menu );
		if ( empty( $min_cap ) ) {
			$min_cap = 'gform_full_access';
		}

		// if another plugin in our suit is already installed and created the submenu we don't have to.
		if ( in_array( 'gravity_ops', array_column( $menu, 2 ), true ) ) {
			add_submenu_page(
                'gravity_ops',
                $this->_short_title,
                $this->_short_title,
                $has_full_access ? 'gform_full_access' : $min_cap,
                $this->_slug,
                [ $this, 'form_folders_page' ]
            );

			return;
		}

		$number        = 10;
		$menu_position = '16.' . $number;
		while ( isset( $menu[ $menu_position ] ) ) {
			$number       += 10;
			$menu_position = '16.' . $number;
		}

		$this->app_hook_suffix = add_menu_page(
            'GravityOps',
            'GravityOps',
            $has_full_access ? 'gform_full_access' : $min_cap,
            'gravity_ops',
            [ $this, 'create_top_level_menu' ],
            $this->get_app_menu_icon(),
            $menu_position
        );
		add_submenu_page(
            'gravity_ops',
            $this->_short_title,
            $this->_short_title,
            $has_full_access ? 'gform_full_access' : $min_cap,
            $this->_slug,
            [
				$this,
				'form_folders_page',
            ]
        );
	}

    /**
     * Retrieves the SVG icon for the application menu in a base64-encoded string.
     *
     * The method generates an SVG icon XML, encodes it in base64, and formats it as a data URL
     * suitable for use as an image source in web applications.
     *
     * @return string The base64-encoded SVG icon as a data URL.
     */
    public function get_app_menu_icon() {
        $svg_xml = '<?xml version="1.0" encoding="utf-8"?><svg height="24" id="Layer_1" viewBox="0 0 300 300" width="24" xmlns="http://www.w3.org/2000/svg" >
<defs>
<style>
      .cls-1 {
        fill: #fff;
      }
      .cls-4 {
        fill: #fff;
      }
    </style>
<radialGradient cx="-28.79" cy="-50.67" fx="-28.79" fy="-50.67" gradientTransform="translate(.26 .38) scale(1.05)" gradientUnits="userSpaceOnUse" id="radial-gradient" r="433.22">
<stop offset="0" stop-color="#402a56"/>
<stop offset="1" stop-color="#2f2e41"/>
</radialGradient>
</defs>
<g>
<g>
<path class="cls-4" d="M204.44,45.16c-7.84,2.35-15.26,5.96-22.05,10.2,0,0-.02,0-.03.01-15.43,9.64-27.63,22.58-34.25,31.59-9.53,13-27.14,30.42-43.32,13.65-2.65-2.75-4.19-6.14-4.72-9.87-1.88-13.02,8.47-30.17,26.39-38.44,33.79-15.6,95.3-12.35,77.98-7.15Z" fill="black"/>
<path class="cls-1" d="M214.25,50.81c-4.41,2.77-11.39,11-16.43,17.33,0,0,0,0-.01,0-1.67,2.09-3.13,3.98-4.21,5.39-11.02,14.34-31.85,47.1-37.9,60.65-8.26,18.49-36.2,49.52-61.36,35.86-.16-.08-.32-.18-.47-.27-.04-.02-.08-.05-.12-.06-25.34-14.5-19.28-50.67,2.72-74.12-8.81,13.47-6.66,25.45.75,32.32,17.55,16.25,36.77,2.62,47.34-13.87,8.15-12.72,17.71-24.76,28.14-34.82,8.38-8.08,23.51-19.35,32.73-24.2,3.09-1.64,7.15-3.25,8.83-4.2Z" fill="black"/>
<path class="cls-1" d="M221.42,60.81c-.66,1.3-5.48,10.14-10.42,20.46t0,.01c-3.67,7.67-7.41,16.16-9.58,23-4.32,13.6-16.91,56.93-19.49,64.57-4.83,14.29-11.87,24.53-20.51,31.19-.29.23-.58.44-.88.66-9.4,6.88-20.63,9.65-32.99,8.88-15.67-.98-27.53-10.99-31.65-27.29,2.63,5.35,7.76,9.4,16.05,10.18,17.18,1.61,29.48-5.6,37.79-13.93,2.9-2.9,5.31-5.95,7.27-8.81,7.58-11.05,20.74-47.79,28.81-63.68,15.38-30.3,27.18-36.6,35.61-45.22Z" fill="black"/>
<path class="cls-1" d="M223.33,174.26h0c-.01.29-.03.58-.05.87-1.12,21.48-14.24,36.62-31.35,38.34-12.52,1.25-24.18-3-31.41-12.78.29-.21.58-.43.88-.66,3.05,1.98,6.75,3.07,11.19,3.03,22.82-.2,31.59-25.49,32.65-44.19,3.54-62.38,17.03-82.68,18.03-85.08-.29,4.36-4.98,17.58-5.62,30.49-.18,3.55-.23,7-.19,10.35h0c.27,21.03,4.28,38.11,5.6,51.39.28,2.83.36,5.58.27,8.23Z" fill="black"/>
<path class="cls-1" d="M241.9,175.78c-7.01,2.69-13.2,2.1-18.62-.65.02-.29.03-.58.05-.86,2.51.46,5.02.16,7.53-.96,11.48-5.11,7.91-25.36,3.03-36.08-4.65-10.23-7.63-25.56-8.77-44.1,5.25,23.34,16.89,31.95,23.93,41.17,6.73,8.81,16.03,32.6-7.15,41.48Z" fill="black"/>
</g>
</g>
</svg>';
        return sprintf( 'data:image/svg+xml;base64,%s', base64_encode( $svg_xml ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
    }

    /**
     * Outputs the HTML for the top-level menu that showcases a list of additional plugins.
     *
     * @return void
     */
    public function create_top_level_menu() {
        ?>
        <h1 style="padding: 15px;">Check out the rest of our plugins</h1>
        <ul style="padding-left: 15px; font-size: larger; line-height: 1.5em; list-style: disc;">
            <li>
                <a target="_blank" href="https://brightleafdigital.io/asana-gravity-forms/">Asana Integration for Gravity Forms</a>
            </li>
            <li>
                <a target="_blank" href="https://brightleafdigital.io/mass-email-notifications-for-gravity-forms/">Mass Email Notifications for Gravity Forms</a>
            </li>
            <li>
                <a target="_blank" href="https://brightleafdigital.io/turn-gravityview-into-a-kanban-project-board/">Kanban View for Gravity View</a>
            </li>
            <li>
                <a target="_blank" href="https://brightleafdigital.io/recurring-form-submissions-for-gravity-forms/">Recurring Form Submissions for Gravity Forms</a>
            </li>
            <li>
                <a target="_blank" href="https://brightleafdigital.io/global-variables-for-gravity-math/">Global Variables for Gravity Math</a>
            </li>
            <li>
                <a target="_blank" href="https://brightleafdigital.io/folders-4-gravity/">Folders 4 Gravity</a>
            </li>
            <li>
                <a target="_blank" href="https://brightleafdigital.io/gravityops-search/">GravityOps Search</a>
            </li>
            <li>
                <a target="_blank" href="https://wordpress.org/plugins/brightleaf-digital-php-compatibility-scanner/">BLD PHP Compatibility Scanner</a>
            </li>
        </ul>
        <?php
    }

    /**
     * Renders the dashboard widget displaying available form and view folders.
     *
     * The method retrieves the terms associated with "go_f4g_form_folders" and "go_f4g_gv_view_folders"
     * taxonomies, counts the number of objects in each folder, and outputs an HTML structure
     * with a list of links to the respective folder pages.
     *
     * @return void
     */
    public function dashboard_widget() {
        $folders = $this->get_ordered_folders();

        $view_folder_nonce = wp_create_nonce( 'view_folder' );

        ?>
        <div class="forms">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->_slug ) ); ?>" target="_blank">
                <h1 class="folder-type-title">Form Folders</h1>
            </a>
		    <br>
		    <ul>
			<?php
			foreach ( $folders as $folder ) {
				$form_count  = count( get_objects_in_term( $folder->term_id, $this->taxonomy_name ) );
				$folder_link = admin_url( 'admin.php?page=' . $this->_slug . '&folder_id=' . $folder->term_id . '&view_folder_nonce=' . $view_folder_nonce );
				?>
                <li class="folder-item">
                    <a href="<?php echo esc_url( $folder_link ); ?>" target="_blank">
                        <span class="dashicons dashicons-category folder-icon"></span>
                        <span class="folder-name"><?php echo esc_html( $folder->name ); ?> (<?php echo esc_html( $form_count ); ?>)</span>
                    </a>
                </li>
	    		<?php
			}
			?>
		    </ul>
		</div>
		<?php
    }

	/**
	 * Registers a submenu page under the Gravity Forms menu for form folders.
	 *
	 * @return void
	 */
	public function register_form_folders_submenu() {
		add_submenu_page(
			'gf_edit_forms',
			'Form Folders',
			'Form Folders',
			'gform_full_access',
			$this->_slug,
			[ $this, 'form_folders_page' ]
		);
	}
	/**
	 * Registers a custom taxonomy for organizing forms into folders.
	 *
	 * The taxonomy 'go_f4g_form_folders' is associated with the 'gf_form' post type. It is not publicly queryable,
	 * does not have URL rewrites, and supports a non-hierarchical structure. It includes an admin column for easier management in the admin interface.
	 *
	 * @return void
	 */
	private function register_form_folders_taxonomy() {
		if ( ! taxonomy_exists( $this->taxonomy_name ) ) {
			register_taxonomy(
			$this->taxonomy_name,
			'gf_form',
			[
				'label'             => 'Form Folders',
				'rewrite'           => false,
				'public'            => false,
				'show_admin_column' => true,
				'hierarchical'      => false,
			]
			);
        }
	}

	/**
	 * Handles the creation of a new folder for forms.
	 *
	 * Validates the current user's permission and the provided folder name.
	 * Inserts a new term into the 'go_f4g_form_folders' taxonomy. Returns a success or error message depending on the outcome.
	 *
	 * @return void Sends a JSON response indicating success or failure.
	 */
	public function handle_create_folder() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'create_folder' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}

		if ( ! current_user_can( 'gform_full_access' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
			wp_die();
		}

		if ( empty( $_POST['folderName'] ) ) {
			wp_send_json_error( [ 'message' => 'Folder name is required' ], 403 );
			wp_die();
		}

		$folder_name = sanitize_text_field( wp_unslash( $_POST['folderName'] ) );
		$inserted    = wp_insert_term( $folder_name, $this->taxonomy_name );

		if ( is_wp_error( $inserted ) ) {
			wp_send_json_error( [ 'message' => $inserted->get_error_message() ], 403 );
			wp_die();
		}

		wp_send_json_success( [ 'message' => 'Folder created successfully!' ] );
		wp_die();
	}

	/**
	 * Handles the process of assigning a form to a folder.
	 *
	 * Ensures the current user has the necessary permissions to perform the action.
	 * Validates required input data, assigns the form to the specified folder,
	 * and returns the appropriate success or error messages.
	 *
	 * @return void Outputs a JSON response indicating success or failure.
	 */
	public function handle_assign_forms_to_folder() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'assign_form' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}

		if ( ! current_user_can( 'gform_full_access' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
			wp_die();
		}

		if ( empty( $_POST['formIDs'] ) || empty( $_POST['folderID'] ) ) {
			wp_send_json_error( [ 'message' => 'Form and Folder are required' ] );
			wp_die();
		}

		$form_ids  = array_map( 'absint', (array) $_POST['formIDs'] );
		$folder_id = absint( $_POST['folderID'] );

		foreach ( $form_ids as $form_id ) {
            $result = wp_set_object_terms( $form_id, [ $folder_id ], $this->taxonomy_name );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( [ 'message' => $result->get_error_message() ] );
				wp_die();
			}
		}

		wp_send_json_success( [ 'message' => 'Form assigned successfully!' ] );
		wp_die();
	}

	/**
	 * Handles the removal of a form from a folder.
	 *
	 * This function validates the nonce, checks user permissions, and removes the specified form
	 * from its associated folder. It sends a JSON response indicating success or failure.
	 *
	 * @return void Outputs a JSON response and terminates execution.
	 */
	public function handle_remove_form_from_folder() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'remove_form' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}

		if ( ! current_user_can( 'gform_full_access' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
			wp_die();
		}

		if ( empty( $_POST['formID'] ) ) {
			wp_send_json_error( [ 'message' => 'Form ID is required' ], 403 );
			wp_die();
		}

		$form_id = absint( $_POST['formID'] );

		$result = wp_set_object_terms( $form_id, [], $this->taxonomy_name );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 403 );
			wp_die();
		}

		wp_send_json_success( [ 'message' => 'Form removed from the folder successfully!' ] );
		wp_die();
	}

	/**
	 * Handles the renaming of a folder via an AJAX request.
	 *
	 * This function validates the provided nonce, ensures required parameters
	 * are present, and updates the folder name in the taxonomy. Errors are returned
	 * in JSON format, and a success response is sent upon successful renaming.
	 *
	 * @return void This function exits with a JSON response and does not return.
	 */
	public function handle_folder_renaming() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rename_folder' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}

		if ( empty( $_POST['folderID'] ) || empty( $_POST['folderName'] ) ) {
			wp_send_json_error( [ 'message' => 'Missing required parameters.' ], 400 );
			wp_die();
		}

		$folder_id   = absint( $_POST['folderID'] );
		$folder_name = sanitize_text_field( wp_unslash( $_POST['folderName'] ) );

		$folder = get_term( $folder_id, $this->taxonomy_name );
		if ( is_wp_error( $folder ) || ! $folder ) {
			wp_send_json_error( [ 'message' => 'The specified folder does not exist.' ], 404 );
		}

		// Update the folder name
		$updated_folder = wp_update_term( $folder_id, $this->taxonomy_name, [ 'name' => $folder_name ] );
		if ( is_wp_error( $updated_folder ) ) {
			wp_send_json_error( [ 'message' => 'Failed to rename the folder. Please try again.' ] );
		}

		wp_send_json_success( [ 'message' => 'Folder renamed successfully.' ] );
		wp_die();
	}

	/**
	 * Deletes a folder via an AJAX request.
	 *
	 * @return void
	 */
	public function handle_folder_deletion() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'delete_folder' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
			wp_die();
		}
		if ( empty( $_POST['folderID'] ) ) {
			wp_send_json_error( [ 'message' => 'Missing required parameters.' ], 400 );
		}
		$folder_id = absint( $_POST['folderID'] );
		$folder    = get_term( $folder_id, $this->taxonomy_name );
		if ( is_wp_error( $folder ) || ! $folder ) {
			wp_send_json_error( [ 'message' => 'The specified folder does not exist.' ], 404 );
		}
		$result = wp_delete_term( $folder_id, $this->taxonomy_name );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => 'Failed to delete the folder. Please try again.' ], 403 );
		} else {
			wp_send_json_success( [ 'message' => 'Folder deleted successfully.' ] );
		}
	}

    /**
     * Deletes all plugin created data during uninstall
     *
     * @return void
     */
    public function uninstall() {

        $forms = GFAPI::get_forms();
        foreach ( $forms as $form ) {
	        wp_set_object_terms( $form['id'], [], $this->taxonomy_name );
        }

		// Delete the taxonomy folders
		$folder_ids = get_terms(
            [
				'taxonomy'   => $this->taxonomy_name,
				'hide_empty' => false,
				'fields'     => 'ids',
			]
            );

		if ( ! is_wp_error( $folder_ids ) ) {
			foreach ( $folder_ids as $folder ) {
				wp_delete_term( $folder, $this->taxonomy_name );
			}
		}
    }

    /**
     * Duplicates a form via an AJAX request.

     * @return void
     */
    public function handle_duplicate_form() {

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'duplicate_form' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
            wp_die();
        }

        if ( empty( $_POST['formID'] ) ) {
            wp_send_json_error( [ 'message' => 'Missing required parameters.' ], 400 );
        }

        $form_id   = absint( $_POST['formID'] );
        $folder_id = absint( $_POST['folderID'] ) ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        $result    = GFAPI::duplicate_form( $form_id );
        if ( ! is_wp_error( $result ) ) {
            if ( $folder_id ) {
                wp_set_object_terms( $result, [ $folder_id ], $this->taxonomy_name );
            }
            wp_send_json_success( [ 'message' => 'Form duplicated successfully.' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Failed to duplicate the form. Please try again.' ], 403 );
            wp_die();
        }
    }


    /**
     * Trashes a form via an AJAX request.
     *
     * @return void
     */
    public function handle_trash_form() {

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'trash_form' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce. Request rejected.' ], 403 );
            wp_die();
        }

        if ( empty( $_POST['formID'] ) ) {
            wp_send_json_error( [ 'message' => 'Missing required parameters.' ], 400 );
        }

        $form_id = absint( $_POST['formID'] );
        $result  = GFFormsModel::trash_form( $form_id ); // method returns true for failure to trash and false for successfully trashing. weird.
        if ( ! is_wp_error( $result ) && ! $result ) {

            $result = wp_set_object_terms( $form_id, [], $this->taxonomy_name );

			if ( is_wp_error( $result ) ) {
				GFFormsModel::restore_form( $form_id );
                wp_send_json_error( [ 'message' => 'Encountered an error removing the form from the folder.' ], 403 );
                wp_die();

			}

            wp_send_json_success( [ 'message' => 'Form trashed successfully.' ] );

        } else {
            wp_send_json_error( [ 'message' => 'Failed to trash the form. Please try again.' ], 403 );
        }
    }

    /**
     * Saves the form order for a specific folder via an AJAX request.
     *
     * @return void
     */
   	public function ajax_save_form_order() {
		// Security check
		if ( ! current_user_can( 'gform_full_access' ) || ! check_ajax_referer( 'save_form_order', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		// Validate and sanitize inputs
		$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
		$order     = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : [];

		if ( ! $folder_id || empty( $order ) ) {
			wp_send_json_error( [ 'message' => 'Missing folder ID or order' ], 400 );
		}

		update_term_meta( $folder_id, "{$this->prefix}form_order", $order );

		wp_send_json_success( [ 'message' => 'Form order saved.' ] );
	}

	/**
	 * Saves the folder order via an AJAX request.
	 *
	 * @return void
	 */
	public function ajax_save_folder_order() {
		// Security check
		if ( ! current_user_can( 'gform_full_access' ) || ! check_ajax_referer( 'save_folder_order', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		// Validate and sanitize inputs
		$order = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : [];

		if ( empty( $order ) ) {
			wp_send_json_error( [ 'message' => 'Missing folder order' ], 400 );
		}

		update_option( "{$this->prefix}folder_order", $order );

		wp_send_json_success( [ 'message' => 'Folder order saved.' ] );
	}

	/**
	 * Gets folders in the custom order if set, otherwise returns default order.
	 *
	 * @return array Array of folder term objects in the correct order.
	 */
	private function get_ordered_folders() {
		$folders = get_terms(
			[
				'taxonomy'   => $this->taxonomy_name,
				'hide_empty' => false,
			]
		);

		if ( empty( $folders ) || is_wp_error( $folders ) ) {
			return [];
		}

		// Get the custom folder order
		$folder_order = get_option( "{$this->prefix}folder_order", [] );

		if ( empty( $folder_order ) ) {
			return $folders;
		}

		// Create a map of folder ID to folder object
		$folder_map = [];
		foreach ( $folders as $folder ) {
			$folder_map[ $folder->term_id ] = $folder;
		}

		// Build ordered array based on saved order
		$ordered_folders = [];
		foreach ( $folder_order as $folder_id ) {
			if ( isset( $folder_map[ $folder_id ] ) ) {
				$ordered_folders[] = $folder_map[ $folder_id ];
				unset( $folder_map[ $folder_id ] );
			}
		}

		// Add any remaining folders that weren't in the saved order
		foreach ( $folder_map as $folder ) {
			$ordered_folders[] = $folder;
		}

		return $ordered_folders;
	}


	/**
	 * Loads stylesheets for the plugin
	 *
	 * @return array
	 */
	public function styles() {
		$styles = [
			[
				'handle'  => 'form-folders-styles',
				'src'     => plugins_url( 'assets/css/folders_stylesheet.css', FOLDERS_4_GRAVITY_BASENAME ),
				'version' => '1.0.0',
				'enqueue' => [
					[ 'query' => 'page=' . $this->_slug ],
				],
			],
			[
                'handle'  => 'folders4formswidget',
                'src'     => plugins_url( 'assets/css/dashboard-widget.css', FOLDERS_4_GRAVITY_BASENAME ),
                'version' => '1.0.0',
                'enqueue' => [
					function () {
							$screen = get_current_screen();
							return is_admin() && $screen && 'dashboard' === $screen->id;
					},
				],
            ],
		];
		return array_merge( parent::styles(), $styles );
	}

    /**
     * Loads scripts for the plugin
     *
     * @return array[]
     */
    public function scripts() {
        $scripts = [
            [
                'handle'    => 'form-folders-scripts',
                'src'       => plugins_url( 'assets/js/folders_script.js', FOLDERS_4_GRAVITY_BASENAME ),
                'version'   => '1.0.0',
                'deps'      => [ 'jquery', 'sortable4folders' ],
				'in_footer' => true,
                'enqueue'   => [
                    [ 'query' => 'page=' . $this->_slug ],
                ],
            ],
            [
				'handle'    => 'sortable4folders',
				'src'       => plugins_url( 'assets/js/Sortable.min.js', FOLDERS_4_GRAVITY_BASENAME ),
				'version'   => '1.15.6',
				'in_footer' => true,
				'enqueue'   => [
					[ 'query' => 'page=' . $this->_slug ],
				],
			],
        ];
        return array_merge( parent::scripts(), $scripts );
    }

	/**
	 * Renders the Form Folders admin page for the Gravity Forms plugin.
	 *
	 * This method displays the main "Form Folders" page or a detailed view of a specific folder
	 * with its assigned forms. Includes functionality for viewing forms within a folder, creating
	 * new folders, and assigning forms to folders. Access is restricted to users with full Gravity Forms access.
	 *
	 * @return void
	 */
	public function form_folders_page() {
		if ( ! current_user_can( 'gform_full_access' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		if ( rgget( 'folder_id' ) ) {
			$this->render_single_folder_page();
		} else {
			$this->render_form_folders_page();
		}
	}

	/**
	 * Renders a single folder page with its assigned forms.
	 *
	 * @return void
	 */
	private function render_single_folder_page() {

		if ( ! isset( $_GET['view_folder_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['view_folder_nonce'] ) ), 'view_folder' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}
		$folder_id = isset( $_GET['folder_id'] ) ? absint( $_GET['folder_id'] ) : 0;

		if ( $folder_id ) {
			$folder = get_term( $folder_id, $this->taxonomy_name );
			if ( is_wp_error( $folder ) || ! $folder ) {
				echo '<div class="error"><p>Invalid folder.</p></div>';
				return;
			}

            $save_form_order_nonce = wp_create_nonce( 'save_form_order' );
            wp_add_inline_script(
                    'form-folders-scripts',
                    sprintf(
                            'const FOLDERS4GRAVITY = %s;',
                            wp_json_encode(
                                [
									'folder_id' => $folder_id,
									'nonce'     => $save_form_order_nonce,
								]
                            )
                    ),
                    'before'
            );

            $forms       = GFAPI::get_forms();
            $saved_order = get_term_meta( $folder_id, "{$this->prefix}form_order", true ) ?: [];
            // Filter forms assigned to this folder
			$forms_in_folder = array_filter(
			$forms,
			function ( $form ) use ( $folder_id ) {
				$terms = wp_get_object_terms( $form['id'], $this->taxonomy_name, [ 'fields' => 'ids' ] );
				return in_array( $folder_id, $terms, true );
			}
			);

			// Sort by saved order
			usort(
			$forms_in_folder,
			function ( $a, $b ) use ( $saved_order ) {
				$pos_a = array_search( $a['id'], $saved_order, true );
				$pos_b = array_search( $b['id'], $saved_order, true );

				// If not found in saved order, push to end
				return ( false === $pos_a ? PHP_INT_MAX : $pos_a )
					<=> ( false === $pos_b ? PHP_INT_MAX : $pos_b );
			}
			);

			?>

			<div class="wrap">
				<h1>Forms in Folder: <?php echo esc_html( $folder->name ); ?> </h1>
				<!--Back button-->
				<br>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->_slug ) ); ?>" class="button">
					Back to All Folders
				</a>
				<br><br>

				<!--Forms Table-->
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
						    <th style="width:30px;"></th>
							<th>Form Name</th>
							<th>Shortcode</th>
							<th>Links</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody class="sortable-forms">

						<?php
						$allowed_svg_tags      = [
							'svg'  => [
								'xmlns'             => true,
								'viewbox'           => true,
								'width'             => true,
								'height'            => true,
								'style'             => true,
								'class'             => true,
								'enable-background' => true,
							],
							'g'    => [
								'fill'            => true,
								'stroke'          => true,
								'stroke-linecap'  => true,
								'stroke-linejoin' => true,
								'stroke-width'    => true,
							],
							'path' => [
								'd'         => true,
								'fill'      => true,
								'stroke'    => true,
								'fill-rule' => true,
							],
						];
						$post_html             = wp_kses_allowed_html( 'post' );
						$combined_allowed_html = array_merge_recursive( $post_html, $allowed_svg_tags );
						$remove_form_nonce     = wp_create_nonce( 'remove_form' );
                        $duplicate_form_nonce  = wp_create_nonce( 'duplicate_form' );
                        $trash_form_nonce      = wp_create_nonce( 'trash_form' );
                        $rename_folder_nonce   = wp_create_nonce( 'rename_folder' );
                        $assign_form_nonce     = wp_create_nonce( 'assign_form' );

						if ( empty( $forms_in_folder ) ) {
							echo '<tr><td colspan="4">No forms found in this folder.</td></tr>';
						} else {
							foreach ( $forms_in_folder as $form ) {
								$edit_form_link = admin_url( 'admin.php?page=gf_edit_forms&id=' . $form['id'] );
								?>
                                    <tr data-form-id="<?php echo esc_attr( $form['id'] ); ?>">
                                        <td class="drag-handle"><span class="dashicons dashicons-move"></span></td>
                                        <!--Form Title-->
                                        <td>
                                            <a href="<?php echo esc_url( $edit_form_link ); ?>"><?php echo esc_html( $form['title'] ); ?></a>
                                        </td>
                                        <!--Shortcode-->
                                        <td>
                                            <code class="copyable">
                                                [gravityform id="<?php echo esc_attr( $form['id'] ); ?>" title="false" description="false"]
                                            </code>
                                        </td>
                                        <!--Links-->
								            <?php $this->render_links_td_section( $form, $allowed_svg_tags, $combined_allowed_html ); ?>
                                        <!--Buttons-->
								        <?php $this->render_buttons_td_section( $form, $remove_form_nonce, $duplicate_form_nonce, $trash_form_nonce ); ?>
                                    </tr>
								<?php
							}
						}

						?>
					</tbody>
				</table>
				<br><br>

				<!--Rename Folder-->
				<form id="rename-folder-form">
					<label for="folder_name" class="form-field-label">Rename Folder</label><br>
					<input type="text" id="folder_name" name="folder_name" placeholder="Folder Name" required>
					<input type="hidden" id="folder_id" name="folder_id" value="<?php echo esc_attr( $folder_id ); ?>">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $rename_folder_nonce ); ?>">
					<button type="submit" class="button">Rename Folder</button>
				</form>

                <br><br>

                <!--Assign Forms to Current Folder-->
				<form id="assign-forms-form">
					<label for="form_ids" class="form-field-label">Assign Forms to Folder</label><br>
					<select id="form_ids" name="form_ids[]" required multiple size="8">
						<?php
						$all_forms = GFAPI::get_forms();
						foreach ( $all_forms as $form ) {
							$assigned_folders = wp_get_object_terms( $form['id'], $this->taxonomy_name, [ 'fields' => 'ids' ] );
							if ( empty( $assigned_folders ) ) {
								echo '<option value="' . esc_attr( $form['id'] ) . '">' . esc_html( $form['title'] ) . '</option>';
							}
						}
						?>
					</select>
					<input type="hidden" id="folder_id" name="folder_id" value="<?php echo esc_attr( $folder_id ); ?>">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $assign_form_nonce ); ?>"> <br>
					<button type="submit" class="button">Assign Forms</button>
				</form>
            </div>
			<?php
		}
	}

	/**
	 * Renders the "Links" section of the table for a specific form in the folder.
	 *
	 * @param array $form The current form.
	 * @param array $allowed_svg_tags A list of allowed SVG tags.
	 * @param array $combined_allowed_html A list of allowed HTML tags.
	 *
	 * @return void
	 */
	private function render_links_td_section( $form, $allowed_svg_tags, $combined_allowed_html ) {
		$edit_form_link = admin_url( 'admin.php?page=gf_edit_forms&id=' . $form['id'] );
		?>
			<td>
				<!--Edit Form-->
				<a href="<?php echo esc_url( $edit_form_link ); ?>">Edit</a> |
				<!--Entries + Dropdown-->
				<?php $this->render_entries_dropdown( $form ); ?>
				<!--Settings + Dropdown-->
				<?php $this->render_settings_dropdown( $form, $allowed_svg_tags, $combined_allowed_html ); ?>
				<!--Live Preview-->
				<?php $this->maybe_render_live_preview_link( $form ); ?>
				<!--Connected Views-->
				<?php $this->maybe_render_connected_views_link( $form ); ?>
			</td>
		<?php
	}

	/**
	 * Renders the main "Form Folders" page.
	 *
	 * @return void
	 */
	private function render_form_folders_page() {

        $create_folder_nonce     = wp_create_nonce( 'create_folder' );
        $assign_form_nonce       = wp_create_nonce( 'assign_form' );
        $view_folder_nonce       = wp_create_nonce( 'view_folder' );
        $delete_folder_nonce     = wp_create_nonce( 'delete_folder' );
        $save_folder_order_nonce = wp_create_nonce( 'save_folder_order' );
        $folders                 = $this->get_ordered_folders();

        $plugin_page_url = get_admin_url() . 'admin.php?page=' . $this->_slug;

		?>
            <div class='wrap fs-section fs-full-size-wrapper'>
                <h2 class='nav-tab-wrapper' style="display: none;">
                    <a href='<?php echo esc_url( $plugin_page_url ); ?>' class='nav-tab fs-tab nav-tab-active home'>
                        Form Folders
                    </a>
                </h2>
			    <div class="wrap">
				<h1>Form Folders</h1>
				<br>
				<ul class="gf-sortable-folders">
					<?php

					foreach ( $folders as $folder ) {
						$form_count  = count( get_objects_in_term( $folder->term_id, $this->taxonomy_name ) );
                        $folder_link = admin_url( 'admin.php?page=' . $this->_slug . '&folder_id=' . $folder->term_id . '&view_folder_nonce=' . $view_folder_nonce );
                        ?>
                        <li class="gf-folder-item" data-folder-id="<?php echo esc_attr( $folder->term_id ); ?>">
                            <span class="gf-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>
                            <a href="<?php echo esc_url( $folder_link ); ?>">
                                <span class="dashicons dashicons-category gf-folder-icon"></span> <?php echo esc_html( $folder->name ); ?> (<?php echo esc_html( $form_count ); ?>)
                            </a>
                        <?php
						if ( ! $form_count ) {
                            ?>
							&nbsp;&nbsp;
							<button class="button delete-folder-button" data-folder-id="<?php echo esc_attr( $folder->term_id ); ?>" data-nonce="<?php echo esc_attr( $delete_folder_nonce ); ?>">Delete Folder</button>
							<?php
						}
                        ?>
                        </li>
						<?php
					}
					?>
				</ul>
				<script type="text/javascript">
					const FOLDERS4GRAVITY_FOLDER_ORDER = {
						nonce: '<?php echo esc_js( $save_folder_order_nonce ); ?>'
					};
				</script>

				<div class="folder-forms">
					<div class="folder-forms-item">
						<form id="create-folder-form">
						    <label for="folder_name" class="form-field-label">Create A New Folder</label><br>
							<input type="text" id="folder_name" name="folder_name" placeholder="Folder Name" required>
							<input type="hidden" name="nonce" value="<?php echo esc_attr( $create_folder_nonce ); ?>">
							<button type="submit" class="button">Create Folder</button>
						</form>
					</div>

					<div class="folder-forms-item">
					    <label for="assign-forms-form" class="form-field-label">Assign Form(s) to a Folder</label>
						<form id="assign-forms-form">
							<label for="form_id" class="form-field-sub-label">Select Form(s) to Assign</label><br>
							<select id="form_id" name="form_ids[]" required multiple size="8">
								<?php
								$all_forms = GFAPI::get_forms();
								foreach ( $all_forms as $form ) {
									$assigned_folders = wp_get_object_terms( $form['id'], $this->taxonomy_name, [ 'fields' => 'ids' ] );
									if ( empty( $assigned_folders ) ) {
                                        ?>
										<option value="<?php echo esc_attr( $form['id'] ); ?>"><?php echo esc_html( $form['title'] ); ?></option>
										<?php
									}
								}
								?>
							</select>
							<br><br>
							<label for="folder_id" class="form-field-sub-label">Select a Folder to Assign To</label><br>
							<select id="folder_id" name="folder_id" required>
								<option value="">Select a Folder</option>
								<?php
								foreach ( $folders as $folder ) {
                                    ?>
									<option value="<?php echo esc_attr( $folder->term_id ); ?>"><?php echo esc_html( $folder->name ); ?></option>
									<?php
								}
								?>
							</select>
							<input type="hidden" name="nonce" value="<?php echo esc_attr( $assign_form_nonce ); ?>">
							<button type="submit" class="button">Assign Form(s)</button>
						</form>
					</div>
				</div>
			</div>
            </div>
		<?php
	}
	/**
	 * Renders the "Entries" dropdown for a specific form in the folder.
	 *
	 * @param array $form The current form.
	 *
	 * @return void
	 */
	private function render_entries_dropdown( $form ) {
		$entries_link        = admin_url( 'admin.php?page=gf_entries&view=entries&id=' . $form['id'] );
		$export_entries_link = admin_url( 'admin.php?page=gf_export&view=export_entry&id=' . $form['id'] );
		if ( in_array( 'gravityview-importer/gravityview-importer.php', get_option( 'active_plugins' ), true ) ) {
			$import_entries_link = admin_url( 'admin.php?page=gv-admin-import-entries#targetForm=' . $form['id'] );
		}
		?>
			<div class="dropdown">
				<a href="<?php echo esc_url( $entries_link ); ?>" class="link">Entries</a>
				<ul class="dropdown-menu">
					<li>
						<a href="<?php echo esc_url( $entries_link ); ?>">Entries</a>
					</li>
					<li>
						<a href="<?php echo esc_url( $export_entries_link ); ?>"> Export Entries </a>
					</li>
					<?php
					if ( isset( $import_entries_link ) ) {
						?>
						<li><a href="<?php echo esc_url( $import_entries_link ); ?>">Import Entries</a></li>
						<?php
					}
					?>
				</ul>
			</div> |
		<?php
	}

    /**
     * Renders the "Settings" dropdown for a specific form in the folder.
     *
     * @param array $form The current form.
     * @param array $allowed_svg_tags The allowed SVG tags.
     * @param array $combined_allowed_html The combined allowed HTML tags.
     *
     * @return void
     */
	private function render_settings_dropdown( $form, $allowed_svg_tags, $combined_allowed_html ) {
        $form_settings_link = admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=settings&id=' . $form['id'] );

		$settings_info = GFForms::get_form_settings_sub_menu_items( $form['id'] );
		?>
			<div class="dropdown">
				<a href="<?php echo esc_url( $form_settings_link ); ?>" class="link">Settings</a>
				<ul class="dropdown-menu">
					<?php
					foreach ( $settings_info as $setting ) {
						$icon_html   = $setting['icon'];
						$icon_output = '';

						if ( preg_match( '/<svg.*<\/svg>/is', $icon_html, $matches ) ) {
							$icon_output = wp_kses( $matches[0], $allowed_svg_tags );
						} elseif ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $icon_html, $matches ) ) {
							// Icon is an <img> tag
							$icon_output = '<img src="' . esc_url( $matches[1] ) . '" alt="" class="settings-icon" />';
						} elseif ( preg_match( '/class=["\']([^"\']+)["\']/', $icon_html, $matches ) ) {
							// Icon is a class-based icon
							$classes = explode( ' ', $matches[1] );
							$classes = array_map( 'sanitize_html_class', $classes );
							$classes = implode( ' ', $classes );

							$icon_output = '<span class="dashicons ' . esc_attr( $classes ) . '"></span>';
						}
						?>
						<li>
							<a href="<?php echo esc_url( $setting['url'] ); ?>" class="settings-item">
								<?php echo wp_kses( $icon_output, $combined_allowed_html ); ?>
								<?php echo esc_html( $setting['label'] ); ?>
							</a>
						</li>
						<?php
					}
					?>
				</ul>
			</div>
		<?php
	}
    /**
     * Renders the "Buttons" section of the table for a specific form in the folder.

     * @param array  $form The current form.
     * @param string $remove_form_nonce The nonce for removing the form.
     * @param string $duplicate_form_nonce The nonce for duplicating the form.
     * @param string $trash_form_nonce The nonce for trashing the form.
     *
     * @return void
     */
    private function render_buttons_td_section( $form, $remove_form_nonce, $duplicate_form_nonce, $trash_form_nonce ) {
        ?>
                <td>
                    <button type="button" class="update-form button" data-action="<?php echo esc_attr( $this->prefix ); ?>remove_form_from_folder" data-form-id="<?php echo esc_attr( $form['id'] ); ?>" data-nonce="<?php echo esc_attr( $remove_form_nonce ); ?>">
                        Remove
                    </button>
                    <button type="button" class="update-form button" data-action="<?php echo esc_attr( $this->prefix ); ?>duplicate_form" data-form-id="<?php echo esc_attr( $form['id'] ); ?>" data-nonce="<?php echo esc_attr( $duplicate_form_nonce ); ?>">
                        Duplicate
                    </button>
                    <button type="button" class="update-form button" data-action="<?php echo esc_attr( $this->prefix ); ?>trash_form" data-form-id="<?php echo esc_attr( $form['id'] ); ?>" data-nonce="<?php echo esc_attr( $trash_form_nonce ); ?>">
                        Trash
                    </button>
                </td>
                <?php
	}

	/**
     * Conditionally renders a live preview link for a form if the GP_Live_Preview class is available.
     *
     * @param array $form The form array containing form data including the form ID.
     *
     * @return void
     */
	private function maybe_render_live_preview_link( array $form ) {
		if ( ! class_exists( 'GP_Live_Preview' ) ) {
			return;
		}
			$gp_live_preview = GP_Live_Preview::get_instance();
			$preview_url     = $gp_live_preview->add_options_to_url( $gp_live_preview->get_preview_url( $form['id'] ) );
		?>
			| <a href="<?php echo esc_url( $preview_url ); ?>" target="_blank">Live Preview</a>
			<?php
	}
    /**
     * Creates a dropdown of all views connected to the form or a create view link
     *
     * @param array $form The current form.
     *
     * @return void
     */
	private function maybe_render_connected_views_link( array $form ) {
			// Check if GravityView is active
		if ( ! class_exists( 'GVCommon' ) ) {
			return;
		}

			// Get connected views for this form
			$connected_views = GVCommon::get_connected_views( $form['id'], [ 'post_status' => 'any' ] );

			// If no connected views, show a link to create one
		if ( empty( $connected_views ) ) {
			?>
				| <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=gravityview&form_id=' . $form['id'] ) ); ?>" target="_blank">Create a View</a>
			<?php
			return;
		}

			// If there are connected views, show a dropdown
		?>
			<div class="dropdown">
				| <a href="#" class="link">Connected Views</a>
				<ul class="dropdown-menu">
					<?php
					foreach ( $connected_views as $view ) {
						$label = empty( $view->post_title ) ? sprintf( 'No Title (View #%d)', $view->ID ) : $view->post_title;
						?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . $view->ID ) ); ?>">
								<?php echo esc_html( $label ); ?>
							</a>
						</li>
						<?php
					}
					?>
					<li>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=gravityview&form_id=' . $form['id'] ) ); ?>">
							<span class="dashicons dashicons-plus"></span>
							Create a View
						</a>
					</li>
				</ul>
			</div>
		<?php
	}
}
