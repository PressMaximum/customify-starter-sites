<?php

defined( 'ABSPATH' ) || exit;

class Customify_Starter_Sites_WXR_Import_UI
{
	/**
	 * Should we fetch attachments?
	 *
	 * Set in {@see display_import_step}.
	 *
	 * @var bool
	 */
	protected $fetch_attachments = true;

	public $id = 0;
	public $authors = null;
	public $version = null;
	public $counts = array();

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->counts = array(
			'posts' => 0,
			'media' => 0,
			'users' => 0,
			'comments' => 0,
			'terms' => 0
		);
	}

	/**
	 * Get preliminary data for an import file.
	 *
	 * This is a quick pre-parse to verify the file and grab authors from it.
	 *
	 * @param int $id Media item ID.
	 * @return Customify_Starter_Sites_WXR_Import_Info|WP_Error Import info instance on success, error otherwise.
	 */
	public function get_data_for_attachment($id)
	{
		$source_url = get_post_meta($id, '_customify_starter_source_url', true);
		$file = get_attached_file($id);
		if (
			!$id
			|| !is_string($file)
			|| 'xml' !== strtolower(pathinfo($file, PATHINFO_EXTENSION))
			|| !Customify_Starter_Sites_Ajax::is_safe_remote_url($source_url)
		) {
			return new WP_Error('wxr_importer.invalid_source', __('Invalid starter content attachment.', 'customify-starter-sites'));
		}

		$existing = get_post_meta($id, '_wxr_import_info');
		if (!empty($existing)) {
			$data = $existing[0];
			$this->authors = $data->users;
			$this->version = $data->version;
			return $data;
		}

		$importer = $this->get_importer();
		$data = $importer->get_preliminary_information($file);
		if (is_wp_error($data)) {
			return $data;
		}

		// Cache the information on the upload
		if (!update_post_meta($id, '_wxr_import_info', $data)) {
			return new WP_Error(
				'wxr_importer.upload.failed_save_meta',
				__('Could not cache information on the import.', 'customify-starter-sites'),
				compact('id')
			);
		}

		$this->authors = $data->users;
		$this->version = $data->version;

		return $data;
	}

	public function import()
	{
		check_ajax_referer( 'customify_starter_sites', 'nonce' );
		$this->id = isset( $_REQUEST['id'] ) ? absint( wp_unslash( $_REQUEST['id'] ) ) : 0;
		$source_url = get_post_meta($this->id, '_customify_starter_source_url', true);
		$file = get_attached_file($this->id);
		if (
			!$this->id
			|| !is_string($file)
			|| 'xml' !== strtolower(pathinfo($file, PATHINFO_EXTENSION))
			|| !Customify_Starter_Sites_Ajax::is_safe_remote_url($source_url)
		) {
			wp_send_json_error(array('message' => __('Invalid starter content attachment.', 'customify-starter-sites')), 400);
		}
		// Download media files
		$this->fetch_attachments = true;
		$importer = $this->get_importer();

		// Are we allowed to create users?
		if (!$this->allow_create_users()) {
			add_filter('custstsi_wxr_importer.pre_process.user', '__return_null');
		}

		// Keep track of our progress
		add_action('custstsi_wxr_importer.processed.post', array($this, 'imported_post'), 10, 2);
		add_action('custstsi_wxr_importer.process_failed.post', array($this, 'imported_post'), 10, 2);
		add_action('custstsi_wxr_importer.process_already_imported.post', array($this, 'already_imported_post'), 10, 2);
		add_action('custstsi_wxr_importer.process_skipped.post', array($this, 'already_imported_post'), 10, 2);
		add_action('custstsi_wxr_importer.processed.comment', array($this, 'imported_comment'));
		add_action('custstsi_wxr_importer.process_already_imported.comment', array($this, 'imported_comment'));
		add_action('custstsi_wxr_importer.processed.term', array($this, 'imported_term'));
		add_action('custstsi_wxr_importer.process_failed.term', array($this, 'imported_term'));
		add_action('custstsi_wxr_importer.process_already_imported.term', array($this, 'imported_term'));
		add_action('custstsi_wxr_importer.processed.user', array($this, 'imported_user'));
		add_action('custstsi_wxr_importer.process_failed.user', array($this, 'imported_user'));
		add_filter('custstsi_wxr_importer.pre_process.post', array($this, 'sanitize_imported_post'), 10, 4);

		$err = $importer->import($file);
		if (is_wp_error($err)) {
			wp_send_json_error(array('message' => $err->get_error_message()), 400);
		}
		update_post_meta($this->id, '_wxr_importer_mapping', $importer->mapping);

		wp_send_json($this->counts);
	}

	/**
	 * Sanitize content received from the remote starter-site service before insertion.
	 *
	 * @param array $data Post data.
	 * @return array
	 */
	public function sanitize_imported_post($data)
	{
		if (!is_array($data)) {
			return array();
		}
		if (isset($data['post_title'])) {
			$data['post_title'] = sanitize_text_field($data['post_title']);
		}
		if (isset($data['post_content'])) {
			$data['post_content'] = wp_kses_post($data['post_content']);
		}
		if (isset($data['post_excerpt'])) {
			$data['post_excerpt'] = wp_kses_post($data['post_excerpt']);
		}

		return $data;
	}

	function re_mapping_thumbnails()
	{
	}

	/**
	 * Get the importer instance.
	 *
	 * @return Customify_Starter_Sites_WXR_Importer
	 */
	protected function get_importer()
	{
		$importer = new Customify_Starter_Sites_WXR_Importer($this->get_import_options());
		$logger = new Customify_Starter_Sites_Importer_Logger_ServerSentEvents();
		$importer->set_logger($logger);
		return $importer;
	}

	/**
	 * Get options for the importer.
	 *
	 * @return array Options to pass to Customify_Starter_Sites_WXR_Importer::__construct
	 */
	protected function get_import_options()
	{
		$options = array(
			'fetch_attachments' => $this->fetch_attachments,
			'default_author'    => get_current_user_id(),
		);

		/**
		 * Filter the importer options used in the admin UI.
		 *
		 * @param array $options Options to pass to Customify_Starter_Sites_WXR_Importer::__construct
		 */
		return apply_filters('custstsi_wxr_importer.admin.import_options', $options); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Backward-compatible WXR importer hook.
	}


	/**
	 * Decide whether or not the importer should attempt to download attachment files.
	 * Default is true, can be filtered via import_allow_fetch_attachments. The choice
	 * made at the import options screen must also be true, false here hides that checkbox.
	 *
	 * @return bool True if downloading attachments is allowed
	 */
	protected function allow_fetch_attachments()
	{
		return apply_filters('custstsi_import_allow_fetch_attachments', true); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Backward-compatible WordPress importer hook.
	}

	/**
	 * Decide whether or not the importer is allowed to create users.
	 * Default is true, can be filtered via import_allow_create_users
	 *
	 * @return bool True if creating users is allowed
	 */
	protected function allow_create_users()
	{
		return apply_filters('custstsi_import_allow_create_users', true); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Backward-compatible WordPress importer hook.
	}

	/**
	 * Get mapping data from request data.
	 *
	 * Parses form request data into an internally usable mapping format.
	 *
	 * @param array $args Raw (UNSLASHED) POST data to parse.
	 * @return array Map containing `mapping` and `slug_overrides` keys.
	 */
	protected function get_author_mapping($args)
	{
		if (!isset($args['imported_authors'])) {
			return array(
				'mapping'        => array(),
				'slug_overrides' => array(),
			);
		}

		$map        = isset($args['user_map']) ? (array) $args['user_map'] : array();
		$new_users  = isset($args['user_new']) ? $args['user_new'] : array();
		$old_ids    = isset($args['imported_author_ids']) ? (array) $args['imported_author_ids'] : array();

		// Store the actual map.
		$mapping = array();
		$slug_overrides = array();

		foreach ((array) $args['imported_authors'] as $i => $old_login) {
			$old_id = isset($old_ids[$i]) ? (int) $old_ids[$i] : false;

			if (!empty($map[$i])) {
				$user = get_user_by('id', (int) $map[$i]);

				if (isset($user->ID)) {
					$mapping[] = array(
						'old_slug' => $old_login,
						'old_id'   => $old_id,
						'new_id'   => $user->ID,
					);
				}
			} elseif (!empty($new_users[$i])) {
				if ($new_users[$i] !== $old_login) {
					$slug_overrides[$old_login] = $new_users[$i];
				}
			}
		}

		return compact('mapping', 'slug_overrides');
	}


	/**
	 * Send message when a post has been imported.
	 *
	 * @param int $id Post ID.
	 * @param array $data Post data saved to the DB.
	 */
	public function imported_post($id, $data)
	{
		if ($data['post_type'] ===  'attachment') {
			$this->counts['media']++;
		} else {
			$this->counts['posts']++;
		}
	}

	/**
	 * Send message when a post is marked as already imported.
	 *
	 * @param array $data Post data saved to the DB.
	 */
	public function already_imported_post($data)
	{
		if ($data['post_type'] ===  'attachment') {
			$this->counts['media']++;
		} else {
			$this->counts['posts']++;
		}
	}

	/**
	 * Send message when a comment has been imported.
	 */
	public function imported_comment()
	{
		$this->counts['comments']++;
	}

	/**
	 * Send message when a term has been imported.
	 */
	public function imported_term()
	{
		$this->counts['terms']++;
	}

	/**
	 * Send message when a user has been imported.
	 */
	public function imported_user()
	{
		$this->counts['users']++;
	}
}
