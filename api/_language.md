# Important notices on customizing language files

## templates/default.manual.XX.env/.json
templates/default.manual.XX.env/.json stores default database entries that are made during installation, so these can as well be prepared in the set up default language. Crop, extend and change to your needs in advance to installation. This file is only used once at this procedure.

## language.XX.env/.json

language.XX.env/.json store language chunks, tokens like :token are replaced by the language handlers (language.php and language.js).
The structure MUST be the same across all provided or extended language files! 
Any key is used and therefore can affect function of the whole application. Try to avoid equal values within one context if possible.
Values occasionally may be stored within databases.

Some subsets are displayed in the given order, hence can be customized to your comprehensible needs. Respective keys are mentioned below.

As stated in the [readme](../readme.md) it is recommended to keep the original JSON-file and customize copies named language.XX.**env**, where you can alter or append selected entries, without deleting a probably needed one.

### company
Holds general company info, used on pdf exports.

### permissions
Autorization levels with language specific values. Default keys are hardcoded!
Append if additional custom modules require specific permissions. `permissions` order is passed to application.

### units
Organizational units.

First item (common) is hardcoded and used as default for texttemplates assignments and possibly other select options, admin and office are hardcoded as well.
Once defined keys remain in the database, so it should be appended at best! `units` order is passed to application. On appending, the sourcecode should be reviewed, e.g.
* notification.php for excluding messaging all users of `common, admin and office` on open records and `common and admin` on expiring trainings
* unit intersections should exclude admin to reduce amount of case documentations and scheduled trainings

### skills
Can be adjusted during runtime, extend or reduce to appropriate length.

_LEVEL is a reserved key.
Duties CAN resemble units but this is optional. Second level key (_DESCRIPTION) is mandatory per duty. *All* orders are passed to application.

### documentcontext
Document contexts - defined here for language reasons.
Avoid reusing any key regardless of top level key and translation to reduce confusion.
Do not use any forbidden word as defined in config.ini.
Keys are hardcoded.

* identify.casedocumentation: patients treatment process, related to a case of a medical device treatment
* identify.incident: occasionally reportable incidents, related to medical device treatment, better overview of cases
* identify.equipmentsurveillance: track equipment, each their own identifier
* identify.generalrecords: make general records appendable

*All* orders are passed to application.

### casestate
Administrative case state, generates a pseudodocument to check states by given permission levels and makes unclosed records filterable by given states within overview. Checkbox options with a low level key according to documentcontext high level keys record_pseudodocument_{low level key} translation must be set in [record](#record). `casestate` order is passed to application.

### orderreturns
Reasons for a returned order. Split into `critical` and `easy`, while ordered alphabetically critical return reasons may trigger a warning to quality assurance, product review and reincorporation process. Since this is a quality management relevant setting it is not buried withon order language chunks. Keys must not contain duplicates.

### general
All keys are hardcoded, `weekday` and `month` order is passed to application.

### application
Most keys are hardcoded. Terms_of_service keys however are handled as headers and therefore require translation. `term_of_service` order is passed to application.

### assemble
All keys are hardcoded.

### audit
All keys are hardcoded.

`risk_issues`-keys must contain `risk.type` as well. `audit.execute.rating_steps` order is passed to application.

### calendar
All keys are hardcoded. `timesheet.pto`, `timesheet.signature` and `timesheet.export.sheet_daily` orders are passed to application.

### consumables
All keys are hardcoded.

### csvfilter
All keys are hardcoded.

### file
All keys are hardcoded.

### menu
All keys are hardcoded.

### maintenance
All keys are hardcoded.

### measure
All keys are hardcoded.

### message
All keys are hardcoded.

### order
Most keys are hardcoded, orderstate can be cropped or extended. `order.ordertype`, `order.orderstate` orders are passed to application.

### record
All keys are hardcoded. `type` order is passed to application. `record.lifespan.years` contains the selecteable lifespans of records concerning automated deletion to match GDPR requirements. Keys are pure numbers in years, value contain a description and eventually examples.

### responsibility
All keys are hardcoded.

### risk
Most keys are hardcoded.

probabilities and damages can be modified. The number of items correspond to config.ini limits risk_acceptance_level.
preset_process and preset_risk can be cropped or extended as well.

`probabilities`, `damage`, `preset_process` and `preset_risk` orders are passed to application.

`type`-keys must be contained within `audit.risk_issues` as well

### texttemplate
All keys are hardcoded. `use.genus` can be extended or cropped, order is passed to application.

### tool
All keys are hardcoded.

### user
All keys are hardcoded.

### regulatory
These are the chapters of the ISO 13485 and other regulatory issues. They serve as a checklist for fulfillment regarding document assignment and can be adjusted during runtime and extended by necessary regulatory aspects.

`regulatory` order is passed to application.

### risks
These are the risks according to ISO 14971 examples of events and circumstances of appedix C and in accordance to [DGIHV](https://www.dgihv.org). They serve as a checklist for fulfillment regarding risks and can be adjusted during runtime and extended by necessary regulatory aspects, new risks and process risks that are applicable for a risk based approach regarding ISO 13485.

`risks` order is passed to application.

### html5_qrcode
Language mod for https://github.com/mebjas/html5-qrcode

Use regex search `(return|throw)".+?"` to find language chunks at approximately the beginning of the last third of the minified sourcecode to replace with api._lang.GET['html5_qrcode.xxx.yyy'] according to language.en.json, language.de.json or [applicable language files](#customisation). Respective chunks can be identified by their english representation within the provided files.