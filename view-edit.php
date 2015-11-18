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
				<a href="index.php?import" class="tabnav-tab">Import file(s)</a>
				<a href="index.php?edit" class="tabnav-tab selected">Start a book</a>
				<a href="index.php?projects" class="tabnav-tab">Book projects</a>
				<a href="#" class="tabnav-tab">Tools</a>
			</nav>
		</div>
		<div class="columns">
			<div class="one-half column">
				<textarea id="markdown" placeholder="Enter Markdown here"></textarea>
			</div>
			<div class="one-half column">
				<div class="result blankslate">
					Rendered Markdown
				</div>
				<div>
					<label><span class="tooltipped tooltipped-n" aria-label="Select one of our repositories to base your work on">Template</span></label>
					<select id="repo-sel"></select>
				</div>
				<div>
					<label><span class="tooltipped tooltipped-n" aria-label="Which format do you want to convert the text to">Output</span></label>
					<select id="target-sel"></select>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<button type="button" id="btn-convert" class="btn btn-primary">Convert</button>
			<button type="button" id="btn-project" class="btn">Create project</button>
		</div>
	</div>
	<script src="js/jquery-2.1.4.min.js"></script>
	<script>
		$(document).ready(function() {

			var hash = window.location.hash.substring(1);
			if (hash != sessionStorage.getItem('tmp_key')) {
				sessionStorage.clear();
				sessionStorage.setItem('tmp_key', hash);
			}

			if (sessionStorage.markdown) {
				document.getElementById('markdown').innerHTML = sessionStorage.markdown;
			}

			var updateRepos = function() {
				$.ajax('json.php?repos', {
					method: 'GET',
					dataType: 'json',
					success: function(data) {
						$.each(data, function() {
							var option = document.createElement('option');
							option.innerHTML = this.description;
							option.value = this.repo;
							if (this.default) {
								option.selected = true;
							}
							document.getElementById('repo-sel').appendChild(option);
							updateTargets(this.repo);
						});
					}
				});
			};

			var updateTargets = function(repo) {
				$.ajax('json.php?targets&repo='+repo, {
					method: 'GET',
					dataType: 'json',
					success: function(data) {
						document.getElementById('target-sel').innerHTML = '';
						$.each(data, function() {
							var option = document.createElement('option');
							option.innerHTML = this.description;
							option.value = this.target;
							if (this.default) {
								option.selected = true;
							}
							document.getElementById('target-sel').appendChild(option);
						});
					}
				});
			};

			var select = document.getElementById('repo-sel');
			select.addEventListener('change', function(e) {
				if (this.selectedIndex == -1) {
					updateTargets(this[this.selectedIndex].value);
				}
			});

			updateRepos();


			// taken from http://stackoverflow.com/questions/16245767/creating-a-blob-from-a-base64-string-in-javascript
			function b64toBlob(b64Data, contentType, sliceSize) {
			    contentType = contentType || '';
			    sliceSize = sliceSize || 512;
			    var byteCharacters = atob(b64Data);
			    var byteArrays = [];
			    for (var offset = 0; offset < byteCharacters.length; offset += sliceSize) {
			        var slice = byteCharacters.slice(offset, offset + sliceSize);
			        var byteNumbers = new Array(slice.length);
			        for (var i = 0; i < slice.length; i++) {
			            byteNumbers[i] = slice.charCodeAt(i);
			        }
			        var byteArray = new Uint8Array(byteNumbers);
			        byteArrays.push(byteArray);
			    }
			    var blob = new Blob(byteArrays, {type: contentType});
			    return blob;
			}


			var convert = document.getElementById('btn-convert');
			convert.addEventListener('click', function(e) {
				var repo = document.getElementById('repo-sel').value;
				var target = document.getElementById('target-sel').value;
				$.ajax('json.php?convert', {
					method: 'POST',
					data: {
						'tmp_key': sessionStorage.getItem('tmp_key'),
						'repo': repo,
						'target': target,
						'files': {
							'md/seed.md': document.getElementById('markdown').value
						}
					},
					success: function(data) {
						sessionStorage.setItem('tmp_key', data.tmp_key);
						window.location.hash = '#' + data.tmp_key;

						$.ajax('json.php?files', {
							method: 'GET',
							data: {
								'tmp_key': sessionStorage.getItem('tmp_key'),
								'files': data.generated
							},
							success: function(data) {
								console.log(data);
							}
						});
						/*
						var blob = b64toBlob(data.data, data.mime);
						console.log(blob);
						var blobUrl = URL.createObjectURL(blob);
						console.log(blobUrl);

						var a = $('<a></a>');
						a.attr('href', blobUrl);
						a.attr('download', data.fn);
						a.text('download');

						$('.result').html(a);
						*/
						// XXX: binary data?
						/*
						var mime = xhr.getResponseHeader('content-type');
						if (mime == 'text/html; charset=utf-8') {
							$('.result').html(data);							
						} else {
							$('.result').text(data);
						}
						$('.result').css('text-align', 'left');
						console.log(data);
						console.log(mime);
						*/

					}
				});
			});

			var project = document.getElementById('btn-project');
			project.addEventListener('click', function(e) {
				var recipe = document.getElementById('repo-sel').value;
				var target = document.getElementById('target-sel').value;
				var repo = prompt('Name the repository');
				$.ajax('json.php?create_project', {
					method: 'POST',
					data: {
						'recipe': recipe,
						'target': target,
						'repo': repo,
						'markdown': document.getElementById('markdown').value
					},
					dataType: 'json',
					success: function(data, textStatus, xhr) {
						if (data.url) {
							window.location = data.url;
						}
					}
				});
			});
		});
	</script>
</body>
</html>
