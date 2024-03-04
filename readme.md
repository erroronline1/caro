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

### useage notes and caveats
* dragging form elements for reordering within the form-editors doesn't work on handhelds because touch-events do not include this function. constructing form components and forms will need devices with mice or a supported pointer to avoid bloating scripts. reordered images will disappear but don't worry.
* orders can be deleted by administrative users and requesting unit members at any time. this module is for operational communication only, not for persistent documentation purpose. it is not supposed to replace your erp
* the manual is intentionally editable to accomodate it to users comprehension.

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

if e.g. no ean is available modify>rewrite>article_ean can be set to [""]. rewrite rules depend on the database columns for the caro_consumables_products database-table.

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
* records by unit? (add unit/user units) select by permissions
* filter by context
* export append all files that are not embedded (aka !images)
* add prevent data loss to form fields within records

* input type email (multiple), phone

* vendor address, email, phone, customer id
* vendor list
* vendor mailto (certificates)

* settings? force desktop mode for small window? themes?
* pdf export
* monitoring measuring equipment, rental parts, machinery, crutches
* sample check MDR §14
* caro audit
* purchase: batch identifier (product and delivery note number) for...
* ...material tracing within documentation
* calendar and alerts
* md mermaid flowcharts

## form contexts ##
### treatment ###
* view other forms (e.g. instructions, modal? prevent filling out)
* import texttemplates
* user manual
