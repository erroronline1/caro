### ean/gtin may have to be formatted as number or fracture before resaving as csv to avoid being displayed as exponential function

* [caroli](#caroli)
* [feet control](#feet-control)
* [fior gentz](#fior-gentz)
* [gottinger](#gottinger)
* [ihle](#ihle)
* [juzo (failing due to filesize)](#juzo)
* [neatec](#neatec)
* [nowecor](#nowecor)
* [ofa (failing due to timeout)](#ofa)
* [ortho reha neuhof](#ortho-reha-neuhof)
* [ortho systems](#ortho-systems)
* [otto bock](#otto-bock)
* [perpedes](#perpedes)
* [prowalk](#prowalk)
* [protheseus](#protheseus)
* [rehaforum](#rehaforum)
* [russka](#russka)
* [schein](#schein)
* [sporlastic](#sporlastic)
* [streifeneder](#streifeneder)
* [taska](#taska)
* [tigges](#tigges)
* [triconmed](#triconmed)
* [uniprox](#uniprox)
* [werkmeister](#werkmeister)

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

### fior gentz
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

### ihle
delete . from headers, replace specialchars
```json
{
	"filesetting": {
		"headerrowindex": 1,
		"columns": ["Nr", "Beschreibung 1", "Beschreibung 2", "Farbe", "Groesse", "Einheiten"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Nr"],
			"article_name": ["Beschreibung 1", ", ", "Beschreibung 2", ", ", "Farbe", ", ", "Groesse"],
			"article_unit": ["Einheiten"],
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
					"article_name": "socke|strumpf|ckchen|kniestr|shirt|body|hose"
				}
			}
		}
	]
}
```

### gottinger
delete whitespaces and . from headers
```json
{
	"filesetting": {
		"headerrowindex": 2,
		"columns": ["Art Nr", "Bezeichnung", "Menge"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Art Nr"],
			"article_name": ["Bezeichnung"],
			"article_unit": ["Menge"],
			"article_ean": [""]
		}]
	}
}
```

### juzo
delete unreqired columns
```json
{
	"filesetting": {
		"columns": ["JUZO-Artikelnr", "GTIN", "Mengeneinheit", "Artikelbezeichnung 1", "Artikelbezeichnung 2"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["JUZO-Artikelnr"],
			"article_name": ["Artikelbezeichnung 1", ", ", "Artikelbezeichnung 2"],
			"article_unit": ["Mengeneinheit"],
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
					"article_name": ".+"
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
		"headerrowindex": 0,
		"columns": ["MATCHCODE", "NAME1", "EANCODE", "VK_EINHEIT"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["MATCHCODE"],
			"article_name": ["NAME1"],
			"article_unit": ["VK_EINHEIT"],
			"article_ean": ["EANCODE"]
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
			"keep": true,
			"match": {
				"all": {
					"article_name": "aircast|fersenkeil|push|^\\w+stabil|manu|^\\w+-hit|vertebra|epidyn|rhizo|handgelenk|stabilo|hallux|unterarm|^\\w+train|donjoy|4titude|manu|omox|secutec|epib|materna|gehstock|bort|gilchrist|malleo|achillo|tübinger|orthese|walkon|2-kletter|bandage|cellacare|genu|epiflex|necky|spino|mks|knieschine|schuh|tricodur|clavicula|^\\w+force|^air\\w+|toeoff|bluerocker|collamed|medi|lumba|epico|afo|pluspoint|liner|psa|souplesse"
				}
			}
		}
	]
}
```

### ofa
delete . from headers
```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["Artikel", "Bez 1", "Bez II", "VME"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Artikel"],
			"article_name": ["Bez 1", ", ", "Bez II"],
			"article_unit": ["VME"],
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
					"article_name": "Anti-Rutsch-Beschichtung"
				}
			}
		}
	]
}
```

### ortho reha neuhof
delete . from header
```json
{
	"filesetting": {
		"columns": ["ArtNr", "Bezeichnung", "ME", "UDI-DI"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["ArtNr"],
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

### ortho systems
delete . and () from header
```json
{
	"filesetting": {
		"columns": ["Artikel-Nr", "Einh", "EAN_UDI-DI-Nr", "Auflistung mit Menge und Inhalt-Pos"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Artikel-Nr"],
			"article_name": ["Auflistung mit Menge und Inhalt-Pos"],
			"article_unit": ["Einh"],
			"article_ean": ["EAN_UDI-DI-Nr"]
		}]
	}
}
```

### otto bock
join tables
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
			"keep": true,
			"match": {
				"all": {
					"article_name": "^o|^ns|^av|schuh|^d\\d"
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

### rehaforum
```json
{
	"filesetting": {
		"columns": ["Artikelnummer", "Bezeichnung", "EAN", "Einheit"]
	},
	"filter": [
		{
			"apply": "filter_by_duplicates",
			"comment": "keep amount of duplicates of column value, ordered by another concatenated column values (asc/desc)",
			"keep": true,
			"duplicates": {
				"orderby": ["Artikelnummer"],
				"descending": false,
				"column": "Artikelnummer",
				"amount": 1
			}
		}
	],
	"modify": {
		"rewrite": [{
			"article_no": ["Artikelnummer"],
			"article_name": ["Bezeichnung"],
			"article_unit": ["Einheit"],
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
			"comment": "delete unneccessary products",
			"keep": true,
			"match": {
				"all": {
					"article_name": ".+"
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
### sporlastic
delete . from headers, replace specialchars and whitespaces
```json
{
	"filesetting": {
		"headerrowindex": 1,
		"columns": ["Bestell-Nr", "ArtikelBez1", "ArtikelBez2", "Seite", "Farbe", "Groesse", "ME", "EAN_CODE"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Bestell-Nr"],
			"article_name": ["ArtikelBez1", ", ", "ArtikelBez2", ", ", "Seite", ", ", "Farbe", ", ", "Groesse"],
			"article_unit": ["ME"],
			"article_ean": ["EAN_CODE"]
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
					"article_name": ".+"
				}
			}
		}
	]
}
```

### streifeneder
delete . from headers, replace specialchars and whitespaces
```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["Artikelnummer", "Bezeichnung", "Einheit", "GTIN-Code"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Artikelnummer"],
			"article_name": ["Bezeichnung"],
			"article_unit": ["Einheit"],
			"article_ean": ["GTIN-Code"]
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
					"article_name": "fersenkeil|schutzhülle|einlagen|philadelphia|clearsil|extensionsorthese|contexgel|comfortsil|primosil|skincaresil|classicsil|ak-control|tl bandage|control4sil|walker|yale|support|achillomax|genumax|spreizhose|knieschiene|schuh|kompressionsstumpstrumpf"
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

### tigges
delete . from headers, replace specialchars and whitespaces
```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["Bestellnr", "Artikelbezeichnung 1", "Artikelbezeichnung 2", "Artikelbezeichnung 3", "Verpackung1"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Bestellnr"],
			"article_name": ["Artikelbezeichnung 1", ", ", "Artikelbezeichnung 2", ", ", "Artikelbezeichnung 3"],
			"article_unit": ["Verpackung1"],
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
					"article_name": "WS-Bandage|Wirbelsäulenbandage|Damenhosenbandage|tigges-.+set|Lumbal.*?orthese|t-flex|BWS|Lumbalbandage"
				}
			}
		}
	]
}
```

### triconmed
delete . from headers, replace specialchars and whitespaces
```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["Artikelnr", "Artikelbeschreibung"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Artikelnr"],
			"article_name": ["Artikelbeschreibung"],
			"article_unit": [""],
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
					"article_name": ".+"
				}
			}
		}
	]
}
```

### uniprox
delete . from headers, replace specialchars and whitespaces
```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["Artikel-Nr", "Beschreibung", "ME", "EAN"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Artikel-Nr"],
			"article_name": ["Beschreibung"],
			"article_unit": ["ME"],
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
			"comment": "delete unneccessary products",
			"keep": true,
			"match": {
				"all": {
					"article_name": "^bob|^daho|liner|philadelphia"
				}
			}
		}
	]
}
```

### werkmeister
delete . from headers, replace specialchars and whitespaces
```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["Artikelnummer", "Farbe Groesse", "Artikelbez 1", "Artikelbez 2", "EAN"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Artikelnummer", " ", "Farbe Groesse"],
			"article_name": ["Artikelbez 1", " ", "Artikelbez 2"],
			"article_unit": [""],
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
			"comment": "delete unneccessary products",
			"keep": true,
			"match": {
				"all": {
					"article_name": "Arthrodesenkissen"
				}
			}
		}
	]
}
```
