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
* [minke](#minke)
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
		"add": {
			"trading_good": "0",
			"has_expiry_date": "0"
		},
		"replace":[
			["EAN GTIN", "\\s+", ""]
		],
		"conditional_and": [
			["trading_good", "1", ["Artikelname", "knieorthese|bandage|strumpf|ellenbogen|handlagerung|pavlik|select.*?on|hyperextension|tls|fingerorthese|cervical|boston|lacer|peronaeus|^toeoff|^ypsilon|^bluerocker|gait|a.s.o.|rhizo|schuh|c.o.s."]]
		],
		"rewrite": [{
			"article_no": ["Art-Nr"],
			"article_name": ["Artikelname", ", ", "Groesse", ", ", "Seite", ", ", "Farbe"],
			"article_unit": ["Verpackungseinheit"],
			"article_ean": ["EAN GTIN"]
		}]
	}
}
```

### caroli
delete first two columns and rows
```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["NeueArtikelNummer", "Bezeichnung", "GTIN"]
	},
	"modify": {
		"rewrite": [{
			"article_no": ["NeueArtikelNummer"],
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
		"add": {
			"trading_good": "0"
		},
		"replace":[
			["EANNummer", "\\s+", ""]
		],
		"conditional_and": [
			["trading_good", "1", ["Bezeichnung", ".+schuh"]]
		],
		"rewrite": [{
			"article_no": ["Art-Nr"],
			"article_name": ["Bezeichnung", ", ", "Bezeichnung 2"],
			"article_unit": ["ME"],
			"article_ean": ["EANNummer"]
		}]
	}
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
		"add": {
			"trading_good": "0"
		},
		"rewrite": [{
			"article_no": ["Nr"],
			"article_name": ["Beschreibung 1", ", ", "Beschreibung 2", ", ", "Farbe", ", ", "Groesse"],
			"article_unit": ["Einheiten"],
			"article_ean": [""]
		}],
		"conditional_and": [
			["trading_good", "1", ["article_name", "socke|strumpf|ckchen|kniestr|shirt|body|hose"]]
		]
	}
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
		"add": {
			"trading_good": "1",
			"has_expiry_date": "1"
		},
		"rewrite": [{
			"article_no": ["JUZO-Artikelnr"],
			"article_name": ["Artikelbezeichnung 1", ", ", "Artikelbezeichnung 2"],
			"article_unit": ["Mengeneinheit"],
			"article_ean": ["GTIN"]
		}]
	}
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
		"add": {
			"trading_good": "1"
		},
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

### minke
rewrite pricelist (concat sections (bit of a pita) Beschreibung to Staffel, repaste as values)
```json
{
	"filesetting": {
		"columns": ["Artikelnummer", "Einheit", "Staffel"]
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "delete headers",
			"keep": true,
			"match": {
				"all": {
					"Artikelnummer": ".+"
				}
			}
		}
	],
	"modify": {
		"rewrite": [{
			"article_no": ["Artikelnummer"],
			"article_name": ["Staffel"],
			"article_unit": ["Einheit"],
			"article_ean": [""]
		}]
	}
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
		"add": {
			"trading_good": "1",
			"has_expiry_date": "1"
		},
		"rewrite": [{
			"article_no": ["Artikel-Nr."],
			"article_name": ["Artikelbezeichnung lang", ", ", "Groesse", ", ", "Farbe"],
			"article_unit": ["Einheit"],
			"article_ean": [""]
		}]
	}
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
		"add": {
			"trading_good": "1"
		},
		"rewrite": [{
			"article_no": ["ARTNR"],
			"article_name": ["NAME"],
			"article_unit": [""],
			"article_ean": ["EAN"]
		}]
	}
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
		"add": {
			"trading_good": "0"
		},
		"replace":[
			["EANCODE", "\\s+", ""]
		],
		"conditional_and": [
			["trading_good", "1", ["NAME1", "aircast|fersenkeil|push|^\\w+stabil|manu|^\\w+-hit|vertebra|epidyn|rhizo|handgelenk|stabilo|hallux|unterarm|^\\w+train|donjoy|4titude|manu|omox|secutec|epib|materna|gehstock|bort|gilchrist|malleo|achillo|tübinger|orthese|walkon|2-kletter|bandage|cellacare|genu|epiflex|necky|spino|mks|knieschine|schuh|tricodur|clavicula|^\\w+force|^air\\w+|toeoff|bluerocker|collamed|medi|lumba|epico|afo|pluspoint|liner|psa|souplesse"]]
		],
		"rewrite": [{
			"article_no": ["MATCHCODE"],
			"article_name": ["NAME1"],
			"article_unit": ["VK_EINHEIT"],
			"article_ean": ["EANCODE"]
		}]
	}
}
```

