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
				<a href="index.php?projects" class="tabnav-tab">Book projects</a>
				<a href="index.php?about" class="tabnav-tab selected">About</a>
			</nav>
		</div>
		<div class="columns">
			<div class="one-fifth column">
				<img id="logo" src="img/logo.svg" alt="Logo" title="Logo by Jess van Zyl">
			</div>
			<div class="four-fifths column">
				<div id="about" class="boxed-group">
					<h3>About</h3>
					<p>The Sausage Machine is an experimental system meant to facilitate hybrid text production. It builds upon the <a href="http://networkcultures.org/blog/publication/from-print-to-ebooks-a-hybrid-publishing-toolkit-for-the-arts/">Hybrid Publishing Toolkit</a> – an effort by a number of researchers and practitioners engaged in various forms of contemporary cross-media publishing. The Sausage Machine was designed and programmed for the <a href="http://publishinglab.org/">PublishingLab</a> by <a href="http://gottfriedhaider.com/">Gottfried Haider</a>.</p>
					<p>The code is <a href="http://github.com/DigitalPublishingToolkit/sausagemachine">available on GitHub</a> under an Open Source license, and can be made to run on most webservers. If you're running into any problems, feel free to <a href="https://github.com/DigitalPublishingToolkit/sausagemachine/issues/new">open an issue</a> on GitHub.</p>
					<p><strong>What is new?</strong> The Hybrid Publishing Toolkit made use of <a href="https://daringfireball.net/projects/markdown/">Markdown</a> (as a markup language), <a href="http://pandoc.org/">Pandoc</a> (as converter software), <a href="https://en.wikipedia.org/wiki/Makefile">Makefiles</a> (for specifying transformation rules) and <a href="https://en.wikipedia.org/wiki/Git_(software)">Git</a> (for distributed version control).</p>
					<p>The Sausage Machine continues to use those tools, but instead of making it necessary to install and invoke them on every user's machine, it is now possible to accomplish the most common tasks encountered in an editorial workflow using this web interface, further a client for Git, and a Markdown editor.</p>
					<p>The invocation of Makefiles, and creation of output files using Pandoc is now most commonly done on the server – enabling consistent results and a more accessible way to start experimenting.</p> 
					<p><strong>How does it work?</strong> In the <i>Import File</i> tab you can drop existing files (.docx Word documents, images) to include in your book project. In the following tab, <i>Start a book</i>, you can choose between multiple base templates to start from. There, you can also edit the various (text) files making up your repository, and also export to various output formats on the spot.</p>
					<p>The output formats provided and the exact manner how this transformation is done is governed by the the very Makefile that comes with the respective base template. The button <i>Continue on GitHub</i> then creates your personal copy of the selected template as a repository on GitHub, and commits your initial changes done in the web interface as well.</p>
					<p>Future work, by you as well as other people working on the same project, can be done using a conventional Git workflow. Except that, after each commit getting pushed to GitHub, the Sausage Machine will automatically go to work and re-generate all of the repository's output files. Those output files are again committed and pushed to GitHub, so that the user who pushed a change (e.g. a change to a Markdown file, or to the CSS stylesheet) can fetch an updated EPUB (and many other formats) just a few seconds later.</p>
					<p>Good luck.</p>
					<p><a href="http://github.com/gohai/">gohai</a>, December 2015</p>
				</div>
			</div>
		</div>
	</div>
	<script src="js/jquery-2.1.4.min.js"></script>
	<script>
	</script>
</body>
</html>
