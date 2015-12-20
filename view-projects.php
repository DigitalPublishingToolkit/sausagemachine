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
				<a href="index.php?about" class="tabnav-tab">About</a>
			</nav>
		</div>
		<div class="columns">
			<div class="one-fifth column" style="position: relative;">
				<div style="width: 100%; height: 100%; background-color: white; position: absolute; z-index: 1;" onmouseover="this.style.visibility='hidden'; var that = this; setTimeout(function() { that.style.visibility='visible'; }, 1000);"></div>
				<iframe src="https://www.youtube.com/embed/oyXHA7xiChc?start=20" frameborder="0" style="width: 100%;"></iframe>
			</div>
			<div class="four-fifths column">
				<div class="flash" style="margin-bottom: 2em; display: none;">
					Your book project got created as <a href="" id="github-repo" target="_blank"></a> on Github. We will automagically update various output formats for you whenever you push to this repository.<br><br>
					<a id="github-repo-clone" href="">Click here</a> to clone the repository to your own computer. We recommend the application <a href="http://macdown.uranusjr.com/" target="_blank">MacDown</a> for editing the Markdown files.
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
	<script src="js/sausagemachine.js"></script>
	<script>
		var getCookie = function getCookie(name) {
			var value = "; " + document.cookie;
			var parts = value.split("; " + name + "=");
			if (parts.length == 2) return parts.pop().split(";").shift();
		}

		$(document).ready(function() {
			if (sessionStorage.getItem('github_repo')) {
				$('.flash').css('display', 'block');
				$('#github-repo').attr('href', 'http://github.com/' + sessionStorage.getItem('github_repo'));
				$('#github-repo').text(sessionStorage.getItem('github_repo'));
				$('#github-repo-clone').attr('href', 'github-mac://openRepo/https://github.com/' + sessionStorage.getItem('github_repo'));
				sessionStorage.removeItem('github_repo');
			}

			$.sausagemachine.get_projects(function(data) {
				// sort by "updated"
				data.sort(function(a, b){
					if(a.updated < b.updated) return 1;
					if(a.updated > b.updated) return -1;
					return 0;
				});

				$.each(data, function() {
					var li = $('.repo').first().clone();
					li.css('display', 'block');
					li.find('.repo-name a').attr('href', 'http://github.com/' + this.github_repo);
					li.find('.repo-name a').text(this.github_repo);
					var d = new Date(this.updated*1000);
					li.find('.note').text('Last updated '+d);
					$('.repo').parent().append(li);
				});
			});
		});
	</script>
</body>
</html>
