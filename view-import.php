<!DOCTYPE html>
<html lang="en">
<head>
	<title>The Sausage Machine</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="css/primer.css">
	<link rel="stylesheet" href="css/sausagemachine.css">
	<link rel="icon" href="favicon.ico">
</head>
<body>
	<div class="container">
		<div class="tabnav">
			<nav class="tabnav-tabs">
				<a href="index.php?import" class="tabnav-tab selected">Import file(s)</a>
				<a href="index.php?edit" id="edit-link" class="tabnav-tab">Start a book</a>
				<a href="index.php?projects" class="tabnav-tab">Book projects</a>
				<a href="index.php?about" class="tabnav-tab">About</a>
			</nav>
		</div>
		<div class="columns">
			<div class="one-half column centered">
				<div class="blankslate" id="upload">
					Drop files here (e.g. Word documents)
				</div>
			</div>
		</div?
	</div>
	<script src="js/jquery-2.1.4.min.js"></script>
	<script src="js/sausagemachine.js"></script>
	<script>
		var upload = $('#upload');

		$(upload).on('dragover', function(e) {
			$(this).addClass('upload-hovering');
			return false;
		});

		$(upload).on('dragleave', function(e) {
			$(this).removeClass('upload-hovering');
			return false;
		});

		$(upload).on('drop', function(e) {
			$(this).removeClass('upload-hovering');

			// dropped files need to be read immediately
			var formData = new FormData();
			for (var i=0; i < e.originalEvent.dataTransfer.files.length; i++) {
				formData.append('uploads', e.originalEvent.dataTransfer.files[i]);
			}

			var on_files_uploaded = function(data) {
				// redirect to edit
				window.location = 'index.php?edit#' + $.sausagemachine._get('temp');
			};

			var upload_files = function() {
				var xhr = new XMLHttpRequest();
				xhr.onload = on_files_uploaded;
				xhr.open('POST', 'api.php?temps/files/upload/' + $.sausagemachine._get('temp'));
				xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
				xhr.send(formData);
			};

			var on_temp_crated = function(data) {
				$.sausagemachine._clear();
				$.sausagemachine._set('temp', data.temp);
				$.sausagemachine._set('repo', data.repo);
				// upload dropped files to newly created temp
				upload_files();
			};

			if ($.sausagemachine._get('temp')) {
				upload_files();
				console.log('a');
			} else {
				$.sausagemachine.create_temp({}, on_temp_crated);
			}
			return false;
		});

		// make it possible to get back to edit without looking temp
		if ($.sausagemachine._get('temp')) {
			$('#edit-link').attr('href', $('#edit-link').attr('href') + '#' + $.sausagemachine._get('temp'));
		}
	</script>
</body>
</html>
