<?php

use F4G\GravityOps\Core\Admin\ReviewPrompter;
use F4G\GravityOps\Core\Admin\SuiteMenu;
use F4G\GravityOps\Core\Admin\SurveyPrompter;
use F4G\GravityOps\Core\Utils\AssetHelper as Assets;

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

    // phpcs:enable PSR2.Classes.PropertyDeclaration.Underscore

    use F4G\GravityOps\Core\Traits\SingletonTrait;

    /**
     * The prefix to be used by the plugin. Gravity Ops-Folders4Gravity
     *
     * @var string
     */
	private $prefix = 'go_f4g_';

    /**
     * Utility for managing and loading assets.
     *
     * @var Assets
     */
    private $assets_helper;

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
	 * Initializes the class by adding necessary filters.
	 *
	 * @return void
	 */
	public function init() {

        $this->assets_helper = new Assets( plugins_url( '/', FOLDERS_4_GRAVITY_BASENAME ), dirname( __DIR__ ) );

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

        $this->register_form_folders_taxonomy();

        $review_prompter = new ReviewPrompter(
			$this->prefix,
			$this->_title,
			'https://wordpress.org/support/plugin/folders-4-gravity/reviews/#new-post'
		);
		$review_prompter->init();
		$review_prompter->maybe_show_review_request( $this->get_usage_count(), 10 );

        $survey_prompter = new SurveyPrompter(
			$this->prefix,
			$this->_title,
			$this->_version,
			'free'
		);
		$survey_prompter->init();
	}

    /**
     * Registers the menus used in the application.
     * This includes adding a top-level menu and submenus as needed.
     *
     * @return void
     */
    public function register_menus() {
        SuiteMenu::ensure_parent_menu();
        $cap_forms = current_user_can( 'gform_full_access' ) ? 'gform_full_access' : 'gravityforms_edit_forms';
        add_submenu_page(
            'gf_edit_forms',
            'Form Folders',
            'Form Folders',
            $cap_forms,
            $this->_slug,
            [ $this, 'form_folders_page' ]
        );
        $cap_ops = $cap_forms;
        add_submenu_page(
            'gravity_ops',
            $this->_short_title,
            $this->_short_title,
            $cap_ops,
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
        return SuiteMenu::get_icon();
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

		if ( ! current_user_can( 'gform_full_access' ) && ! current_user_can( 'gravityforms_edit_forms' ) ) {
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

		if ( ! current_user_can( 'gform_full_access' ) && ! current_user_can( 'gravityforms_edit_forms' ) ) {
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

		if ( ! current_user_can( 'gform_full_access' ) && ! current_user_can( 'gravityforms_edit_forms' ) ) {
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
			$this->assets_helper->build_style(
					'form-folders-styles',
					'/assets/css/folders_stylesheet.css',
					[ [ 'query' => 'page=' . $this->_slug ] ]
			),
			$this->assets_helper->build_style(
					'folders4formswidget',
					'/assets/css/dashboard-widget.css',
					[
						function () {
                            $s = get_current_screen();
							return is_admin() && $s && 'dashboard' === $s->id;
						},
					]
				),
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
			$this->assets_helper->build_script(
					'form-folders-scripts',
					'/assets/js/folders_script.js',
					[ 'jquery', 'sortable4folders' ],
					[ [ 'query' => 'page=' . $this->_slug ] ]
			),
			$this->assets_helper->build_script(
					'sortable4folders',
					'/assets/js/Sortable.min.js',
					[],
					[ [ 'query' => 'page=' . $this->_slug ] ]
			),
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

    /**
	 * Returns number of folders/views as usage proxy.
	 *
	 * @return int
	 */
	private function get_usage_count(): int {
		$count = 0;

		if ( taxonomy_exists( $this->taxonomy_name ) ) {
			$terms = wp_count_terms( $this->taxonomy_name );
			if ( ! is_wp_error( $terms ) ) {
				$count += (int) $terms;
			}
		}

		if ( taxonomy_exists( $this->view_taxonomy_name ) ) {
			$view_terms = wp_count_terms( $this->view_taxonomy_name );
			if ( ! is_wp_error( $view_terms ) ) {
				$count += (int) $view_terms;
			}
		}

		return $count;
	}
}
