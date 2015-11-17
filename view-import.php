<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="css/primer.css">
	<link rel="icon" href="favicon.ico">
</head>
<body>
	<div class="container">
		<div class="tabnav">
			<nav class="tabnav-tabs">
				<a href="#" class="tabnav-tab selected">Import file(s)</a>
				<a href="view1.html" class="tabnav-tab">Start a book</a>
				<a href="view2.html" class="tabnav-tab">Book projects</a>
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
				e.stopPropagation();
				e.preventDefault();
			}, false);
			upload.addEventListener('dragover', function(e) {
				e.stopPropagation();
				e.preventDefault();
			}, false);
			upload.addEventListener('drop', function(e) {
				e.stopPropagation();
				e.preventDefault();
				var xhr = new XMLHttpRequest();
				var formData = new FormData();
				xhr.onload = function(e) {
					var json = JSON.parse(this.response);
					console.log(json);
					// store the markdown for now
					//sessionStorage.markdown = json.markdown;
					//window.location = 'view1.html';
				};
				xhr.open('POST', 'json.php?convert_files');
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
