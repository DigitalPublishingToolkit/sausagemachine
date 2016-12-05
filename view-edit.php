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
				<a href="index.php?import" id="import-link" class="tabnav-tab">Import file(s)</a>
				<a href="index.php?edit" id="edit-link" class="tabnav-tab selected">Start a book</a>
				<a href="index.php?projects" class="tabnav-tab">Archive</a>
				<a href="index.php?about" class="tabnav-tab">About</a>
			</nav>
		</div>
		<div class="columns">
			<div class="one-fifth column">
				<ol id="files">
					&nbsp;
				</ol>
			</div>
			<div class="four-fifths column">
				<div id="textarea-and-result">
					<div id="result">
						<p id="result-header">click to close</p>
						<div id="result-content">
						</div>
					</div>
					<textarea id="markdown" placeholder="Enter Markdown here"></textarea>
				</div>
				<div class="options-line">
					<label><span class="tooltipped tooltipped-n" aria-label="Select one of our repositories to base your work on">Template</span></label>
					<select id="repo-sel"></select>
					<label><span class="tooltipped tooltipped-nw" aria-label="Which format do you want to convert the text to">Output</span></label>
					<select id="target-sel"></select>
				</div>
				<div class="options-line">
					<button type="button" id="btn-project" class="btn" disabled="disabled">Log into GitHub</button>
					<button type="button" id="btn-convert" class="btn btn-primary">Save</button>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Start Activity Indicator -->
	<div id="activityIndicator">
		<div class="annimation"><img src="img/ring-alt.svg"></div>
		<div class="text">
			<h3 class="header">Loading</h2>
			<p class="details">. . .</p>
			<p class="cancel"><a href="#">Cancel</a></p>
		</div>
	</div>
	<script>
		$('#activityIndicator .cancel a').on('click', function(){
			$('#activityIndicator').fadeOut();
			return false;
		});
	</script>
	<!-- End Activity Indicator -->
	
	<script src="js/jquery-2.1.4.min.js"></script>
	<script src="js/sausagemachine.js"></script>
	<script>
		var convert = function() {
			// upload current file to temporary repository
			var fn = $('#files .file-selected').first().text();
			var md = $('#markdown').val();
			var files = [{
				fn: fn,
				data: btoa(unescape(encodeURIComponent(md)))
			}];
			$.sausagemachine.update_temp_files($.sausagemachine._get('temp'), files, function(data) {
				// mark file as saved
				$('#markdown').data('changed', null);
				// run Makefile
				$.sausagemachine.make_temp($.sausagemachine._get('temp'), $('#target-sel').val(), function(data) {
					if (data.error) {
						// show error messages
						var error = $('<div class="flash flash-error"> \
										There was a problem executing the template\'s Makefile. \
										The process returned error code ' + data.error + '. \
										You might want to contact the autor of the <a href="' + $.sausagemachine._get('repo') + '" target="_blank">template</a> for more information. \
										<br><br>\
										Output:<br>\
										</div>');
						for (var i=0; i < data.out.length; i++) {
							$(error).append(data.out[i] + '<br>');
						}
						$('#result-content').html(error);
					} else {
						// determine which file to show
						var html = null;
						for (var i=0; i < data.modified.length; i++) {
							var fn = data.modified[i];
							if (fn.lastIndexOf('.') !== -1) {
								var ext = fn.substring(fn.lastIndexOf('.')+1);
							} else {
								var ext = '';
							}
							if (ext == 'html') {
								html = fn;
								break;
							}
						}
						// if the conversion returned an HTML file, we display it in an iFrame
						// else offer all modified files for download
						if (html) {
							var iframe = $('<iframe src="api.php?temps/files/' + $.sausagemachine._get('temp') + '/' + html + '"></iframe>');
							$('#result-content').html(iframe);
						} else {
							$('#result-content').html('<div id="result-content-files"></div>');
							for (var i=0; i < data.modified.length; i++) {
								var fn = data.modified[i];
								var btn = $('<p><a class="btn" role="button" href="api.php?temps/files/' + $.sausagemachine._get('temp') + '/' + fn + '" download="' + fn + '">' + fn + '</a></p>');
								$('#result-content-files').append(btn);
							}
						}
						// XXX (later): update list of files on the left?
					}
					$('#result').show({duration: 'fast'});
				});
			});
		};

		var load_file = function(fn) {
			var on_file_loaded = function(data) {
				$('#markdown').val(data);
			}

			// we either load a file from the template or the temp repository, depending on where we are
			if ($.sausagemachine._get('temp')) {
				$.sausagemachine.get_temp_file($.sausagemachine._get('temp'), fn, on_file_loaded);
			} else {
				$.sausagemachine.get_repo_file($.sausagemachine._get('repo'), fn, on_file_loaded);
			}
		};

		var load_files = function(repo) {
			var on_files_loaded = function(files) {
				// success, populate file menu
				$('#files').html('');
				for (var i=0; i < files.length; i++) {
					var fn = files[i];
					var pos = fn.lastIndexOf('.');
					if (pos != -1) {
						var ext = fn.substring(pos+1);
					} else {
						var ext = '';
					}
					if (fn.substr(0, 3) == 'md/' && ext == 'md') {
						var li = $('<li class="file-md">' + fn + '</li>');
					}
					if (ext == 'gif' || ext == 'jpg' || ext == 'jpeg' || ext == 'png') {
						var li = $('<li class="file-img"><span class="tooltipped tooltipped-se" aria-label="Images can be referenced in Markdown documents">' + fn + '</span></li>');
					}
					if (fn == 'Makefile' || fn == 'makefile') {
						var li = $('<li class="file-makefile"><span class="tooltipped tooltipped-se" aria-label="Makefiles contain the recipes for creating books">' + fn + '</span></li>');
					}
					if (ext == 'css') {
						var li = $('<li class="file-css"><span class="tooltipped tooltipped-se" aria-label="CSS files determine the look for various output formats">' + fn + '</span></li>');
					}
					$('#files').append(li);
				}
				if ($('.file-md').length == 0) {
					// if there is nothing else, add a dummy seed entry
					var li = $('<li class="file-md file-new">md/seed.md</li>');
					$('#files').append(li);
				}
				$('#files .file-md').first().each(function() {
					$(this).addClass('file-selected');
					if (!$(this).is('.file-new')) {
						load_file($(this).text());
					} else {
						$('#markdown').val('');
					}
				});
			};

			var on_files_error = function() {
				$('#files').html('');
			};

			if ($.sausagemachine._get('repo') === repo && $.sausagemachine._get('temp')) {
				// if we have a temporary repository, use this one
				$.sausagemachine.get_temp_files($.sausagemachine._get('temp'), on_files_loaded, on_files_error);
			} else {
				// else list files in the template
				$.sausagemachine.get_repo_files(repo, on_files_loaded, on_files_error);
			}
		};

		var load_targets = function(repo) {
			$.sausagemachine.get_targets(repo, function(targets) {
				// success, populate target dropdown
				$('#target-sel').html('');
				$.each(targets, function() {
					var option = $('<option value="' + this.target + '">' + this.description + '</option>');
					if (this.default) {
						$(option).attr('selected', 'selected');
					}
					$('#target-sel').append(option);
				});
			}, function() {
				// error
				$('#target-sel').html('');
			});
		};

		var on_repos_loaded = function(repos) {
			var repo = $.sausagemachine._get('repo');
			$.each(repos, function() {
				// populate repo dropdown
				var option = $('<option value="' + this.repo + '">' + this.description + '</option>');
				if (repo && repo === this.repo) {
					// select the current temp's repo
					option.attr('selected', 'selected');
				} else if (!repo && this.default) {
					// else the global default
					option.attr('selected', 'selected');
					// this also sets repo in sessionStorage
					$.sausagemachine._set('repo', this.repo);
				}
				$('#repo-sel').append(option);
			});
			// refresh files and targets
			load_files($.sausagemachine._get('repo'));
			load_targets($.sausagemachine._get('repo'));
		};

		var load_temp = function(temp) {
			$.sausagemachine.get_temp(temp, function(data) {
				// success, update state
				$.sausagemachine._set('temp', temp);
				$.sausagemachine._set('repo', data.repo);
			}, function() {
				// error, most likely invalid temp
				// continue with loading the repos
				window.location.hash = '';
				$.sausagemachine.get_repos(on_repos_loaded);
			});
		};

		/* entry point */
		var hash = window.location.hash.substring(1);
		if (hash.length) {
			// update the tab links accordingly
			// XXX (later): find a more elegant solution, also: but when he hash is invalid, and we save later
			$('#import-link').attr('href', $('#import-link').attr('href') + '#' + hash);
			$('#edit-link').attr('href', $('#edit-link').attr('href') + '#' + hash);
		}
		if (hash !== $.sausagemachine._get('temp')) {
			// delete state
			$.sausagemachine._clear();
			if (hash.length) {
				// set sessionStorage based on hash
				load_temp(hash);
			} else {
				// no temp yet, load repos next
				$.sausagemachine.get_repos(on_repos_loaded);
			}
		} else {
			// sessionStorage is up to date, skip temp loading and continue with loading the repos
			$.sausagemachine.get_repos(on_repos_loaded);
		}

		// enable the GitHub button if we have a temporary repository
		if ($.sausagemachine._get('temp')) {
			$('#btn-project').removeAttr('disabled');
			$('#btn-convert').text('Update');
		}

		$('#repo-sel').on('change', function() {
			// prompt for confirmation before overwriting changes
			if ($('#markdown').data('changed')) {
				if (!confirm('You have unsaved changes. Continue?')) {
					$(this).val($.sausagemachine._get('repo'));
					return;
				} else {
					$('#markdown').data('changed', null);
				}
			}

			$.sausagemachine._set('repo', $(this).val());
			if ($.sausagemachine._get('temp')) {
				// migrate existing repo to selected one
				$.sausagemachine.switch_repo($.sausagemachine._get('temp'), $.sausagemachine._get('repo'), function(data) {
					// refresh files and targets
					load_files($.sausagemachine._get('repo'));
					load_targets($.sausagemachine._get('repo'));
				});
			} else {
				// refresh files and targets
				load_files($.sausagemachine._get('repo'));
				load_targets($.sausagemachine._get('repo'));
			}
		});

		$('#btn-convert').on('click', function() {
			// make sure we have a temporary repository, then upload & convert
			if (!$.sausagemachine._get('temp')) {
				$.sausagemachine.create_temp({
					repo: $.sausagemachine._get('repo')
				}, function(temp) {
					$.sausagemachine._set('temp', temp.temp);
					// also update URL
					window.location.hash = '#' + temp.temp;
					// update the tab links accordingly
					$('#import-link').attr('href', $('#import-link').attr('href') + '#' + temp.temp);
					$('#edit-link').attr('href', $('#edit-link').attr('href') + '#' + temp.temp);
					$('#btn-project').removeAttr('disabled');
					$('#btn-convert').text('Update');
					convert();
				});
			} else {
				convert();
			}
		});

		$('#result').on('click', function(e) {
			// don't hide overlay when clicking a download link
			if ($(e.target).parents('#result-content').length) {
				return;
			}
			$(this).hide({duration: 'fast'});
		});

		$('#markdown').on('change', function(e) {
			// track when the textfield got edited
			$(this).data('changed', true);
		});

		$('#files').on('click', 'li', function(e) {
			// get the file's extension
			var fn = $(this).text();
			var pos = fn.lastIndexOf('.');
			if (pos != -1) {
				var ext = fn.substring(pos+1);
			} else {
				var ext = '';
			}
			// special case for images, those don't ever get "selected" per se
			if (ext == 'gif' || ext == 'jpg' || ext == 'jpeg' || ext == 'png') {
				// add to textarea
				var text = $('#markdown').val();
				var cursor = $('#markdown')[0].selectionStart;
				var toInsert = '![](' + fn + ')';
				$('#markdown').val(text.substring(0, cursor) + toInsert + text.substring(cursor));
				return;
			}

			// prompt for confirmation before overwriting changes
			if ($('#markdown').data('changed')) {
				if (!confirm('You have unsaved changes. Continue?')) {
					return;
				} else {
					$('#markdown').data('changed', null);
				}
			}
			// special case for new files
			if ($(this).is('.file-new')) {
				$('#markdown').val('');
			} else {
				load_file($(this).text());
			}
			// switch selection
			$('.file-selected').removeClass('file-selected');
			$(this).addClass('file-selected');
		});

		// helper function, move
		var getCookieValue = function(name) {
			var ret = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
			return ret ? ret.pop() : null;
		}

		// set sessionStorage according to cookie, if set
		var github_access_token = getCookieValue('github_access_token');
		if (github_access_token) {
			$.sausagemachine._set('github_access_token', github_access_token);
			$('#btn-project').text('Continue on GitHub');
		}

		$('#btn-project').on('click', function(e) {
			// prompt for confirmation before overwriting changes
			if ($('#markdown').data('changed')) {
				if (!confirm('You have unsaved changes. Continue?')) {
					return;
				} else {
					$('#markdown').data('changed', null);
				}
			}

			if (!$.sausagemachine._get('github_access_token')) {
				// log into GitHub (will navigate away)
				$.ajax('github.php?auth', {
					method: 'get',
					data: {
						'target': window.location.href
					},
					dataType: 'json',
					success: function(data) {
						window.location = data;
					}
				});
			} else {
				// create a repository on the use's GitHub account
				var github_repo_name = prompt("Name of the repository");
				if (!github_repo_name) {
					return;
				}
				$.ajax('github.php?repo', {
					method: 'POST',
					data: {
						'temp': $.sausagemachine._get('temp'),
						'github_access_token': $.sausagemachine._get('github_access_token'),
						'github_repo_name': github_repo_name
					},
					dataType: 'json',
					error: function(jqXHR, textStatus, errorThrown) {
						alert(jqXHR.responseText);
					},
					success: function(data) {
						if (data.length) {
							$.sausagemachine._clear();
							window.location = 'index.php?projects#' + data;
						}
					}
				});
			}
		});
	</script>
</body>
</html>
