jQuery(($) => {
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

		const action = 'go_f4g_rename_view_folder';
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

	$('#assign-views-form').on('submit', function (e) {
		e.preventDefault();

		const action = 'go_f4g_assign_views_to_folder';
		const folderID = $(this).find('[name="folder_id"]').val();
		const viewIDs = $(this).find('[name="view_ids[]"]').val();
		const nonce = $(this).find('[name="nonce"]').val();

		makeAjaxRequest({
			action,
			viewIDs,
			folderID,
			nonce,
		});
	});

	$('.update-view').on('click', function () {
		const action = $(this).data('action');
		const viewID = $(this).data('view-id');
		const nonce = $(this).data('nonce');

		// Show confirmation dialog for trash action
		if (action === 'go_f4g_trash_view') {
			if (!confirm('Are you sure you want to trash this view? This action can be undone from the Gravity View trash.')) {
				return;
			}
		}

		const urlParams = new URLSearchParams(window.location.search);
		const folderID = urlParams.get('folder_id'); // could be null

		makeAjaxRequest({
			action,
			viewID,
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

		const action = 'go_f4g_create_view_folder';
		const folderName = $(this).find('[name="folder_name"]').val();
		const nonce = $(this).find('[name="nonce"]').val();

		makeAjaxRequest({
			action,
			folderName,
			nonce,
		});
	});

	$('.delete-folder-button').on('click', function () {
		const action = 'go_f4g_delete_view_folder';
		const folderID = $(this).data('folder-id');
		const nonce = $(this).data('nonce');
		makeAjaxRequest({
			action,
			folderID,
			nonce,
		});
	});

	if (typeof FOLDERS4GRAVITY !== 'undefined') {
		const tableBody = document.querySelector('.sortable-views');

		if (tableBody) {
			Sortable.create(tableBody, {
				animation: 150,
				handle: '.drag-handle',
				onEnd: () => {
					const order = Array.from(
						tableBody.querySelectorAll('tr')
					).map((row) => parseInt(row.dataset.viewId, 10));

					$('html, body').css('cursor', 'wait');

					$.post(ajaxurl, {
						action: 'go_f4g_save_view_order',
						folder_id: FOLDERS4GRAVITY.folder_id,
						nonce: FOLDERS4GRAVITY.nonce,
						order,
					})
						.done((res) => {
							if (!res.success) {
								console.error(
									'Failed to save view order:',
									res.data?.message || 'Unknown error'
								);
							}
						})
						.fail((xhr, status, error) => {
							console.error('AJAX error:', error);
						})
						.always(() => {
							$('html, body').css('cursor', 'default');
						});
				},
			});
		}
	}

	// Views folder ordering functionality
	if (typeof FOLDERS4GRAVITY_VIEWS_FOLDER_ORDER !== 'undefined') {
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
						action: 'go_f4g_save_views_folder_order',
						nonce: FOLDERS4GRAVITY_VIEWS_FOLDER_ORDER.nonce,
						order,
					})
						.done((response) => {
							if (!response.success) {
								console.error(
									'Failed to save views folder order:',
									response.data || 'Unknown error'
								);
							}
						})
						.fail((xhr, status, error) => {
							console.error(
								'AJAX error while saving views folder order:',
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
