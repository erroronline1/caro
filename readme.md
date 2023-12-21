CARO
Cloud Assisted Record and Operation


requirements:
* php > 8
* mysql (or some other database, but queries may have to be adjusted. as i chose pdo as database connectivity i hope this is possible. couldn't check though)
* ssl (camera access for qr-scanner and serviceworkers don't work otherwise)
* php.ini memory_limit ~1024MB for processing of large csv-files
* my.ini (MySQL) max_allowed_packet = 100M

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