### oessur
merge lists, delete . from headers
modify product description for:
* knit-rite soft socken (1SB1XXYY3, ff)
```json
{
	"filesetting": {
		"headerrowindex": 4,
		"columns": ["ART-NR", "BEZEICHNUNG"]
	},
	"filter":[
		{
			"apply": "filter_by_expression",
			"comment": "delete unnecessary products",
			"keep": true,
			"match": {
				"all": {
					"ART-NR": ".+"
				}
			}
		}
	],
	"modify": {
		"add": {
			"trading_good": "0"
		},
		"conditional_and": [
			["trading_good", "1", ["BEZEICHNUNG", "miami|occian|papoose|philadelphia|formfit|orthese|unloader|rebound|oa ease|firststep|cti|afo|keeogo"]]
		],
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
			["ART-NR", "(M8994|M8996|M8997|M8998)(XX)", 18, 20, 22, 23.5, 25, 26.5, 28, 30, 32, 34, 36, 38, 40, 45],
			["ART-NR", "(T-0103|T-0106|T-0109|T-CL03|T-CL06|T-CL09)(XX)", "15 (S)", "18 (M)", "20 (M+)", "25 (L)", "28 (L+)", "33 (XL)"],
			["ART-NR", "(F-2011|S-2011)(XX)", 35, 41, 49, 55, 62],
			["ART-NR", "(F-1010)(XX)", 35, 49, 62],
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
			["ART-NR", "(I-PO12)(XX)", 22, 23.5, 25, 26.5, 28, 30, 32, 34, 36, 38, 40, 42, 45],
			["ART-NR", "(I-PO22)(XX)", 30, 32, 34, 36, 38, 40, 42, 45, 50, 55],
			["ART-NR", "(MF921|MF941)(XX)", 18, 20, 22, 23.5, 25, 26.5, 28, 30, 32, 34, 36, 38, 40, 42, 45],

			["ART-NR", "(1SB1|1SBS)(XX)(.+?)", "NA (SCHMAL)", "RG (NORMAL)", "WD (WEIT)", "XW (EXTRA-WEIT)"],
			["ART-NR", "(1SB1.*?|1SBS.*?)(YY)(.+?)", "SH (KURZ)", "MD (MITTEL)", "LG (LANG)"],
			["ART-NR", "(CTF020)(X)(.+?)", "2 (M KURZ)", "3 (M STANDARD)", "4 (L KURZ)", "5 (L STANDARD)"],
			["ART-NR", "(CTF020.+?)(Y)", "L", "R"],

			["ART-NR", "(FBD0|FBDU|FBP0|FBPU|JBPE|JBPU|FAPE|FAXE|FAPU)(X)(.+?)", 1, 2, 3, 4, 5],
			["ART-NR", "(PLA0)(X)(.+?)", 1, 2, 3, 4, 5, 6],
			["ART-NR", "(PFP0|PFPU|CXE004|CXE002)(X)(.+?)", 1, 2, 3, 4, 5, 6, 7],
			["ART-NR", "(BSP0|BSPU|BST0|BSTU|PSX01|PSXU1|PXT0|PXTU|PLT0|PLTU|RSPE|RSFE|RHPE|RHFE|RSXE|RHXE|RSPU|RSFU|RRP0|RRF0|RSS4400|CHP00)(X)(.+?)", 1, 2, 3, 4, 5, 6, 7, 8],
			["ART-NR", "(PXC0|PXCU|PST0|PSTU|PDM0|PLP0|PLPU|VFPE|VFXE|VFPU|VLPE|VLXE|SSPE|SHPE|RSXE|RHXE|RSP0|SSPU|FSX009|SLP0|CXD002|CXL004|CXL002|FSX008|FSX003|FSX006)(X)(.+?)", 1, 2, 3, 4, 5, 6, 7, 8, 9],

			["ART-NR", "(BSP0.|BSPU.|BST0.|BSTU.|FST0|JBPE.|JBPU.|FSL0|FAPU.|PSX01.|PSXU1.|PFP0.|PFPU.|PXC0.|PXCU.|PXT0.|PXTU.|PST0.|PSTU.|PDM0.|PLP0.|PLPU.|PLT0.|PLTU.)(YY)(.+?)", 22, 23, 24, 25, 26, 27, 28, 29, 30],
			["ART-NR", "(FBD0.|FBDU.|FBP0.|FBPU.|FSM0)(YY)(.+?)", 21, 22, 23, 24, 25, 26, 27, 28],
			["ART-NR", "(FAPE.|FAXE.)(YY)(.+?)", 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30],
			["ART-NR", "(FSF0S)(YY)(.+?)", 22, 23, 24],
			["ART-NR", "(FST0S)(YY)(.+?)", 25, 26, 27, 28, 29, 30],
			["ART-NR", "(PLA0.)(YY)(.+?)", 22, 23, 24, 25, 26, 27, 28],
			["ART-NR", "(FSF0)(YY)(.+?)", 22, 23, 24, 25, 26],
			["ART-NR", "(FST0)(YY)(.+?)", 27, 28],
			["ART-NR", "(VFPE.|VFXE.|VFPU.|FSE0|VLPE.|VLXE.|RSPE.|RSFE.|RHPE.|RHFE.|RSXE.|RHXE.|RSPU.|RSFU.|SSPE.|SHPE.|RSXE.|RHXE.|SSPU.|SLP0.|CHP00.)(YY)(.+?)", 22, 23, 24, 25, 26, 27, 28, 29, 30],
			["ART-NR", "(FSX008)(YY)(.+?)", "HOCH", "NIEDRIG"],

			["ART-NR", "(I-8301|I-8302)(XX)", 12, 14, 16, 18],

			["ART-NR", "(VJB0|JRP0|CJX0)(X)(.+?)", 1, 2, 3, 4],
			["ART-NR", "(FSX007)(X)(.+?)", 1, 2, 3, 4, 5 ,6],
			["ART-NR", "(CHX0)(Y)(.+?)", 1, 2, 3, 4],

			["ART-NR", "(VJB0.|JRP0.)(YY)(.+?)", 16, 17, 18, 19, 20, 21, 22, 23, 24],
			["ART-NR", "(FJS0)(YY)(.+?)", 16, 17, 18, 19, 20, 21],
			["ART-NR", "(FSM0)(YY)(.+?)", 22, 23, 24],
			["ART-NR", "(FSL0)(YY)(.+?)", 18, 19, 20, 21, 22, 23, 24],

			["ART-NR", "(BSP0.+|BSPU.+|BST0.+|BSTU.+|FST0.+|FBD0.+|FBDU.+|FBP0.+|FBPU.+|JBPE.+|JBPU.+|FSL0.+|FAPE.+|FAXE.+|FAPU.+|PSX01.+|PSXU1.+|FSF0S.+|FST0S.+|PFP0.+|PFPU.+|PXC0.+|PXCU.+|PXT0.+|PXTU.+|PST0.+|PSTU.+|PDM0.+|PLP0.+|PLPU.+|PLT0.+|PLTU.+|PLA0.+|FSF0.+|FST0.+|FSE0.+|VJB0.+|FJS0.+|FSM0.+|JRP0.+|FSL0.+)(Z)", "L", "R"]
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

### ofa
delete . from headers

```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["Artikel", "Bez 1", "Bez II", "VME"]
	},
	"modify": {
		"add": {
			"trading_good": "1"
		},
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
		}],
		"conditional_and": [
			["trading_good", "0", ["article_name", "Anti-Rutsch-Beschichtung"]]
		]
	}
}
```

### ortho reha neuhof
line end is mandatory on gloves particular article_no, otherwise the filter matches recursively and having a memory overflow.
```json
{
	"filesetting": {
		"columns": ["Art.Nr.", "Bezeichnung", "ME", "UDI-DI"]
	},
    "filter": [
        {
            "apply": "filter_by_expression",
            "comment": "drop microcoating",
            "keep": false,
            "match": {
                "all": {
                    "Art.Nr.": "501[BDKJ].+C=.+"
                }
            }
        }
	],
	"modify": {
		"add": {
			"trading_good": "0",
			"has_expiry_date": "0"
		},
		"conditional_and": [
			["trading_good", "1", ["Bezeichnung", "liner|kniekappe|strumpf|wilmer"]]
		],
		"replace":[
			["Art.Nr.", "(501[BDKJ].+)(L$)", "L1", "L1-2", "L2", "L2-3", "L3", "L3-4", "L4", "L4-5", "L5"],
			["Art.Nr.", "(501[BDKJ].+)(R$)", "R1", "R1-2", "R2", "R2-3", "R3", "R3-4", "R4", "R4-5", "R5"]
		],
		"rewrite": [{
			"article_no": ["Art.Nr."],
			"article_name": ["Bezeichnung"],
			"article_unit": ["ME"],
			"article_ean": ["UDI-DI"]
		}]
	}
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
		"add": {
			"trading_good": "0",
			"has_expiry_date": "0"
		},
		"conditional_and": [
			["trading_good", "1", ["Materialtext", "knee comfort|strumpf|tübinger|necky|walk.*on|genu|patella|liner|malleo|agilium|proflex|cosa|smartspine"]],
			["has_expiry_date", "1", ["Material", "633s2|617h.+|617p\\d\\d.+|85h.+|636K.+"]]
		],
		"rewrite": [{
			"article_no": ["Material"],
			"article_name": ["Materialtext"],
			"article_unit": ["Mengeneinheit"],
			"article_ean": ["EAN/UPC"]
		}]
	}
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
		"add": {
			"trading_good": "0"
		},
		"conditional_and": [
			["trading_good", "1", ["Materialkurztext", "^o|^ns|^av|schuh|^d\\d"]]
		],
		"rewrite": [{
			"article_no": ["Material"],
			"article_name": ["Materialkurztext"],
			"article_unit": ["ME"],
			"article_ean": [""]
		}]
	}
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
		"add": {
			"trading_good": "0"
		},
		"rewrite": [{
			"article_no": ["ArtikelNummer"],
			"article_name": ["Bezeichnung 1", ", ", "Bezeichnung 2", ", ", "Farbe", ", ", "Groesse"],
			"article_unit": ["Menge"],
			"article_ean": [""]
		}],
		"conditional_and": [
			["trading_good", "1", ["article_name", "Lagerungsschiene|orthese|helm|headmaster"]]
		]
	}
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
		"add": {
			"trading_good": "0"
		},
		"conditional_and": [
			["trading_good", "1", ["name", "Compression|Weste|Peronäusschiene|Stax|liner"]]
		],
		"rewrite": [{
			"article_no": ["artno"],
			"article_name": ["name"],
			"article_unit": ["unit"],
			"article_ean": [""]
		}]
	}
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
		"add": {
			"trading_good": "0"
		},
		"rewrite": [{
			"article_no": ["Art-Nr"],
			"article_name": ["Bezeichnung1", ", ", "Bezeichnung2"],
			"article_unit": ["ME"],
			"article_ean": ["EAN_GTIN"]
		}],
		"conditional_and": [
			["trading_good", "1", ["article_name", "stuhl|hilfe|wagen|rollator|stock|gehstütze|achselstütze|toiletten|gehgestell|vierfuß|fischer"]]
		]
	}
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
		"add": {
			"trading_good": "1"
		},
		"rewrite": [{
			"article_no": ["Artikelnummer"],
			"article_name": ["Bezeichnung"],
			"article_unit": ["Einheit"],
			"article_ean": ["EAN"]
		}]
	}
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
		"add": {
			"trading_good": "0"
		},
		"conditional_and": [
			["trading_good", "1", ["Artikelbezeichnung", "kissen|handschuh|extensionsschiene|finger.*?schiene|orthese|protector|stützschiene|handgelenkschiene|handschuh|TAP-Schiene|urias|buddy loop|comfy"]]
		],
		"rewrite": [{
			"article_no": ["Artikelnummer "],
			"article_name": ["Artikelbezeichnung"],
			"article_unit": ["Einheit"],
			"article_ean": [""]
		}]
	}
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
		"add": {
			"trading_good": "0"
		},
		"rewrite": [{
			"article_no": ["Artikelnummer"],
			"article_name": ["Artikelbezeichnung", ", ", "Artikelbezeichnung 2"],
			"article_unit": ["Basiseinheit"],
			"article_ean": [""]
		}],
		"conditional_and": [
			["trading_good", "1", ["article_name", "einlage|schuh|orthese"]]
		]
	}
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
		"add": {
			"trading_good": "1",
			"has_expiry_date": "1"
		},
		"rewrite": [{
			"article_no": ["Bestell-Nr"],
			"article_name": ["ArtikelBez1", ", ", "ArtikelBez2", ", ", "Seite", ", ", "Farbe", ", ", "Groesse"],
			"article_unit": ["ME"],
			"article_ean": ["EAN_CODE"]
		}]
	}
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
		"add": {
			"trading_good": "0"
		},
		"conditional_and": [
			["trading_good", "1", ["Bezeichnung", "fersenkeil|schutzhülle|einlagen|philadelphia|clearsil|extensionsorthese|contexgel|comfortsil|primosil|skincaresil|classicsil|ak-control|tl bandage|control4sil|walker|yale|support|achillomax|genumax|spreizhose|knieschiene|schuh|kompressionsstumpstrumpf"]]
		],
		"rewrite": [{
			"article_no": ["Artikelnummer"],
			"article_name": ["Bezeichnung"],
			"article_unit": ["Einheit"],
			"article_ean": ["GTIN-Code"]
		}]
	}
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
		"add": {
			"trading_good": "0"
		},
		"rewrite": [{
			"article_no": ["Bestellnr"],
			"article_name": ["Artikelbezeichnung 1", ", ", "Artikelbezeichnung 2", ", ", "Artikelbezeichnung 3"],
			"article_unit": ["Verpackung1"],
			"article_ean": [""]
		}],
		"conditional_and": [
			["trading_good", "1", ["article_name", "WS-Bandage|Wirbelsäulenbandage|Damenhosenbandage|tigges-.+set|Lumbal.*?orthese|t-flex|BWS|Lumbalbandage"]]
		]
	}
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
		"add": {
			"trading_good": "1"
		},
		"rewrite": [{
			"article_no": ["Artikelnr"],
			"article_name": ["Artikelbeschreibung"],
			"article_unit": [""],
			"article_ean": [""]
		}]
	}
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
		"add": {
			"trading_good": "0"
		},
		"conditional_and": [
			["trading_good", "1", ["Beschreibung", "^bob|^daho|liner|philadelphia"]]
		],
		"rewrite": [{
			"article_no": ["Artikel-Nr"],
			"article_name": ["Beschreibung"],
			"article_unit": ["ME"],
			"article_ean": ["EAN"]
		}]
	}
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
		"add": {
			"trading_good": "0"
		},
		"rewrite": [{
			"article_no": ["Artikelnummer", " ", "Farbe Groesse"],
			"article_name": ["Artikelbez 1", " ", "Artikelbez 2"],
			"article_unit": [""],
			"article_ean": ["EAN"]
		}],
		"conditional_and": [
			["trading_good", "1", ["article_name", "Arthrodesenkissen"]]
		]
	}
}
```