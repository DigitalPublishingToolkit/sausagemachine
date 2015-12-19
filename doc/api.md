# Sausagemachine API

GET repos

	[
		{
			repo: "https://github.com/DigitalPublishingToolkit/template-test.git",
			description: "Default template",
			default: true
		}
	]

GET repos/files/:repo

	{
		files: [
			"book.html",
			"docx/hva_deliverables_2015_q4.docx",
			"Makefile"
		]
	}

GET repos/targets/:repo

	[
		{
			target: "icmls",
			description: "ICML files for use with Adobe InDesign",
			default: true
		}
	]

GET temps

	[
		"14486397022961",
		"14486399266785"
	]

POST temps/create

	param: repo (default: default_repo config option)

	{
		temp: "14486397022961",
		repo: "https://github.com/DigitalPublishingToolkit/template-test.git",
		branch: "master",
		commit: "4396da7ace5685b2a9b550155ce1c18b0125bb71",
		files: [
			"book.html",
			"docx/hva_deliverables_2015_q4.docx"
		]
	}

	... files: all files

GET temps/:temp

	{
		temp: "14486397022961",
		repo: "https://github.com/DigitalPublishingToolkit/template-test.git",
		branch: "master",
		commit: "4396da7ace5685b2a9b550155ce1c18b0125bb71",
		files: [
			"book.html",
			"docx/hva_deliverables_2015_q4.docx",
			"Makefile"
		],
		modified: [
			"book.html",
			"docx/hva_deliverables_2015_q4.docx"
		]
	}

	... files: all files
	... modified: all modified or newly created files on top of the last commit

GET temps/files/:temp/:fn

	param: format ("raw", "download" or "json"; default: "raw")

	if "json":

	{
		fn: "book.html",
		mime: "text/html",
		data: "base64-encoded data"
	}

POST temps/files/update/:temp

	param: files (array of { fn: "filename", data: "base64-encoded data" })

	{
		modified: [
			"docx/foo.docx",
			"md/foo.md"
		]
	}

	... modified: modified or newly added files (by this method)

POST temps/files/update/:temp

	param: uploaded files
	param: auto_convert (default: true)

	{
		modified: [
			"docx/foo.docx",
			"md/foo.md"
		]
	}

	... modified: modified or newly added files (by this method)

POST temps/files/delete/:temp/:fn

POST temps/make/:temp

	param: target (default: default_target config option)
	param: clean_before (default: false)
	param: clean_after (default: false)

	{
		target: "html",
		modified: [
			"book.html"
		],
		error: false, (or errno)
		out: [
			"...",
			"..."
		]
	}

	... modified:modified or newly added files (by this method)

POST temps/commit/:temp

	param: files (default: all modified)
	param: clean_before (default: true)
	param: message (optional)
	param: author (optional)

POST temps/push/:temp

	param: repo

POST temps/switch_repo/:temp

	param: repo
	param: clean_before (default: true)
	param: clean_after (default: true)

POST temps/delete/:temp

GET projects

	[
		{
			repo: "https://github.com/DigitalPublishingToolkit/my-first-book.git",
			parent: "https://github.com/DigitalPublishingToolkit/template-test.git",
			created: 1448639925,
			updated: 1448640020
		}
	]

POST projects/create

	param: key -> value

POST projects/update/:repo

	param: key -> value

POST projects/delete/:repo
