### ean/gtin may have to be formatted as number or fracture before resaving as csv to avoid being displayed as exponential function

* [caroli](#caroli)
* [feet control](#feet-control)
* [fiorgentz](#fiorgentz)
* [juzo (failing due to filesize)](#juzo)
* [neatec](#neatec)
* [nowecor (trading goods yet to define)](#nowecor)
* [ofa](#ofa)
* [ortho reha neuhof](#ortho-reha-neuhof)
* [otto bock](#otto-bock)
* [perpedes (trading goods yet to define)](#perpedes)
* [prowalk](#prowalk)
* [protheseus](#protheseus)
* [russka](#russka)
* [schein](#schein)
* [taska](#taska)

### caroli
delete first two columns and rows
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

### fiorgentz
add name headers where empty, delete . from headers
```json
{
	"filesetting": {
		"columns": ["Art-Nr", "Bezeichnung", "Bezeichnung 2", "ME", "EANNummer"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Art-Nr"],
			"article_name": ["Bezeichnung", ", ", "Bezeichnung 2"],
			"article_unit": ["ME"],
			"article_ean": ["EANNummer"]
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
			"comment": "keep all products",
			"keep": false,
			"match": {
				"all": {
					"article_name": "^(?!.*?(schuh)).*"
				}
			}
		}
	]
}
```

### juzo
```json
{
	"filesetting": {
		"columns": ["JUZO-Artikelnr", "GTIN", "Mengeneinheit (Artikel)", "Artikelbezeichnung 1", "Artikelbezeichnung 2"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["JUZO-Artikelnr"],
			"article_name": ["Artikelbezeichnung 1", ", ", "Artikelbezeichnung 2"],
			"article_unit": ["Mengeneinheit (Artikel)"],
			"article_ean": ["GTIN"]
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
			"comment": "keep all products",
			"keep": true,
			"match": {
				"all": {
					"article_name": ".*?"
				}
			}
		}
	]
}
```

### neatec
replace specialchars in header
```json
{
	"filesetting": {
		"headerrowindex": 1,
		"columns": ["Artikel-Nr", "Artikelbezeichnung lang", "Groesse", "Farbe", "Einheit"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Artikel-Nr."],
			"article_name": ["Artikelbezeichnung lang", ", ", "Groesse", ", ", "Farbe"],
			"article_unit": ["Einheit"],
			"article_ean": [""]
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
			"comment": "keep all products",
			"keep": true,
			"match": {
				"all": {
					"article_name": ".*?"
				}
			}
		}
	]
}
```

### nowecor
```json
{
	"filesetting": {
		"headerrowindex": 1,
		"columns": ["Artikel-Nr", "Artikelbezeichnung lang", "Groesse", "Farbe", "Einheit"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Artikel-Nr."],
			"article_name": ["Artikelbezeichnung lang", ", ", "Groesse", ", ", "Farbe"],
			"article_unit": ["Einheit"],
			"article_ean": [""]
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
					"article_name": ""
				}
			}
		}
	]
}
```

### ofa
```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["Artikel", "Bez. 1", "Bez. II", "EAN", "VME"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Artikel"],
			"article_name": ["Bez. 1", ", ", "Bez. II"],
			"article_unit": ["VME"],
			"article_ean": ["EAN"]
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
					"article_name": "Anti-Rutsch-Beschichtung"
				}
			}
		}
	]
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

### perpedes
```json
{
	"filesetting": {
		"headerrowindex": 9,
		"columns": ["Material", "Materialkurztext", "ME"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Material"],
			"article_name": ["Materialkurztext"],
			"article_unit": ["ME"],
			"article_ean": [""]
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
			"comment": "delete unneccessary products",
			"keep": false,
			"match": {
				"all": {
					"article_name": ""
				}
			}
		}
	]
}
```

### prowalk
replace specialchars in header

```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["ArtikelNummer", "Bezeichnung 1", "Bezeichnung 2", "Farbe", "Groesse", "Menge"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["ArtikelNummer"],
			"article_name": ["Bezeichnung 1", ", ", "Bezeichnung 2", ", ", "Farbe", ", ", "Groesse"],
			"article_unit": ["Menge"],
			"article_ean": [""]
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
			"comment": "delete unneccessary products",
			"keep": true,
			"match": {
				"all": {
					"article_name": "Lagerungsschiene|orthese|helm|headmaster"
				}
			}
		}
	]
}
```

### protheseus
add header on first line
```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["artno", "name", "unit"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["artno"],
			"article_name": ["name"],
			"article_unit": ["unit"],
			"article_ean": [""]
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
			"comment": "delete unneccessary products",
			"keep": true,
			"match": {
				"all": {
					"article_name": "Compression|Weste|Peronäusschiene|Stax|liner"
				}
			}
		}
	]
}
```

### russka
```json
{
	"filesetting": {
		"headerrowindex": 2,
		"columns": ["Artikelnummer ", "Artikelbezeichnung", "Einheit"]
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "delete unneccessary products",
			"keep": false,
			"match": {
				"all": {
					"Einheit": "nicht mehr lieferbar"
				}
			}
		}
	],
	"modify": {
		"rewrite": [{
			"article_no": ["Artikelnummer "],
			"article_name": ["Artikelbezeichnung"],
			"article_unit": ["Einheit"],
			"article_ean": [""]
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
			"comment": "delete unneccessary products",
			"keep": true,
			"match": {
				"all": {
					"article_name": "kissen|handschuh|extensionsschiene|finger.*?schiene|orthese|protector|stützschiene|handgelenkschiene|handschuh|TAP-Schiene|urias|buddy loop|comfy"
				}
			}
		}
	]
}
```

### schein
```json
{
	"filesetting": {
		"headerrowindex": 1,
		"columns": ["Artikelnummer", "Artikelbezeichnung", "Artikelbezeichnung 2", "Basiseinheit"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Artikelnummer"],
			"article_name": ["Artikelbezeichnung", ", ", "Artikelbezeichnung 2"],
			"article_unit": ["Basiseinheit"],
			"article_ean": [""]
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
			"comment": "delete unneccessary products",
			"keep": true,
			"match": {
				"all": {
					"article_name": "einlage|schuh|orthese"
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