# Sausagemachine API

GET repos

	[
		{
			repo: "https://github.com/DigitalPublishingToolkit/template-test.git",
			description: "Default template",
			default: true
		}
	]

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
	... modified: all modified or newly created files

GET temps/files/:temp/:fn

	param: format ("raw", "download", "json")

	if "json":

	{
		fn: "book.html",
		mime: "text/html",
		data: "..." (base64-encoded)
	}

POST temps/files/update/:temp

	param: files (or HTTP upload)
	param: auto_convert (default: no)

	{
		converted: [
			"md/foo.md"
		],
		modified: [
			"docx/foo.docx",
			"md/foo.md"
		]
	}

	... converted: files created or modified by auto_convert
	... modified: only by this

POST temps/files/delete/:temp/:fn

POST temps/make/:temp

	param: target (default: default_target config option)
	param: clean_before (default: false)

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

	... modified: only by this

POST temps/commit/:temp

	param: files (default: all modified)
	param: message (optional)
	param: author (optional)

POST temps/push/:temp

	param: repo

POST temps/switch_repo/:temp

	param: repo
	param: clean_before
	param: clean_after

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

POST projects/update/:repo

	param: key -> value
