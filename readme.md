![CARO logo](media/favicon/windows11/SmallTile.scale-100.png)
# CARO - Cloud Assisted Record and Operation

## prerequisites
* php >= 8
* mysql or sql server (or some other database, but queries may have to be adjusted. as i chose pdo as database connectivity i hope this is possible)
* ssl (camera access for qr-scanner and serviceworkers don't work otherwise)
* vendor pricelists as csv-files - one per vendor

tested server environments:
* apache [uniform server zero XV](https://uniformserver.com) with php 8.2, mysql 8.0.31
* microsoft iis with sql express (sql server 22)

tested devices:
* desktop pc win10 edge-browser
* notebook win11 firefox-browser
* smartphone android12 firefox-browser

## installation
* php.ini memory_limit ~1024MB for processing of large csv-files, disable open_basedir at least for local iis for file handlers
* my.ini (MySQL) max_allowed_packet = 100M
* manually set mime type for site-webmanifest as application/manifest+json for iis servers
* set up api/setup.ini, especially the used sql subset and its credentials
* run api/install.php, you will be redirected to the frontpage afterwards - no worries, in case of a rerun nothing will happen

## limitations and intended usecases
* dragging doesn't work on handhelds for touch-events do not include this function. constructing form components and forms will need devices with mice or a supported pointer to avoid bloating scripts.
* orders can be deleted at any time. this module is for operational communication only, not for persistent documentation purpose.

## ressources
### external libraries
* https://github.com/mebjas/html5-qrcode
* https://github.com/szimek/signature_pad
* https://github.com/nimiq/qr-creator
* https://github.com/lindell/JsBarcode/

### kudos on additional help on
* [restful api](https://www.9lessons.info/2012/05/create-restful-services-api-in-php.html)
* [put request with multipart form data](https://stackoverflow.com/a/18678678)
* [webmanifest for iis](https://stackoverflow.com/questions/49566446/how-can-i-have-iis-properly-serve-webmanifest-files-on-my-web-site)
* [webworker caching](https://developer.chrome.com/docs/workbox/caching-strategies-overview)
* [indexedDB](https://github.com/jakearchibald/idb)

# open tasks
* syncing post, put and delete
* syncing every possible get to chache?
* unread messages indicator and notification
* stl viewer
* qr-code reader raw implementation
* forms and contexts
* pdf export
* vendor list
* user qualifications, certificates
* monitoring measuring equipment, rental parts, machinery, crutches
* sample check MDR §14
* caro audit
* manuals
* pdf directory (internal and external documents)
* purchase: batch identifier (product and delivery note number) for...
* ...material tracing within documentation
* text recommendations

