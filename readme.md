CARO
Cloud Assisted Record and Operation


prerequisites:
* php > 8
* mysql or sql server (or some other database, but queries may have to be adjusted. as i chose pdo as database connectivity i hope this is possible)
* ssl (camera access for qr-scanner and serviceworkers don't work otherwise)
* php.ini memory_limit ~1024MB for processing of large csv-files, disable open_basedir at least for local iis for file handlers
* my.ini (MySQL) max_allowed_packet = 100M
* manually set mime type for site-webmanifest as application/manifest+json for iis servers
* vendor pricelists as csv-files

information:
* dragging doesn't work on handhelds for touch-events do not include this function. constructing form will need devices with mice or a supported pointer to avoid bloating scripts.




external libraries:
* https://github.com/mebjas/html5-qrcode
* https://github.com/szimek/signature_pad
* https://github.com/nimiq/qr-creator
* https://github.com/lindell/JsBarcode/

partially heavily inspired by:
* https://www.9lessons.info/2012/05/create-restful-services-api-in-php.html
* https://stackoverflow.com/a/18678678
* https://stackoverflow.com/questions/49566446/how-can-i-have-iis-properly-serve-webmanifest-files-on-my-web-site