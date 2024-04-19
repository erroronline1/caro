### ean/gtin may have to be formatted as number or fracture before resaving as csv to avoid being displayed as exponential function

* [basko](#basko)
* [caroli](#caroli)
* [feet control](#feet-control)
* [fior gentz](#fior-gentz)
* [gottinger](#gottinger)
* [ihle](#ihle)
* [juzo](#juzo)
* [lohmann und rauscher](#lohmann-und-rauscher)
* [medi](#medi)
* [mmib](#mmib)
* [neatec](#neatec)
* [nowecor](#nowecor)
* [oessur](#oessur)
* [ofa](#ofa)
* [ortho systems](#ortho-systems)
* [ortho reha neuhof](#ortho-reha-neuhof)
* [otto bock](#otto-bock)
* [perpedes](#perpedes)
* [protheseus](#protheseus)
* [prowalk](#prowalk)
* [rebotec](#rebotec)
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

### basko
delete . from headers, replace specialchars
```json
{
	"filesetting": {
		"columns": ["Art-Nr", "Artikelname", "Groesse", "Seite", "Farbe", "EAN GTIN", "Verpackungseinheit"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Art-Nr"],
			"article_name": ["Artikelname", ", ", "Groesse", ", ", "Seite", ", ", "Farbe"],
			"article_unit": ["Verpackungseinheit"],
			"article_ean": ["EAN GTIN"]
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
					"article_name": "knieorthese|bandage|strumpf|ellenbogen|handlagerung|pavlik|select.*?on|hyperextension|tls|fingerorthese|cervical|boston|lacer|peronaeus|^toeoff|^ypsilon|^bluerocker|gait|a.s.o.|rhizo|schuh|c.o.s."
				}
			}
		}
	]
}
```

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

### lohmann und rauscher
```json
{
	"filesetting": {
		"columns": ["Material", "Bezeichnung"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Material"],
			"article_name": ["Bezeichnung"],
			"article_unit": [""],
			"article_ean": [""]
		}]
	}
}
```

### medi
replace . and specialchars in header

```json
{
	"filesetting": {
		"columns": ["ArtikelNr", "Bezeichnung", "Mengeneinh", "EAN-Nummer"]
	},
	"modify": {
		"replace":[
			["EAN-Nummer", "\\.", ""]
		],
		"rewrite": [{
			"article_no": ["ArtikelNr"],
			"article_name": ["Bezeichnung"],
			"article_unit": ["Mengeneinh"],
			"article_ean": ["EAN-Nummer"]
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

### mmib
rewrite pricelist (concat first three columns to article number, paste as values, add name column, delete the rest)
```json
{
	"filesetting": {
		"columns": ["artnr", "name"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["artnr"],
			"article_name": ["name"],
			"article_unit": ["LFM"],
			"article_ean": [""]
		}]
	}
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

### nowecare
add headers on line 7
```json
{
	"filesetting": {
		"headerrowindex": 6,
		"columns": ["EAN", "NAME", "ARTNR"]
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "delete headers",
			"keep": true,
			"match": {
				"all": {
					"ARTNR": ".+"
				}
			}
		}
	],

	"modify": {
		"rewrite": [{
			"article_no": ["ARTNR"],
			"article_name": ["NAME"],
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

### oessur
merge lists, delete . from headers
modify product description for:
* knit-rite soft socken (1SB1XXYY3,ff)

resume on variflex

```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["ART-NR", "BEZEICHNUNG"]
	},
	"filter":[
		{
			"apply": "filter_by_expression",
			"comment": "delete unnecessary products",
			"keep": false,
			"match": {
				"all": {
					"ART-NR": ""
				}
			}
		}
	]
	"modify": {
		"replace":[
			["ART-NR", "(I-4443|I-CL63|I-CW63|I-CL43|I-CW43|I-CL53)(XX)", 16, 18, 20, 22, 23.5, 25, 26.5, 28, 30, 32, 34, 36, 40],
			["ART-NR", "(I-4446|I-CL46|I-CL56)(XX)", 16, 18, 20, 22, 23.5, 25, 26.5, 28, 30, 32, 34, 36],
			["ART-NR", "(I-SXC0|I-SXG0|I-SXV0)(XX)", 20, 22, 24, 26, 28, 30, 32, 35, 38, 41, 44, 47, 51, 55, 60, 65],
			["ART-NR", "(I-SXL3|I-SXL6)(XX)", 16, 18, 20, 22, 23.5, 25, 26.5, 28, 30, 32, 34, 36, 40, 45],
			["ART-NR", "(I-4713|I-4723|I-3664|I-3663|I-4613)(XX)", 18, 20, 22, 23.5, 25, 26.5, 28, 30, 32, 34, 36],
			["ART-NR", "(I-6303|I-6306|I-6003|I-6203|I-4313|I-4223|I-4013|I-4213|I-4016|I-4216|I-5303|I-5406|I-5506|I-5006|I-5106)(XX)", 16, 18, 20, 22, 23.5, 25, 26.5, 28, 30, 32, 34, 36, 40, 45],
			["ART-NR", "(I-4913)(XX)", 18, 20, 22, 23.5, 25, 26.5, 28, 30, 32, 34, 36],
			["ART-NR", "(I-0124|I-2024)(XX)", 16, 18, 20, 22, 23.5, 25, 26.5, 28, 30, 32, 34, 36, 38, 40, 42, 45],
			["ART-NR", "(I-1033|I-1233|I-09DC|I-09FLC)(XX)", 16, 18, 20, 22, 23.5, 25, 26.5, 28, 30, 32, 34],
			["ART-NR", "(M8994|M8996|MF921|M8997|M8998)(XX)", 18, 20, 22, 23.5, 25, 26.5, 28, 30, 32, 34, 36, 38, 40, 45],
			["ART-NR", "(MF941)(XX)", 20, 22, 23.5, 25, 26.5, 28, 30, 32, 34, 36, 38, 40],
			["ART-NR", "(T-0103|T-0106|T-0109|T-CL03|T-CL06|T-CL09)(XX)", "15 (S)", "18 (M)", "20 (M+)", "25 (L)", "28 (L+)", "33 (XL)"],
			["ART-NR", "(F-2011|S-2011)(XX)", 35, 41, 49, 55, 62],
			["ART-NR", "(F-1010)(XX)",  35, 49, 62],
			["ART-NR", "(MK0020|M00020)(X)",  "2 (S)", "3 (M)", "4 (L)"],
			["ART-NR", "(I-8532|I-TF673|I-7532|I-7032)(XX)", 25, 26.5, 28, 30, 32, 34, 36, 38, 40, 45, 50, 55],
			["ART-NR", "(I-8632)(XX)", 23.5, 25, 26.5, 28, 30, 32, 34, 36, 38, 40],
			["ART-NR", "(I-TF678|I-7632)(XX)", 25, 26.5, 28, 30, 32, 34, 36, 38],
			["ART-NR", "(M8917)(XX)", 26.5, 28, 30, 32, 34, 36, 38, 40, 45, 50],
			["ART-NR", "(M8918)(XX)", 25, 26.5, 28, 30, 32, 34, 36, 40],
			["ART-NR", "(I-7132)(XX)", 23.5, 25, 26.5, 28, 30, 32, 34, 36, 38, 40, 42],
			["ART-NR", "(M8955|M8957)(XX)", 27, 31, 35, 39, 43, 47, 52],
			["ART-NR", "(M8956|M8958)(XX)", 24, 27, 31, 35, 39],
			["ART-NR", "(I-8131)(XX)", 12, 14, 16, 18, 20, 22],
			["ART-NR", "(I-3000)(XX)", "06", "07", "09", 11, 12, 14, 16, 17, 19, 20, 22, 24, 26],
			["ART-NR", "(K-5311|K-5313)(XX)", 10, 13, 16, 20],
			["ART-NR", "(1SB1|1SBS)(XX)(.+?)", "NA (SCHMAL)", "RG (NORMAL)", "WD (WEIT)", "XW (EXTRA-WEIT)"],
			["ART-NR", "(1SB1.*?|1SBS.*?)(YY)(.+?)", "SH (KURZ)", "MD (MITTEL)", "LG (LANG)"],
			["ART-NR", "(CTF020)(X)(.+?)", "2 (M KURZ)", "3 (M STANDARD)", "4 (L KURZ)", "5 (L STANDARD)"],
			["ART-NR", "(CTF020.+?)(Y)", "L", "R"],

			["ART-NR", "(FBD0|FBDU|FBP0|FBPU|JBPE|JBPU|FAPE|FAXE|FAPU)(X)(.+?)", 1, 2, 3, 4, 5],
			["ART-NR", "(PLA0)(X)(.+?)", 1, 2, 3, 4, 5, 6],
			["ART-NR", "(PFP0|PFPU)(X)(.+?)", 1, 2, 3, 4, 5, 6, 7],
			["ART-NR", "(BSP0|BSPU|BST0|BSTU|PSX01|PSXU1|PXT0|PXTU|PLT0|PLTU)(X)(.+?)", 1, 2, 3, 4, 5, 6, 7, 8],
			["ART-NR", "(PXC0|PXCU|PST0|PSTU|PDM0|PLP0|PLPU)(X)(.+?)", 1, 2, 3, 4, 5, 6, 7, 8, 9],

			["ART-NR", "(BSP0.|BSPU.|BST0.|BSTU.|FST0|JBPE.|JBPU.|FSL0|FAPU.|PSX01.|PSXU1.|PFP0.|PFPU.|PXC0.|PXCU.|PXT0.|PXTU.|PST0.|PSTU.|PDM0.|PLP0.|PLPU.|PLT0.|PLTU.)(YY)(.+?)", 22, 23, 24, 25, 26, 27, 28, 29, 30],
			["ART-NR", "(FBD0.|FBDU.|FBP0.|FBPU.|FSM0)(YY)(.+?)", 21, 22, 23, 24, 25, 26, 27, 28],
			["ART-NR", "(FAPE.|FAXE.)(YY)(.+?)", 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30],
			["ART-NR", "(FSF0S)(YY)(.+?)", 22, 23, 24],
			["ART-NR", "(FST0S)(YY)(.+?)", 25, 26, 27, 28, 29, 30],
			["ART-NR", "(PLA0.)(YY)(.+?)", 22, 23, 24, 25, 26, 27, 28],
			["ART-NR", "(FSF0)(YY)(.+?)", 22, 23, 24, 25, 26],
			["ART-NR", "(FST0)(YY)(.+?)", 27, 28],

			["ART-NR", "(BSP0.+|BSPU.+|BST0.+|BSTU.+|FST0.+|FBD0.+|FBDU.+|FBP0.+|FBPU.+|JBPE.+|JBPU.+|FSL0.+|FAPE.+|FAXE.+|FAPU.+|PSX01.+|PSXU1.+|FSF0S.+|FST0S.+|PFP0.+|PFPU.+|PXC0.+|PXCU.+|PXT0.+|PXTU.+|PST0.+|PSTU.+|PDM0.+|PLP0.+|PLPU.+|PLT0.+|PLTU.+|PLA0.+|FSF0.+|FST0.+)(Z)", "L", "R"],


],
		"rewrite": [{
			"article_no": ["ART-NR"],
			"article_name": ["BEZEICHNUNG"],
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

### ofa
delete . from headers

```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["Artikel", "Bez 1", "Bez II", "VME"]
	},
	"modify": {
		"replace":[
			["Artikel", "\\s{2,}", " "],
			["Bez 1", "\\s{2,}", " "],
			["Bez II", "\\s{2,}", " "]
		],
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

### rebotec
delete last row
```json
{
	"filesetting": {
		"headerrowindex": 2,
		"columns": ["Art-Nr", "Bezeichnung1", "Bezeichnung2", "EAN_GTIN", "ME"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["Art-Nr"],
			"article_name": ["Bezeichnung1", ", ", "Bezeichnung2"],
			"article_unit": ["ME"],
			"article_ean": ["EAN_GTIN"]
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
					"article_name": "stuhl|hilfe|wagen|rollator|stock|gehstütze|achselstütze|toiletten|gehgestell|vierfuß|fischer"
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
