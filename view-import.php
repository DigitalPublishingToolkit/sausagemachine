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
				<a href="index.php?edit" class="tabnav-tab">Start a book</a>
				<a href="index.php?projects" class="tabnav-tab">Book projects</a>
				<a href="#" class="tabnav-tab">Tools</a>
			</nav>
		</div>
		<div class="columns">
			<div class="one-half column">
				<div class="blankslate" id="upload">
					Drop here
				</div>
			</div>
		</div?
	</div>
	<script src="js/jquery-2.1.4.min.js"></script>
	<script>
		$(document).ready(function() {
			var upload = document.getElementById('upload');

			upload.addEventListener('dragenter', function(e) {
				$(this).addClass('upload-hovering');
				e.stopPropagation();
				e.preventDefault();
			}, false);
			upload.addEventListener('dragover', function(e) {
				e.stopPropagation();
				e.preventDefault();
			}, false);
			upload.addEventListener('dragleave', function(e) {
				$(this).removeClass('upload-hovering');
			}, false);
			upload.addEventListener('drop', function(e) {
				e.stopPropagation();
				e.preventDefault();
				var xhr = new XMLHttpRequest();
				var formData = new FormData();
				xhr.onload = function(e) {
					var json = JSON.parse(this.response);
					sessionStorage.tmp_key = json.tmp_key;
					sessionStorage.repo = json.repo;
					sessionStorage.uploaded = json.uploaded;
					sessionStorage.generated = json.generated;
					sessionStorage.files = json.files;
					// convert to Markdown
					$.ajax('json.php?convert', {
						method: 'POST',
						data: {
							'tmp_key': json.tmp_key,
							'target': 'html'
						},
						success: function(data) {
							var toFetch;
							// look for generated Markdown files first
							for (var i=0; i < data.generated.length; i++) {
								var ext = data.generated[i].substring(data.generated[i].length-3).toLowerCase();
								if (ext == '.md') {
									toFetch = data.generated[i];
									break;
								}
							}
							// and for uploaded ones second
							if (!toFetch) {
								for (var i=0; i < data.files.length; i++) {
									var ext = data.files[i].substring(data.files[i].length-3).toLowerCase();
									if (ext == '.md') {
										toFetch = data.files[i];
										break;
									}
								}
							}
							if (toFetch) {
								$.ajax('json.php?files', {
									method: 'GET',
									data: {
										'tmp_key': sessionStorage.getItem('tmp_key'),
										'files': [toFetch]
									},
									success: function(data) {
										for (var first in data) break;
										if (first) {
											// save markdown in session storage
											sessionStorage.setItem('markdown', window.atob(data[first].data));
										}
										// redirect
										window.location = 'index.php?edit#' + sessionStorage.tmp_key;
									}
								});
							} else {
								// redirect instantly
								window.location = 'index.php?edit#' + sessionStorage.tmp_key;
							}
						}
					});
				};
				xhr.open('POST', 'json.php?upload_files');
				xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
				for (var i=0; i < e.dataTransfer.files.length; i++) {
					formData.append('uploads', e.dataTransfer.files[i]);
				}
				xhr.send(formData);
			}, false);
		});
	</script>
</body>
</html>
