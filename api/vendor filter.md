* [caroli](#caroli)
* [feet control](#feet-control)
* [ortho reha neuhof](#ortho-reha-neuhof)
* [otto bock](#otto-bock)
* [taska](#taska)

### caroli
delete first two columns
```json
{
    "filesetting": {
		"headerrowindex": 2,
        "columns": ["Neue Artikel Nummer", "Bezeichnung", "GTIN"]
    },
    "modify": {
        "rewrite": [{
            "article_no": ["Neue Artikel Nummer"],
            "article_name": ["Bezeichnung"],
            "article_unit": [""],
            "article_ean": ["GTIN"]
        }]
    }
}
```

### feet control
add header
```json
{
    "filesetting": {
        "columns": ["artnr"]
    },
    "modify": {
        "rewrite": [{
            "article_no": ["artnr"],
            "article_name": [""],
            "article_unit": [""],
            "article_ean": [""]
        }]
    }
}
```

### ortho reha neuhof
```json
{
    "filesetting": {
        "columns": ["Art.Nr.", "Bezeichnung", "ME", "UDI-DI"]
    },
    "modify": {
        "rewrite": [{
            "article_no": ["Art.Nr."],
            "article_name": ["Bezeichnung"],
            "article_unit": ["ME"],
            "article_ean": ["UDI-DI"]
        }]
    }
}
```
```json
{
	"filesetting": {
		"columns": ["article_no", "article_name"]
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "delete unnecessary products",
			"keep": false,
			"match": {
				"all": {
					"article_name": "^(?!.*?(liner|kniekappe|strumpf|wilmer)).*"
				}
			}
		}
	]
}
```

### otto bock
```json
{
    "filesetting": {
        "columns": ["Material", "Materialtext", "Mengeneinheit", "EAN/UPC"]
    },
    "modify": {
        "rewrite": [{
            "article_no": ["Material"],
            "article_name": ["Materialtext"],
            "article_unit": ["Mengeneinheit"],
			"article_ean": ["EAN/UPC"]
        }]
    }
}
```
```json
{
	"filesetting": {
		"columns": ["article_no", "article_name"]
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "delete unnecessary products",
			"keep": false,
			"match": {
				"all": {
					"article_name": "^(?!.*?(knee comfort|strumpf|tübinger|necky|walk.*on|genu|patella|liner|malleo|agilium|proflex|cosa|smartspine)).*"
				}
			}
		}
	]
}
```

### taska
```json
{
    "filesetting": {
        "headerrowindex": 0,
        "dialect": {
            "separator": ";",
            "enclosure": "\"",
            "escape": ""
        },
        "columns": ["Part", "DE"]
    },
    "modify": {
        "rewrite": [{
            "article_no": ["Part"],
            "article_name": ["DE"],
            "article_unit": [""],
			"article_ean": [""]
        }]
    }
}
```