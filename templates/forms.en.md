# Content
### Common
* [Identify](#identify)
* [Basic data](#basic-data)


## Identify
```json
[
	[
		{
			"type": "identify",
			"attributes": {
				"name": "Case"
			}
		}
	]
]
```
[Content](#content)

## Basic data
```json
[
	[
		{
			"type": "text",
			"attributes": {
				"name": "Name",
				"required": true
			}
		},
		{
			"type": "date",
			"attributes": {
				"name": "Date of birth"
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Address"
			}
		},
		{
			"type": "tel",
			"attributes": {
				"name": "Phone number"
			}
		},
		{
			"type": "email",
			"attributes": {
				"name": "eMail-address"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Insurance"
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Info"
			},
			"hint": "Contact person, address particularities"
		}
	],
	[
		{
			"type": "textarea",
			"attributes": {
				"name": "Service, aid or recipe"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Prescriber"
			},
			"hint": "named"
		},
		{
			"type": "text",
			"attributes": {
				"name": "Patient number"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "ERP case number"
			}
		}
	]
]
```
[Content](#content)