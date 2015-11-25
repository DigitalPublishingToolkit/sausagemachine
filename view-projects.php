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
				<a href="index.php?edit" class="tabnav-tab">Start a book</a>
				<a href="index.php?projects" class="tabnav-tab selected">Book projects</a>
				<a href="#" class="tabnav-tab">Tools</a>
			</nav>
		</div>
		<div class="columns">
			<div class="one-third column" style="position: relative;">
				<div style="width: 100%; height: 100%; background-color: white; position: absolute; z-index: 1;" onmouseover="this.style.visibility='hidden'; var that = this; setTimeout(function() { that.style.visibility='visible'; }, 1000);"></div>
				<iframe src="https://www.youtube.com/embed/oyXHA7xiChc?start=20" frameborder="0" style="width: 100%;"></iframe>
			</div>
			<div class="two-thirds column">
				<div class="flash" style="margin-bottom: 2em; display: none;">
					Your book project got created as <a href="" id="foo"></a> on Github. We will automagically update various output formats for you whenever you push to this repository.<br><br>
					<a id="bar" href="">Click here</a> to clone the repository to your own computer. We recommend the application <a href="http://macdown.uranusjr.com/">MacDown</a> for editing the Markdown files.
				</div>
				<div class="boxed-group">
					<h3>Repositories</h3>
					<ul>
						<li class="repo" style="display: none;">
							<p class="repo-name css-truncate"><a href="#">Foo</a></p>
							<p class="note">Last updated 20 minutes ago</p>
							<span class="counter">1 contributors</span>
							<!-- icon, read ebook -->
						</li>
					</ul>
				</div>
			</div>
		</div>
	</div>
	<script src="js/jquery-2.1.4.min.js"></script>
	<script>
		var getCookie = function getCookie(name) {
			var value = "; " + document.cookie;
			var parts = value.split("; " + name + "=");
			if (parts.length == 2) return parts.pop().split(";").shift();
		}

		$(document).ready(function() {
			/*
			if (getCookie('repo').length) {
				$('.flash').css('display', 'block');
				$('#foo').attr('href', 'http://github.com/' + unescape(getCookie('repo')));
				$('#foo').text(unescape(getCookie('repo')));
				$('#bar').attr('href', 'github-mac://openRepo/https://github.com/' + unescape(getCookie('repo')));
			}
			*/

			$.ajax('json.php?projects', {
				method: 'GET',
				dataType: 'json',
				success: function(data) {
					console.log(data);
					/*
					_.each(data, function(repository, key) {
						var bar = $('.repo').first().clone();
						bar.css('display', 'block');
						bar.find('.repo-name a').attr('href', 'http://github.com/' + key);
						bar.find('.repo-name a').text(repository.name);
						//var diff = Math.abs(new Date() - new Date(repository.updated*1000));
						//var d = new Date(diff);
						var d = new Date(repository.updated*1000);
						bar.find('.note').text('Last updated '+d);
						bar.find('.counter').text(repository.owner);
						$('.repo').parent().append(bar);
						console.log(repository);
						/*
						var option = document.createElement('option');
						option.innerHTML = recipe.description;
						document.getElementById('receipe-sel').appendChild(option);
						*/
					/*
					});
					*/
				}
			});
		});
	</script>
</body>
</html>
