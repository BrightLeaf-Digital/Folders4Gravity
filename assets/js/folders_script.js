jQuery(($) => {
	$('h2.nav-tab-wrapper').show();
	const makeAjaxRequest = function (body) {
		$('html, body').css('cursor', 'wait');
		$.post(ajaxurl, body)
			.done(function (response) {
				if (response.success) {
					window.location.reload();
				} else {
					console.error('Failed:', response.data || 'Unknown error');
				}
			})
			.fail(function (xhr, status, error) {
				console.error('AJAX error:', error);
			})
			.always(function () {
				// Restore default cursor
				$('html, body').css('cursor', 'default');
			});
	};

	$('.dropdown').hover(
		function () {
			$(this).find('.dropdown-menu').stop(true, true).fadeIn(200);
		},
		function () {
			$(this).find('.dropdown-menu').stop(true, true).fadeOut(200);
		}
	);

	$('#rename-folder-form').on('submit', function (e) {
		e.preventDefault();

		const action = 'go_f4g_rename_folder';
		const folderID = $(this).find('[name="folder_id"]').val();
		const folderName = $(this).find('[name="folder_name"]').val();
		const nonce = $(this).find('[name="nonce"]').val();

		makeAjaxRequest({
			action,
			folderID,
			folderName,
			nonce,
		});
	});

	$('#assign-forms-form').on('submit', function (e) {
		e.preventDefault();

		const action = 'go_f4g_assign_forms_to_folder';
		const folderID = $(this).find('[name="folder_id"]').val();
		const formIDs = $(this).find('[name="form_ids[]"]').val();
		const nonce = $(this).find('[name="nonce"]').val();

		makeAjaxRequest({
			action,
			formIDs,
			folderID,
			nonce,
		});
	});

	$('.update-form').on('click', function () {
		const action = $(this).data('action');
		const formID = $(this).data('form-id');
		const nonce = $(this).data('nonce');

		// Show confirmation dialog for trash action
		if (action === 'go_f4g_trash_form') {
			if (!confirm('Are you sure you want to trash this form? This action can be undone from the Gravity Forms trash.')) {
				return;
			}
		}

		const urlParams = new URLSearchParams(window.location.search);
		const folderID = urlParams.get('folder_id'); // could be null

		makeAjaxRequest({
			action,
			formID,
			nonce,
			folderID,
		});
	});

	$('.copyable').on('click', function () {
		const $el = $(this);
		navigator.clipboard
			.writeText($el.html())
			.then(() => {
				$el.css('background-color', '#d4edda'); // success green
				setTimeout(() => {
					$el.css('background-color', '');
				}, 1000);
			})
			.catch((err) => {
				console.error('Clipboard copy failed:', err);
			});
	});

	$('#create-folder-form').on('submit', function (e) {
		e.preventDefault();

		const action = 'go_f4g_create_folder';
		const folderName = $(this).find('[name="folder_name"]').val();
		const nonce = $(this).find('[name="nonce"]').val();

		makeAjaxRequest({
			action,
			folderName,
			nonce,
		});
	});

	$('.delete-folder-button').on('click', function () {
		const action = 'go_f4g_delete_folder';
		const folderID = $(this).data('folder-id');
		const nonce = $(this).data('nonce');
		makeAjaxRequest({
			action,
			folderID,
			nonce,
		});
	});
	if (typeof FOLDERS4GRAVITY !== 'undefined') {
		const tableBody = document.querySelector('.sortable-forms');

		if (tableBody) {
			Sortable.create(tableBody, {
				animation: 150,
				handle: '.drag-handle',
				onEnd: () => {
					const order = Array.from(
						tableBody.querySelectorAll('tr')
					).map((row) => row.dataset.formId);

					$('html, body').css('cursor', 'wait');
					$.post(ajaxurl, {
						action: 'go_f4g_save_form_order',
						folder_id: FOLDERS4GRAVITY.folder_id,
						nonce: FOLDERS4GRAVITY.nonce,
						order,
					})
						.done((response) => {
							if (!response.success) {
								console.error(
									'Failed to save order:',
									response.data || 'Unknown error'
								);
							}
						})
						.fail((xhr, status, error) => {
							console.error(
								'AJAX error while saving order:',
								error
							);
						})
						.always(() => {
							$('html, body').css('cursor', 'default');
						});
				},
			});
		}
	}

	// Folder ordering functionality
	if (typeof FOLDERS4GRAVITY_FOLDER_ORDER !== 'undefined') {
		const folderList = document.querySelector('.gf-sortable-folders');

		if (folderList) {
			Sortable.create(folderList, {
				animation: 150,
				handle: '.gf-drag-handle',
				onEnd: () => {
					const order = Array.from(
						folderList.querySelectorAll('.gf-folder-item')
					).map((item) => item.dataset.folderId);

					$('html, body').css('cursor', 'wait');
					$.post(ajaxurl, {
						action: 'go_f4g_save_folder_order',
						nonce: FOLDERS4GRAVITY_FOLDER_ORDER.nonce,
						order,
					})
						.done((response) => {
							if (!response.success) {
								console.error(
									'Failed to save folder order:',
									response.data || 'Unknown error'
								);
							}
						})
						.fail((xhr, status, error) => {
							console.error(
								'AJAX error while saving folder order:',
								error
							);
						})
						.always(() => {
							$('html, body').css('cursor', 'default');
						});
				},
			});
		}
	}
});
