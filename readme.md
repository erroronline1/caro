![CARO logo](media/favicon/windows11/SmallTile.scale-100.png)
# CARO - Cloud Assisted Record and Operation

## prerequisites
* php >= 8
* mysql or sql server (or some other database, but queries may have to be adjusted. as i chose pdo as database connectivity i hope this is possible)
* ssl (camera access for qr-scanner and serviceworkers don't work otherwise)
* vendor pricelists as csv-files [see details](#importing-vendor-pricelists)

tested server environments:
* apache [uniform server zero XV](https://uniformserver.com) with php 8.2, mysql 8.0.31
* microsoft iis with sql express (sql server 22)

tested devices:
* desktop pc win10 edge-browser
* notebook win11 firefox-browser
* smartphone android12 firefox-browser

## installation
* php.ini memory_limit ~2048M for processing of large csv-files, disable open_basedir at least for local iis for file handlers
* php.ini upload_max_filesize & post_max_size / applicationhost.config | web.config for iis according to your expected filesize for e.g. sharepoint- and csv-files ~128MB
* php.ini max_execution_time / fastCGI timeout (iis) ~ 300 for csv processing may take a while depending on your data amount
* php.ini enable extensions:
    * gd
    * gettext
    * mbstring
    * exif
    * pdo_odbc
    * zip
    * php_pdo_sqlsrv_82_nts_x64.dll (sqlsrv)
* my.ini (MySQL) max_allowed_packet = 100M / [SQL SERVER](https://learn.microsoft.com/en-us/sql/database-engine/configure-windows/configure-the-network-packet-size-server-configuration-option?view=sql-server-ver16) 32767
* manually set mime type for site-webmanifest as application/manifest+json for iis servers
* set up api/setup.ini, especially the used sql subset and its credentials, packagesize in byte according to sql-configuration
* run api/install.php, you will be redirected to the frontpage afterwards - no worries, in case of a rerun nothing will happen

## limitations and intended usecases

### setup
* setting the package size for the sql environment to a higher value than default is useful beside the packagesize within setup.ini. batch-queries are supposed to be split in chunks, but single queries with occasionally base64 encoded images might exceed the default limit

### system limitations
* notifications on new messages are as reliable as the timespan of a service-woker. which is short. therefore there will be an periodic fetch request with a tiny payload to wake it up once in a while - at least as long as the app is opened. there will be no implementation of push api to avoid third party usage and for lack of safari support
* the application caches requests. get requests return the latest version, which might not always be the recent system state but better than nothing. POST, PUT and DELETE requests however are stored within an indexedDB and executed once a successful GET request indicates reconnection to the server. this might lead to a delay but is better than nothing. however note that this only is reliable if the browser does not delete session content on closing. this is not a matter of the app but your system environment. you may have to contact your it department.
* cached post requests may insert the user name and entry date on processing. that is the logged in user on, and time of processing on the server side.
* changing the database structure during runtime may be a pita using sqlsrv for default preventing changes to the db structure (https://learn.microsoft.com/en-us/troubleshoot/sql/ssms/error-when-you-save-table). adding columns to the end appears to be easier instad of insertions between.

### useage notes and caveats
* dragging form elements for reordering within the form-editors doesn't work on handhelds because touch-events do not include this function. constructing form components and forms will need devices with mice or a supported pointer to avoid bloating scripts. reordered images will disappear but don't worry.
* orders can be deleted by administrative users and requesting unit members at any time. this module is for operational communication only, not for persistent documentation purpose. it is not supposed to replace your erp
* the manual is intentionally editable to accomodate it to users comprehension.
* MDR §14 sample check will ask for a check for every vendors [product that qualifies as trading good](#sample-check) if the last check for any product of this vendor exceeds the mdr14_sample_interval timespan set in setup.ini, so e.g. once a year per vendor by default. this applies for all products that have not been checked within mdr14_sample_reusable timespan. 

### importing vendor pricelists
vendor pricelists must have an easy structure to be importable. it may need additional off-app customizing available data to have input files like:

| Article Number | Article Name | EAN         | Sales Unit |
| :------------- | :----------- | :---------- | :--------- |
| 1234           | Shirt        | 90879087    | Piece      |
| 2345           | Trousers     | 23459907    | Package    |
| 3456           | Socks        | 90897345    | Pair       |

while setting up a vendor an import rule must be defined like:
```js
{
    "filesettings": {
        "headerrowindex": 0,
        "dialect": {
            "separator": ";",
            "enclosure": "\"",
            "escape": ""
        },
        "columns": [
            "Article Number",
            "Article Name",
            "EAN",
            "Sales Unit"
        ]
    },
    "modify": {
        "rewrite": {
            "article_no": ["Article Number"],
            "article_name": ["Article Name"],
            "article_ean": ["EAN"],
            "article_unit": ["Sales Unit"]
        }
    }
}
```
*headerrowindex* and *dialect* are added with a default value from setup.ini if left out.

### sample check
to detect trading goods for the MDR §14 sample check add a respective filter like:
```js
{
	"filesetting": {
		"columns": ["article_no", "article_name"]
	},
	"filter": [
		{
			"apply": "filter_by_expression",
			"comment": "delete unnecessary products",
			"keep": false, //or true
			"match": {
				"all": {
					"article_name": "ANY REGEX PATTERN THAT MIGHT MATCH ARTICLE NAMES THAT QUALIFY AS TRADING GOOD (OR DON'T IN ACCORDANCE TO keep-FLAG)"
				}
			}
		}
	]
}
```
without a filter none of the vendors products will be treated as a trading good!

## ressources
### external libraries
* https://github.com/mebjas/html5-qrcode
* https://github.com/szimek/signature_pad
* https://github.com/nimiq/qr-creator
* https://github.com/lindell/JsBarcode/
* https://github.com/omrips/viewstl
* https://github.com/mk-j/PHP_XLSXWriter
* https://github.com/tecnickcom/TCPDF

### kudos on additional help on
* [restful api](https://www.9lessons.info/2012/05/create-restful-services-api-in-php.html)
* [put request with multipart form data](https://stackoverflow.com/a/18678678)
* [webmanifest for iis](https://stackoverflow.com/questions/49566446/how-can-i-have-iis-properly-serve-webmanifest-files-on-my-web-site)
* [webworker caching](https://developer.chrome.com/docs/workbox/caching-strategies-overview)
* [indexedDB](https://github.com/jakearchibald/idb)

# open tasks
* view other forms (e.g. instructions, modal? prevent filling out)
* user manual

* incorporation and sample check custom forms (not languagafile)

* incorporate product
    * adding documents
    * adding photo (scaled down) ? (display on order selection) (->consumables as well)

* vendor address, email, phone, customer id
* vendor list
* vendor mailto (certificates)

* user selectable color themes?
* monitoring measuring equipment, rental parts, machinery, crutches
* caro audit (vendors, certificate expiry dates, user certificates)
* purchase: batch identifier (product and delivery note number) for...
* ...material tracing within documentation
* calendar and alerts
* md mermaid flowcharts
