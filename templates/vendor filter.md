> ean/gtin may have to be formatted as number or fracture before resaving as csv to avoid being displayed as exponential function within ms-office

# content
* ***[plan b](#plan-b-no-usable-list-or-no-vendor-response)***
* [aet](#aet)
* [albrecht](#albrecht)
* [amt](#amt)
* [arthroven](#arthroven)
* [aspen](#aspen)
* [basko](#basko)
* [blatchford](#blatchford)
* [bort](#bort)
* [busch](#busch)
* [caroli](#caroli)
* [cosmesil](#cosmesil)
* [darco](#darco)
* [erkodent](#erkodent)
* [feet control](#feet-control)
* [fior gentz](#fior-gentz)
* [gottinger](#gottinger)
* [ihle](#ihle)
* [juzo](#juzo)
* [kowsky](#kowski)
* [lohmann und rauscher](#lohmann-und-rauscher)
* [medi](#medi)
* [minke](#minke)
* [mmib](#mmib)
* [neatec](#neatec)
* [nowecare](#nowecare)
* [nowecor](#nowecor)
* [oessur](#oessur)
* [ofa](#ofa)
* [ortho reha neuhof](#ortho-reha-neuhof)
* [ortho systems](#ortho-systems)
* [orthoservice](#orthoservice)
* [otto bock](#otto-bock)
* [perpedes](#perpedes)
* [pochert](#pochert)
* [polyform*](#polyform)
* [protheseus](#protheseus)
* [prowalk](#prowalk)
* [rebotec](#rebotec)
* [rehaforum](#rehaforum)
* [ruckgaber*](#ruckgaber)
* [russka](#russka)
* [schein](#schein)
* [sporlastic](#sporlastic)
* [storitec*](#storitec)
* [streifeneder](#streifeneder)
* [taska](#taska)
* [thuasne*](#thuasne)
* [tigges](#tigges)
* [triconmed](#triconmed)
* [uniprox](#uniprox)
* [werkmeister](#werkmeister)

### plan b: no usable list or no vendor response
generate csv-lists from your erp-export with the following csv-filter. data is groomed as accurate or shitty as within your erp! it is highly recommended to edit
* trading_good,
* has_expiry_date and
* special_attention

in the output files if it is not possible to edit the following filter according to your erp-data concerning these attributes. template matches german optadata/eva viva output. translations must be updated to your custom erp entries.
```json
{
	"useCase": "Sort article lists from erp-system to csv-files for vendors that do not provide pricelists",
	"postProcessing": "Import result lists to respective vendor with default filter",
	"filesetting": {
		"source": "ARTIKELMANAGER\\.CSV",
		"headerrowindex": 1,
		"destination": "article_list.csv",
		"columns": [
			"REFERENZ",
			"LIEFERANTENNAME",
			"BEZEICHNUNG",
			"BESTELL_NUMMER",
			"BESTELL_EINHEIT",
			"SUCHBEGRIFF_1",
			"SUCHBEGRIFF_2",
			"SUCHBEGRIFF_3",
			"SUCHBEGRIFF_4",
			"SUCHBEGRIFF_5",
			"Zusatzinformation",
			"MODELL_NAME",
			"GROESSEN_NAME",
			"FARBEN_NAME",
			"BESTELLSTOP",
			"STATUS",
			"EAN"
		]
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "keep if all general patterns match",
			"keep": false,
			"match": {
				"any": {
					"STATUS": "true",
					"BESTELLSTOP": "true",
					"LIEFERANTENNAME": "^$|(aet|albrecht|amt|arthroven|aspen|basko|blatchford|bort|busch|caroli|cosmesil|darco|erkodent|feet.control|fior.+gentz|gottinger|ihle|kowsky|lohmann.+rauscher|medi|minke|mmib|neatec|nowecare|nowecor|ssur|ortho.reha.neuhof|ortho.systems|orthoservice|otto.bock|perpedes|pochert|polyform|protheseus|prowalk|rebotec|rehaforum|ruckgaber|russka|schein|sporlastic|streifeneder|taska|tigges|triconmed|uniprox|werkmeister)"
				}
			}
		},
		{
			"apply": "filter_by_duplicates",
			"comment": "keep amount of duplicates of column value, ordered by another concatenated column values (asc/desc)",
			"duplicates": {
				"orderby": ["REFERENZ"],
				"descending": false,
				"column": "REFERENZ",
				"amount": 1
			}
		}
	],
	"modify": {
		"translate": {
			"BESTELL_EINHEIT": "BESTELL_EINHEIT"
		},
		"replace": [
			[null, "\"", ""],
			[null, ";", ","]
		],
		"rewrite": [
			{
				"article_no": ["BESTELL_NUMMER"],
				"article_name": ["BEZEICHNUNG", " ", "Zusatzinformation", " ", "MODELL_NAME", " ", "GROESSEN_NAME", " ", "FARBEN_NAME"],
				"article_unit": ["BESTELL_EINHEIT"],
				"article_ean": ["EAN"]}
			],
		"add": {
			"trading_good": "",
			"has_expiry_date": "",
			"special_attention": ""
		},
		"remove": [
			"REFERENZ",
			"BEZEICHNUNG",
			"BESTELL_NUMMER",
			"BESTELL_EINHEIT",
			"SUCHBEGRIFF_1",
			"SUCHBEGRIFF_2",
			"SUCHBEGRIFF_3",
			"SUCHBEGRIFF_4",
			"SUCHBEGRIFF_5",
			"Zusatzinformation",
			"MODELL_NAME",
			"GROESSEN_NAME",
			"FARBEN_NAME",
			"BESTELLSTOP",
			"STATUS",
			"EAN"
		]
	},
	"split": {
		"LIEFERANTENNAME": ".*"
	},
	"translations": {
		"BESTELL_EINHEIT": {
			"1": "Stück",
			"2": "Paar",
			"4": "Meter",
			"5": "Set",
			"6": "Liter",
			"7": "qm",
			"8": "Sack",
			"9": "Satz",
			"10": "Kanister",
			"11": "Stunde",
			"12": "Packung",
			"13": "Platte",
			"14": "Rolle",
			"15": "kg",
			"16": "Monat",
			"17": "Stange",
			"18": "Flasche",
			"19": "KG 4,6 ",
			"21": "kg á 15 m",
			"22": "kg á 16 m",
			"23": "Pack á 100 Stück",
			"24": "Packung á 10 Stück",
			"25": "kg á 23,8m",
			"26": "kg á 18m",
			"27": "kg á 38,5m",
			"28": "kg á 32m",
			"29": "kg á 31,2m",
			"30": "kg á 35,7m",
			"31": "kg á 32,2m",
			"32": "kg á 26,3m",
			"33": "Rolle á 50 m",
			"34": "Packung á 6 Stück",
			"35": "Packung á 200 Stück",
			"36": "Packung á 12 Stück",
			"37": "Kanne á 4 KG",
			"38": "Packung á 100 Stück",
			"39": "Rolle á 25m",
			"40": "Woche",
			"41": "Platte á 1,49 m²",
			"42": "Sack á 30 kg",
			"43": "Platte á 1,21 m²",
			"44": "Platte á 0,28 m²",
			"45": "Platte á 0,24 m²",
			"46": "Platte á 2,6 qm",
			"47": "Platte á 2 qm",
			"48": "Packung á 1000 Stück",
			"49": "Platte á 3 qm",
			"50": "Rolle á 30 m",
			"51": "Dose á 0,6 kg",
			"52": "Platte á 0,57 qm",
			"53": "Kanister á 1,57 kg",
			"54": "Kanister á 3,135 kg",
			"55": "Kanister á 0,3 kg",
			"56": "Kanister á 0,77 kg",
			"57": "Kanister á 1,57 kg",
			"58": "Kanister á 0,45 kg",
			"59": "Kanister á 0,9 kg",
			"60": "Kanister á 0,75 kg",
			"61": "Kanister á 1,9 kg",
			"62": "Rolle á 35,7 m",
			"63": "Haut",
			"64": "Platte á 0,56 qm",
			"65": "Matte á 20 qm",
			"66": "Kanister á 25 kg",
			"67": "Bogen á 1,5qm",
			"68": "Platte á 1,37 qm",
			"69": "Rolle á 59,4 qm",
			"70": "Packung á 500 Stück",
			"71": "Stück á 1,5 m",
			"72": "Schlauch á 2,2 qm",
			"73": "lfd Meter á 1,6 qm",
			"74": "Stück á 5 m",
			"75": "Packung á 4,6 kg",
			"76": "Rolle á 10 m",
			"77": "X 100 Stück",
			"78": "Rolle á 0,5 kg",
			"79": "85 kg",
			"80": "Packung á 25 Stück",
			"81": "Packung á 50 Stück",
			"82": "lfm.",
			"83": "Gebinde",
			"84": "Pack. á 100 Stück",
			"85": "Pack. á 500 Stück",
			"86": "Platte á 1,28 qm",
			"87": "Kanister á 0,7 kg",
			"88": "Tube á 0,18 Kg",
			"89": "Pack á 500 Stück",
			"90": "Pack á 100 Stück",
			"91": "Stange a 6 m",
			"92": "Platte á 0,5 qm",
			"93": "Platte á 0,94 qm",
			"94": "Rolle á 100 m",
			"95": "Platte 1,10 x 0,95 m",
			"96": "Karton á 20 Stück",
			"97": "lfm á 1,4 qm",
			"98": "Packung á 16 Rollen",
			"99": "Platte á 1,5 qm",
			"100": "0,25 L",
			"101": "Packung mit 5 x 2 Stück",
			"102": "Flasche mit 0,25 L",
			"103": "Rolle á 15 Meter",
			"104": "Platte á 0,45 qm",
			"105": "Haut á 0,5 m²",
			"106": "Sack á 100 L",
			"107": "St. á 500 ml",
			"108": "St. á 425 g",
			"109": "Sack á 25 kg",
			"110": "lfm. á 1,30",
			"111": "10 Rollen á 1000 mtr.",
			"112": "Pack. á 2 St.",
			"113": "Packung á 5 Kg",
			"114": "Platte á 0,96 qm",
			"115": "Karton á 12 Dosen",
			"116": "Kanister á 3,8 Kg",
			"117": "10x1000 Meter/Rolle",
			"118": "Packung á 4 Stück",
			"119": "Rolle á 5 Meter",
			"120": "Rolle á 250 Meter",
			"121": "Rolle á 100 m²",
			"122": "Beutel á 5 Stück",
			"123": "Kanister á 10 kg",
			"124": "Platte á 0,4 qm",
			"125": "Kanister á 4,6 kg",
			"126": "Packung á 0,15 Kg",
			"127": "Packung á 8 Paar",
			"128": "Packung á 12 Paar",
			"129": "Packung á 6 Paar",
			"130": "Platte á 1,14 qm",
			"131": "Platte á 0,8 qm",
			"132": "Platte á 1,05 qm",
			"133": "Gramm",
			"134": "Stück á 1 m²",
			"135": "Platte á 0,52 m²",
			"136": "Platte á 0,63 m²",
			"137": "Platte á 0,64 m²",
			"138": "Rolle á 7 Stück",
			"139": "Platte á 0,47 m²",
			"140": "Set á 6 St.",
			"141": "23 cm",
			"142": "Platte á 0,3 m²",
			"143": "Rolle á 3 Kg",
			"144": "Dose",
			"145": "Packung á 5 Stück",
			"146": "Stück - Rechts",
			"147": "Stück - Links",
			"148": "Packung á 10 Stück",
			"149": "Platte á 0,75 m²",
			"150": "Platte á 0,2 m²",
			"151": "Kanister á 5 Liter",
			"152": "Haut á ca. 4 m²",
			"153": "Folie ",
			"154": "Karton á 6 Dosen",
			"155": "Karton á 24 Dosen",
			"156": "Karton ",
			"157": "Platte á 1 m²",
			"158": "Flasche á 100 ml",
			"159": "Karton á 4,8 Kg",
			"160": "Karton á 0,3 Kg",
			"161": "Stück á 7,62 m",
			"162": "Platte á 11 Kg",
			"163": "Platte á 5,50 Kg",
			"164": "Rolle á 20 Meter",
			"165": "Rolle á 14,5 Meter",
			"166": "Einheit zu 6,8 Kg",
			"167": "Rolle á 16,5 Meter",
			"168": "Pack á 8 Kartuschen",
			"169": "Stück á 6 Meter",
			"170": "Stück á 5 Meter",
			"171": "Stange á 2 Meter",
			"172": "Dose á 1 Kg",
			"173": "Rolle á 7 Kg",
			"174": "Stück á 3 Meter",
			"175": "Platte á 0,16 qm",
			"176": "Rolle á 4 Meter",
			"177": "Kanister á 0,85 kg",
			"178": "Tube á 0,5 kg",
			"179": "Pack. á 8 Stück",
			"180": "Ständer mit 7 Stück",
			"181": "Flasche á 0,5 Liter",
			"182": "Stück á 10 Meter",
			"183": "Rolle á 70 Meter",
			"184": "Set mit 2 Tuben",
			"185": "Pack. á 20 Stück",
			"186": "Kanister á 8,4 Kg",
			"187": "Dose á 0,96 Kg",
			"188": "Dose á 0,8 Kg",
			"189": "Stange á 3 Meter",
			"190": "Kanister á 8 Kg",
			"191": "Kanister á 0,95 Kg",
			"192": "Kanister á 0,68 Kg",
			"193": "Flasche á 0,05 Kg",
			"194": "Kanister á 1,53 Kg",
			"195": "Glas á 0,25 Kg",
			"196": "Stück á 0,5 Kg",
			"197": "Pack. á 144 St.",
			"198": "Karton á 3 kg",
			"199": "Pack á 10 qm",
			"200": "Karton á 150 Stück",
			"201": "Tube á 0,05 Kg",
			"202": "Dose á 200 ml",
			"203": "Rolle á 33 Meter",
			"204": "Rolle á 40 Meter",
			"205": "Rolle á 250 Meter",
			"206": "Haut a 1,2 qm",
			"207": "Stück á 1,5 qm",
			"208": "Matte á 10 qm",
			"209": "Flasche á 1 Kg",
			"210": "Kanister á 5 Kg",
			"211": "Platte á 0,36 m²",
			"212": "Platte á 1,2 m²",
			"213": "Platte á 0,7 m²",
			"214": "Pack. á 3 Stück",
			"215": "Dose a 1 L",
			"216": "Platte á 0,6 qm",
			"217": "Flasche a 0,1 Kg",
			"218": "Eimer á 2,95 Kg",
			"219": "Rolle á 36 qm",
			"220": "Stück á 0,075 Kg",
			"221": "Platte á 0,27 m²",
			"222": "Pack. á 40 St.",
			"223": "Rolle á 10 m²",
			"224": "Pack. á 30 Stück",
			"225": "Karton a 10 Stück",
			"226": "Stange á ca. 1 Kg",
			"227": "Set mit 2 x 0,5 Ltr.",
			"228": "Stück",
			"229": "Rolle á 300 Mtr.",
			"230": "Packung á 7 Paar",
			"231": "Bogen á 1,6 qm",
			"232": "Rolle á 50 Meter",
			"233": "2 kg",
			"234": "Rolle á 150 Meter",
			"235": "Karton á 20 Kg",
			"236": "Pack á 2,5kg",
			"237": "Platte á 0,25 qm",
			"238": "Pack. á 7,26 Kg",
			"239": "Pack. á 300 Stück",
			"240": "Pack. á 9 Paar",
			"241": "Pack. á 62 Stück",
			"242": "Stück á 2 m",
			"243": "Platte á 0,06 qm",
			"244": "Platte á 0,90 qm",
			"245": "Platte á 7 qm",
			"246": "Eimer",
			"247": "Platte á 2,8 qm",
			"248": "Packung á 250 Stück",
			"240": "Rolle á 500 Stück",
			"250": "Platte á 1,4 qm",
			"2451": "Packung á 8 Stück"
		}
	}
}
```

import with default filter
```js
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": [
			"article_no",
			"article_name",
			"article_unit", 
			"article_ean", 
			"trading_good",
			"has_expiry_date",
			"special_attention"
		]
	}
}
```

### aet
rewrite header (delivery unit), delete animal-aids, intermediate headers and all rows above header, append aid name to +options
```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["schwarz", "Bandagenbezeichnung", "Liefereinheit"]
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "delete anything but articles",
			"keep": true,
			"match": {
				"all": {
					"schwarz": ".+"
				}
			}
		}
	],
	"modify": {
		"add": {
			"trading_good": "1",
			"has_expiry_date": "0"
		},
		"rewrite": [{
			"article_no": ["schwarz"],
			"article_name": ["Bandagenbezeichnung"],
			"article_unit": ["Liefereinheit"],
			"article_ean": []
		}]
	}
}
```

[content](#content)

### albrecht
```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["Artikelbezeichnung", "Artikelnummer", "EAN"]
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "delete anything but articles",
			"keep": true,
			"match": {
				"all": {
					"Artikelnummer": ".+"
				}
			}
		}
	],
	"modify": {
		"add": {
			"trading_good": "1",
			"has_expiry_date": "0",
			"article_unit": "Stk"
		},
		"rewrite": [{
			"article_no": ["Artikelnummer"],
			"article_name": ["Artikelbezeichnung"],
			"article_ean": ["EAN"]
		}]
	}
}
```

[content](#content)

### amt
```json
{
	"filesetting": {
		"columns": ["Lager-Artikelnummer AMT", "Bezeichnung", "VE", "EAN/GTIN/UPC"],
		"headerrowindex": 2
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "filter articles",
			"keep": true,
			"match": {
				"all": {
					"Bezeichnung": "silicon|silipos"
				}
			}
		}
	],
	"modify": {
		"add": {
			"trading_good": "1",
			"special_attention": "1",
			"has_expiry_date": "0"
		},
		"rewrite": [{
			"article_no": ["Lager-Artikelnummer AMT"],
			"article_name": ["Bezeichnung"],
			"article_unit": ["VE"],
			"article_ean": ["EAN/GTIN/UPC"]
		}]
	}
}
```

[content](#content)

### arthroven
```json
{
	"filesetting": {
		"columns": ["Artikelnr.", "Artikelbeschreibung", "Einheit", "Barcode"]
	},
	"modify": {
		"add": {
			"trading_good": "1",
			"has_expiry_date": "0"
		},
		"rewrite": [{
			"article_no": ["Artikelnr."],
			"article_name": ["Artikelbeschreibung"],
			"article_unit": ["Einheit"],
			"article_ean": ["Barcode"]
		}]
	}
}
```

[content](#content)

### aspen
```json
{
	"filesetting": {
		"columns": ["Artikel-Nr.", "Artikelbeschreibung", "EAN/GTIN"],
		"headerrowindex": 1
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "filter articles",
			"keep": true,
			"match": {
				"all": {
					"Artikel-Nr.": ".+"
				}
			}
		}
	],
	"modify": {
		"add": {
			"trading_good": "1"
		},
		"rewrite": [{
			"article_no": ["Artikel-Nr."],
			"article_name": ["Artikelbeschreibung"],
			"article_unit": [""],
			"article_ean": ["EAN/GTIN"]
		}]
	}
}
```

[content](#content)

### basko
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
		"conditional_or": [
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

[content](#content)

### blatchford
```json
{
	"filesetting": {
		"columns": ["Artikel", "Artikelbeschreibung"]
	},
	"modify": {
		"add": {
			"trading_good": "0",
			"has_expiry_date": "0"
		},
		"rewrite": [{
			"article_no": ["Artikel"],
			"article_name": ["Artikelbeschreibung"],
			"article_unit": [""],
			"article_ean": [""]
		}]
	}
}
```

[content](#content)

### bort
```json
{
	"filesetting": {
		"columns": ["Artikelnr.", "Bezeichnung", "Größe", "Farbe", "Seite / Ausführung", "Einheit", "EAN", "CCL"]
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "filter articles",
			"keep": false,
			"match": {
				"any": {
					"CCL": ".+",
					"Bezeichnung": "climacare|aktiven|displ..|easylife|knet|hilfe",
					"Größe": "MAß"
				}
			}
		}
	],
	"modify": {
		"add": {
			"trading_good": "1",
			"has_expiry_date": "0"
		},
		"conditional_or": [
			["trading_good", "0", ["Bezeichnung", "ersatz|für|tausch|bezug|luft"], ["Größe", ""]]
		],
		"remove": ["CCL"],
		"rewrite": [{
			"article_no": ["Artikelnr."],
			"article_name": ["Bezeichnung", ", ", "Größe", ", ", "Farbe", ", ", "Seite / Ausführung"],
			"article_unit": ["Einheit"],
			"article_ean": ["EAN"]
		}]
	}
}
```

[content](#content)

### busch
add article_no header
```json
{
	"filesetting": {
		"columns": ["article_no", "Bezeichnung", "Einheit"],
		"headerrowindex": 14
	},
	"add": {
		"special_attention": 0
	},
	"modify": {
		"conditional_or": [
			["special_attention", "1", ["Bezeichnung", "Ziegen-Narbenleder|macanta|liegeschalen|neoprene|nappa|multiform"]]
		],
		"rewrite": [{
			"article_no": ["article_no"],
			"article_name": ["Bezeichnung"],
			"article_unit": ["Einheit"],
			"article_ean": [""]
		}]
	}
}
```

[content](#content)

### caroli
```json
{
	"filesetting": {
		"headerrowindex": 8,
		"columns": ["Artikelnummer", "Bezeichnung", "GTIN"]
	},
	"modify": {
		"add": {
			"special_attention": "0"
		},
		"conditional_or": [
			["special_attention", "1", ["Bezeichnung", "einfa[sß]+band|samtband"]]
		],
		"rewrite": [{
			"article_no": ["NeueArtikelNummer"],
			"article_name": ["Bezeichnung"],
			"article_unit": [""],
			"article_ean": ["GTIN"]
		}]
	}
}
```

[content](#content)

### cosmesil
add "article_no", "article_name" to first row
```json
{
	"filesetting": {
		"columns": ["article_no", "article_name"],
		"headerrowindex": 0
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "filter articles",
			"keep": true,
			"match": {
				"all": {
					"article_no": ".+",
					"article_name": "alsil|techsil"
				}
			}
		}
	],
	"modify": {
		"add": {
			"special_attention": "1",
			"has_expiry_date": "0"
		},
		"rewrite": [{
			"article_no": ["article_no"],
			"article_name": ["article_name"],
			"article_unit": [""],
			"article_ean": [""]
		}]
	}
}
```

[content](#content)

### darco
```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["Kategorie", "Produktname", "Produktbezeichnung", "Farbe", "Größenbez.", "Artikel-Nr.", "VE ", "EAN 13"]
	},
	"modify": {
		"add": {
			"trading_good": "1"
		},
		"conditional_or": [
			["trading_good", "0", ["Kategorie", "zubehör|tapes|Sohlensysteme"]]
		],
		"remove": ["Kategorie"],
		"rewrite": [{
			"article_no": ["Artikel-Nr"],
			"article_name": ["Produktname", ", ", "Produktbezeichnung", ", ", "Farbe", ", ", "Größenbez."],
			"article_unit": ["VE "],
			"article_ean": ["EAN 13"]
		}]
	}
}
```

[content](#content)

### erkodent
```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["Bestellnr.", "Beschreibung", "Inhalt"]
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "filter articles",
			"keep": false,
			"match": {
				"any": {
					"Beschreibung": "^$|einlage"
				}
			}
		}
	],
	"modify": {
		"rewrite": [{
			"article_no": ["Bestellnr."],
			"article_name": ["Beschreibung"],
			"article_unit": ["Inhalt"],
			"article_ean": [""]
		}]
	}
}
```

[content](#content)

### feet control
add header
```json
{
	"filesetting": {
		"columns": ["artnr"]
	},
	"modify": {
		"add": {
			"has_expiry_date": "0"
		},
		"conditional_or": [
			["has_expiry_date", "1", ["artnr", "s35sc11_|sansc35_"]]
		],
		"rewrite": [{
			"article_no": ["artnr"],
			"article_name": [""],
			"article_unit": [""],
			"article_ean": [""]
		}]
	}
}
```

[content](#content)

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
		"conditional_or": [
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

[content](#content)

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

[content](#content)

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
		"conditional_or": [
			["trading_good", "1", ["article_name", "socke|strumpf|ckchen|kniestr|shirt|body|hose"]]
		],
		"rewrite": [{
			"article_no": ["Nr"],
			"article_name": ["Beschreibung 1", ", ", "Beschreibung 2", ", ", "Farbe", ", ", "Groesse"],
			"article_unit": ["Einheiten"],
			"article_ean": [""]
		}]
	}
}
```

[content](#content)

### juzo
> unusable list for having too much entries. requires alternate implementation

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

[content](#content)

### kowski
```json
{
	"filesetting": {
		"columns": ["Plangruppe", "Artikelnummer", "Artikelbezeichnung", "Mengeneinheit"],
		"headerrowindex": 9
	},
	"modify": {
		"add": {
			"trading_good": "1"
		},
		"conditional_or": [
			["trading_good", "0", ["Plangruppe", "Ersatzteile|SOA"]]
		],
		"remove": ["Plangruppe"],
		"rewrite": [{
			"article_no": ["Artikelnummer"],
			"article_name": ["Artikelbezeichnung"],
			"article_unit": ["Mengeneinheit"],
			"article_ean": [""]
		}]
	}
}
```

[content](#content)

### lohmann und rauscher
```json
{
	"filesetting": {
		"columns": ["Material", "Bezeichnung"]
	},
	"modify": {
		"add": {
			"has_expiry_date": "0"
		},
		"conditional_or": [
			["has_expiry_date", "1", ["Bezeichnung", "trikotschlauch"]]
		],
		"rewrite": [{
			"article_no": ["Material"],
			"article_name": ["Bezeichnung"],
			"article_unit": [""],
			"article_ean": [""]
		}]
	}
}
```

[content](#content)

### medi
replace . and specialchars in header

```json
{
	"filesetting": {
		"columns": ["ArtikelNr", "Bezeichnung", "Mengeneinh", "EAN-Nummer"]
	},
	"modify": {
		"add": {
			"trading_good": "1",
			"special_attention": "0"
		},
		"conditional_or": [
			["special_attention", "1", ["Bezeichnung", "noppenhaft"]]
		],
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

[content](#content)

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
		"add": {
			"special_attention": "0"
		},
		"conditional_or": [
			["special_attention", "1", ["Staffel", "pryx|deusith"]]
		],
		"rewrite": [{
			"article_no": ["Artikelnummer"],
			"article_name": ["Staffel"],
			"article_unit": ["Einheit"],
			"article_ean": [""]
		}]
	}
}
```

[content](#content)

### mmib
rewrite pricelist (concat first three columns to article number, paste as values, add name column, delete the rest)
```json
{
	"filesetting": {
		"columns": ["artnr", "name"]
	},
	"modify": {
		"add": {
			"special_attention": "1"
		},
		"rewrite": [{
			"article_no": ["artnr"],
			"article_name": ["name"],
			"article_unit": ["LFM"],
			"article_ean": [""]
		}]
	}
}
```

[content](#content)

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

[content](#content)

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

[content](#content)

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
		"conditional_or": [
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

[content](#content)

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
			"trading_good": "0",
			"has_expiry_date": "0"
		},
		"conditional_or": [
			["trading_good", "1", ["BEZEICHNUNG", "miami|occian|papoose|philadelphia|formfit|orthese|unloader|rebound|oa ease|firststep|cti|afo|keeogo"]],
			["has_expiry_date", "1", ["BEZEICHNUNG", "solvent"]]
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

[content](#content)

### ofa
> unusable list for having too much entries. requires alternate implementation

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
		"conditional_or": [
			["trading_good", "0", ["article_name", "Anti-Rutsch-Beschichtung"]]
		]
	}
}
```

[content](#content)

### ortho reha neuhof
line end is mandatory on gloves particular article_no, otherwise the filter matches recursively and having a memory overflow.
```json
{
	"filesetting": {
		"headerrowindex": 6,
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
			"has_expiry_date": "0",
			"special_attention": "0"
		},
		"conditional_or": [
			["trading_good", "1", ["Bezeichnung", "liner|kniekappe|strumpf|wilmer|fersenkissen|tobisil|gelenksorthese|schuh|peroneusschiene|walker|kniebandage|knieorthese|lagerungsschiene|rotationsorthese|spreizhose|daumenorthese|handgelenksorthese|immobilisations|immobilisierung|ellenbogen.*bandage|ellenbogenorthese|schulterbandage|humerusorthese|cervicalorthese|rippengürtel|mieder|lumbalbandage|lumbalorthese|elite pro|rumpforthese|extensionsorthese|rückenorthese|"]],
			["has_expiry_date", "1", ["Bezeichnung", "neofast"]],
			["special_attention", "1", ["Bezeichnung", "extrasoft|plastazote|multisoft|lunairmed|evazote|lunalastik|lunasoft|orthosoft|lunacell|neopren|syncrosoft|htv silikon|silikonfarbe|polyethylen|polypropylen|ppc|laminierharz|gießharz|drell|plex|cat-flex"]]
		],
		"replace":[
			["Art.Nr.", "(501[BDKJ].+)(L$)", "L1", "L1-2", "L2", "L2-3", "L3", "L3-4", "L4", "L4-5", "L5", "L5-6", "L6", "L6-7", "L7", "L7-8", "L8", "L8-9", "L9", "LC0", "LC0-C1", "LC1", "LC1-C2", "LC2", "LC2-C3", "LC3", "LC3-C4", "LC4", "LC4-C5", "LC5", "LA1", "LA1-A2", "LA2", "LA2-A3", "LA3", "LA3-A4", "LA4"],
			["Art.Nr.", "(501[BDKJ].+)(R$)", "R1", "R1-2", "R2", "R2-3", "R3", "R3-4", "R4", "R4-5", "R5", "R5-6", "R6", "R6-7", "R7", "R7-8", "R8", "R8-9", "R9", "RC0", "RC0-C1", "RC1", "RC1-C2", "RC2", "RC2-C3", "RC3", "RC3-C4", "RC4", "RC4-C5", "RC5", "RA1", "RA1-A2", "RA2", "RA2-A3", "RA3", "RA3-A4", "RA4"]
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

[content](#content)

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

[content](#content)

### orthoservice
```json
{
	"filesetting": {
		"columns": ["Artikel-NR", "Bezeichnung1 Artikelname", "Bezeichnung2 = Farbe (falls vorhanden)", "Bezeichnung3 = Langtext (kein muss)", "Bezeichnung4 = Dimensionstext (Größe)", "EAN", "HMV-NR "]
	},
	"modify": {
		"add": {
			"trading_good": "1",
			"has_expiry_date": "0"
		},
		"conditional_or": [
			["trading_good", "0", ["HMV-NR ", "^komponente|^zubehör"], ["Bezeichnung3 = Langtext (kein muss)", "zubehör"]]
		],
		"remove": ["HMV-NR "],
		"rewrite": [{
			"article_no": ["Artikel-NR"],
			"article_name": ["Bezeichnung1 Artikelname", ", ", "Bezeichnung2 = Farbe (falls vorhanden)", ", ", "Bezeichnung3 = Langtext (kein muss)", ", ", "Bezeichnung4 = Dimensionstext (Größe)"],
			"article_unit": [""],
			"article_ean": ["EAN"]
		}]
	}
}
```

[content](#content)

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
			"has_expiry_date": "0",
			"special_attention": "0"
		},
		"conditional_or": [
			["trading_good", "1", ["Materialtext", "knee comfort|strumpf|tübinger|necky|walk.*on|genu|patella|liner|malleo|agilium|proflex|cosa|smartspine"]],
			["has_expiry_date", "1", ["Material", "633s2|617h.+|617p\\d\\d.+|617s\\d=|85h.+|636K.+|616T111|453h10|646m453"]],
			["special_attention", "1", ["Materialtext", "ComforTex|pastasil|plastazote|trolen|innenlack|thermolyn"]]
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

[content](#content)

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
		"conditional_or": [
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

[content](#content)

### pochert
```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["Artikelnummer", "Matchcode", "Menge", "VPE"]
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "drop price scale",
			"keep": false,
			"match": {
				"any": {
					"Menge": ".+",
					"Matchcode": "^$|einl[ea]ge|schuh|sandale|sicherheit|rohl.|gr\\.\\d|schild|plakat|aufsteller|muster|katalog|bilderrahmen|kosten|schlag|ziel|leisten|deckso|futter|schl[oö][ßs]{1,}|abs[aä]tz|sohle|fleck|gürtel"
				}
			}
		}
	],
	"modify": {
		"add": {
			"trading_good": "0",
			"special_attention": "0",
			"expiry_date": "0"
		},
		"conditional_or": [
			["trading_good", "1", ["Matchcode", "Compression|Weste|Peronäusschiene|Stax|liner"]]
			["special_attention", "1", ["Matchcode", "prosil"]]
			["expiry_date", "1", ["Matchcode", "prosil"]]
		],
		"remove": ["Menge"],
		"rewrite": [{
			"article_no": ["Artikelnummer"],
			"article_name": ["Matchcode"],
			"article_unit": ["VPE"],
			"article_ean": [""]
		}]
	}
}
```

[content](#content)

### polyform

[content](#content)

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
		"conditional_or": [
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

[content](#content)

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
		"conditional_or": [
			["trading_good", "1", ["article_name", "Lagerungsschiene|orthese|helm|headmaster"]]
		],
		"rewrite": [{
			"article_no": ["ArtikelNummer"],
			"article_name": ["Bezeichnung 1", ", ", "Bezeichnung 2", ", ", "Farbe", ", ", "Groesse"],
			"article_unit": ["Menge"],
			"article_ean": [""]
		}]
	}
}
```

[content](#content)

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
		"conditional_or": [
			["trading_good", "1", ["article_name", "stuhl|hilfe|wagen|rollator|stock|gehstütze|achselstütze|toiletten|gehgestell|vierfuß|fischer"]]
		],
		"rewrite": [{
			"article_no": ["Art-Nr"],
			"article_name": ["Bezeichnung1", ", ", "Bezeichnung2"],
			"article_unit": ["ME"],
			"article_ean": ["EAN_GTIN"]
		}]
	}
}
```

[content](#content)

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

[content](#content)

### ruckgaber

[content](#content)

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
		"conditional_or": [
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

[content](#content)

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
		"conditional_or": [
			["trading_good", "1", ["article_name", "einlage|schuh|orthese"]]
		],
		"rewrite": [{
			"article_no": ["Artikelnummer"],
			"article_name": ["Artikelbezeichnung", ", ", "Artikelbezeichnung 2"],
			"article_unit": ["Basiseinheit"],
			"article_ean": [""]
		}]
	}
}
```

[content](#content)

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

[content](#content)

### storitec

[content](#content)

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
			"trading_good": "0",
			"has_expiry_date": "0",
			"special_attention": "0"
		},
		"conditional_or": [
			["trading_good", "1", ["Bezeichnung", "fersenkeil|schutzhülle|einlagen|philadelphia|clearsil|extensionsorthese|contexgel|comfortsil|primosil|skincaresil|classicsil|ak-control|tl bandage|control4sil|walker|yale|support|achillomax|genumax|spreizhose|knieschiene|schuh|kompressionsstumpstrumpf"]],
			["has_expiry_date", "1", ["Bezeichnung", "silikonspray|sekundenkleber"]],
			["special_attention", "1", ["Bezeichnung", "kon-gel|varioform|streifydur|streifylast|PET glasklar|streifyflex|drell|jaquard|satine|elastinova|bandagengurt|diabetikermaterial|multiform"]]
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

[content](#content)

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

[content](#content)

### thuasne
```json
{
	"filesetting": {
		"headerrowindex": 0,
		"columns": ["Artikel-Nummer", "Art.-Bez. 1 NEU", "Art.-Bez. 2 NEU", "GTIN", "Verpackungseinheit"]
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "keep if all general patterns match",
			"keep": false,
			"match": {
				"any": {
					"Art.-Bez. 1 NEU": "^BH |badeanzug|bikini|tankini|mamille|epithese"
				}
			}
		}
	],
	"modify": {
		"add": {
			"trading_good": "1",
			"special_attention": "0",
			"has_expiry_date": "1"
		},
		"conditional_or": [
			["trading_good", "0", ["Art.-Bez. 1 NEU", "COTTON SHORT STRETCH|LOMBAMUM|binden|spray|biplast|pelotte|kulanz|pauschale|wechselpolster|flexi-pads|ersatz|verlängerungs|haftpad"]],
			//["special_attention", "1", ["Art.-Bez. 1 NEU", ""]],
			//["has_expiry_date", "0", ["Art.-Bez. 1 NEU", ""]]
		],
		"rewrite": [{
			"article_no": ["Artikel-Nummer"],
			"article_name": ["Art.-Bez. 1 NEU", " ", "Art.-Bez. 2 NEU"],
			"article_unit": ["Verpackungseinheit"],
			"article_ean": ["GTIN"]
		}]
	}
}
```
[content](#content)

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

[content](#content)

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

[content](#content)

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
			"trading_good": "0",
			"has_expiry_date": "0",
			"special_attention": "0"
		},
		"conditional_or": [
			["trading_good", "1", ["Beschreibung", "^bob|^daho|liner|philadelphia"]],
			["special_attention", "1", ["Beschreibung", "thermoflex"]]
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

[content](#content)

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