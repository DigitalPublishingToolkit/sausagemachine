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

GET temps/show/:temp

	{
		temp: "14486397022961",
		repo: "https://github.com/DigitalPublishingToolkit/template-test.git",
		branch: "master",
		files: [
			'book.html',
			'docx/hva_deliverables_2015_q4.docx'
		],
		modified: [
			'book.html',
			'docx/hva_deliverables_2015_q4.docx'
		]
	}

GET temps/files/:temp/:fn

	different encodings

POST temps/files/update/:temp

	[] or files

POST temps/files/delete/:temp/:fn

POST temps/make/:temp

POST temps/commit/:temp

POST temps/push/:temp

POST temps/switch_repo/:temp

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
